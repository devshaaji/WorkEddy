"""Live worker processor implementation.

This worker handles real-time pose estimation for live streaming sessions.
It supports switching between MediaPipe and YOLO26 backends per-session.

Like the video worker, this module only extracts pose metrics and reports
them back to the PHP API. PHP remains the single scoring authority.
"""

from __future__ import annotations

import json
import os
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

from pose_engines import EngineRuntimeConfig, PoseEngineManager

ROOT = Path(__file__).resolve().parent

API_BASE_URL = os.getenv("WORKER_API_BASE_URL", "http://nginx").rstrip("/")
API_TIMEOUT_SECONDS = float(os.getenv("WORKER_API_TIMEOUT_SECONDS", "20"))

NEXT_JOB_ENDPOINT = "/api/v1/internal/live-worker/jobs/next"
FRAMES_ENDPOINT   = "/api/v1/internal/live-worker/frames"
COMPLETE_ENDPOINT = "/api/v1/internal/live-worker/sessions/complete"
FAIL_ENDPOINT     = "/api/v1/internal/live-worker/sessions/fail"


# ── Decoupled engine manager ────────────────────────────────────────────
_engine_manager = PoseEngineManager()
_session_states: dict[int, dict[str, Any]] = {}

ANGLE_KEYS = ("trunk_angle", "neck_angle", "upper_arm_angle", "lower_arm_angle", "wrist_angle")


def _get_engine(
    engine_name: str,
    model_variant: str | None = None,
    multi_person_mode: bool = False,
):
    """Get or create a cached pose engine instance."""
    runtime_cfg = EngineRuntimeConfig(
        model_variant=model_variant,
        multi_person_mode=multi_person_mode,
    )
    engine = _engine_manager.get_engine(engine_name, runtime_cfg)
    print(
        "[live-worker] initialised engine: "
        f"{engine_name} (variant={model_variant or 'default'}, multi_person={multi_person_mode})"
    )
    return engine


def _distance(x1: float, y1: float, x2: float, y2: float) -> float:
    return ((x1 - x2) ** 2 + (y1 - y2) ** 2) ** 0.5


def _apply_stability_controls(
    session_id: int,
    metrics: dict[str, Any],
    *,
    smoothing_alpha: float,
    min_joint_confidence: float,
    tracking_max_distance: float,
) -> dict[str, Any] | None:
    """Apply confidence filtering, ID tracking, and angle smoothing."""
    confidence = float(metrics.get("confidence", 0.0) or 0.0)
    if confidence < min_joint_confidence:
        return None

    state = _session_states.setdefault(session_id, {
        "last_center": None,
        "track_id": 1,
        "smoothed_angles": {},
    })

    center_x = float(metrics.get("subject_center_x", 0.5))
    center_y = float(metrics.get("subject_center_y", 0.5))
    last_center = state.get("last_center")

    if isinstance(last_center, tuple) and len(last_center) == 2:
        d = _distance(center_x, center_y, float(last_center[0]), float(last_center[1]))
        if d > tracking_max_distance:
            state["track_id"] = int(state.get("track_id", 1)) + 1

    state["last_center"] = (center_x, center_y)
    metrics["subject_track_id"] = int(state.get("track_id", 1))

    smoothed = state.get("smoothed_angles", {})
    for key in ANGLE_KEYS:
        if key not in metrics:
            continue
        current = float(metrics[key])
        prev = smoothed.get(key)
        if prev is None:
            smoothed_value = current
        else:
            smoothed_value = (smoothing_alpha * current) + ((1.0 - smoothing_alpha) * float(prev))
        smoothed[key] = smoothed_value
        metrics[key] = round(smoothed_value, 2)

    state["smoothed_angles"] = smoothed
    return metrics


# ── HTTP helpers (same pattern as video worker) ────────────────────────

def _api_request(
    endpoint: str,
    *,
    method: str,
    payload: dict[str, Any] | None = None,
    allow_no_content: bool = False,
) -> dict[str, Any] | None:
    token = os.getenv("WORKER_API_TOKEN", "").strip()
    if token == "":
        raise RuntimeError("WORKER_API_TOKEN is not configured")

    body = None
    headers = {"X-Worker-Token": token}

    if payload is not None:
        body = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = "application/json"

    request = urllib.request.Request(
        f"{API_BASE_URL}{endpoint}",
        data=body,
        method=method,
        headers=headers,
    )

    try:
        with urllib.request.urlopen(request, timeout=API_TIMEOUT_SECONDS) as response:  # noqa: S310
            raw = response.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as exc:
        error_body = exc.read().decode("utf-8", errors="replace") if hasattr(exc, "read") else ""
        raise RuntimeError(
            f"Live worker API request failed with status {exc.code}: {error_body or str(exc)}"
        ) from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Live worker API request failed: {exc.reason}") from exc

    if raw == "":
        return None if allow_no_content else {}

    try:
        parsed = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Live worker API returned non-JSON response: {raw}") from exc

    if isinstance(parsed, dict) and parsed.get("error"):
        raise RuntimeError(f"Live worker API returned error: {parsed['error']}")

    return parsed if isinstance(parsed, dict) else {}


def _api_post(endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
    response = _api_request(endpoint, method="POST", payload=payload)
    return response if isinstance(response, dict) else {}


def fetch_next_job() -> dict[str, Any] | None:
    """Poll the PHP API for the next live-session job."""
    response = _api_request(NEXT_JOB_ENDPOINT, method="POST", payload={}, allow_no_content=True)
    if response is None:
        return None

    job = response.get("data")
    if job is None or not isinstance(job, dict):
        return None

    return job


def report_frames(
    session_id: int,
    organization_id: int,
    model: str,
    frames: list[dict[str, Any]],
) -> dict[str, Any]:
    """Report a batch of analysed frames to the PHP API."""
    return _api_post(FRAMES_ENDPOINT, {
        "session_id":      session_id,
        "organization_id": organization_id,
        "model":           model,
        "frames":          frames,
    })


def complete_session(
    session_id: int,
    organization_id: int,
    summary_metrics: dict[str, Any],
) -> dict[str, Any]:
    """Mark a live session as completed with summary metrics."""
    return _api_post(COMPLETE_ENDPOINT, {
        "session_id":      session_id,
        "organization_id": organization_id,
        "summary_metrics": summary_metrics,
    })


def fail_session(
    session_id: int,
    organization_id: int,
    error_message: str = "",
) -> None:
    """Mark a live session as failed."""
    _api_post(FAIL_ENDPOINT, {
        "session_id":      session_id,
        "organization_id": organization_id,
        "error_message":   (error_message or "Live processing failed").strip(),
    })


def process_live_session(job: dict[str, Any]) -> None:
    """Process a live session job.

    This is the main entry point for the live-worker runner.
    The worker will:
      1. Initialise the correct pose engine (mediapipe or yolo26)
      2. Listen for incoming frames via a shared video source
      3. Estimate pose per frame and batch-report to PHP API
      4. Complete the session when done

    NOTE: In the current pull-based architecture, the worker processes
    a "session" by reading frames from a video source (webcam capture
    or RTSP stream URL stored in the job). The client-side captures
    and stores frames; the worker processes them.
    """
    session_id      = int(job["session_id"])
    organization_id = int(job["organization_id"])
    engine_name     = str(job.get("pose_engine", "yolo26"))
    model_variant   = str(job.get("model_variant", "")).strip() or None
    multi_person_mode = bool(job.get("multi_person_mode", False))
    model           = str(job.get("model", "reba"))
    target_fps      = float(job.get("target_fps", 5.0))
    batch_window_ms = int(job.get("batch_window_ms", 500))
    max_e2e_ms      = int(job.get("max_e2e_latency_ms", 2000))
    smoothing_alpha = float(job.get("smoothing_alpha", os.getenv("LIVE_TEMPORAL_SMOOTHING_ALPHA", "0.35")))
    min_joint_confidence = float(job.get("min_joint_confidence", os.getenv("LIVE_MIN_JOINT_CONFIDENCE", "0.45")))
    tracking_max_distance = float(job.get("tracking_max_distance", os.getenv("LIVE_TRACKING_MAX_DISTANCE", "0.15")))

    print(
        f"[live-worker] session={session_id} engine={engine_name} "
        f"model={model} variant={model_variant or 'default'} multi_person={multi_person_mode} "
        f"target_fps={target_fps} batch_window_ms={batch_window_ms} "
        f"smoothing_alpha={smoothing_alpha} min_conf={min_joint_confidence} "
        f"tracking_max_dist={tracking_max_distance}"
    )

    # Get or create the engine for this session
    engine = _get_engine(engine_name, model_variant=model_variant, multi_person_mode=multi_person_mode)

    # The session is now active; the worker will be called again
    # when frames arrive. For the pull-based model, we just mark
    # readiness and return. The runner loop will pick up frame
    # batches via the PHP API.
    print(f"[live-worker] engine ready for session {session_id}")


def process_frame_batch(
    engine_name: str,
    frames_bgr: list[Any],
    session_id: int,
    organization_id: int,
    model: str,
    model_variant: str | None = None,
    multi_person_mode: bool = False,
    smoothing_alpha: float = 0.35,
    min_joint_confidence: float = 0.45,
    tracking_max_distance: float = 0.15,
    start_frame_number: int = 0,
    max_e2e_latency_ms: int = 2000,
) -> dict[str, Any]:
    """Process a batch of BGR frames through the selected pose engine.

    Parameters
    ----------
    engine_name:
        "mediapipe" or "yolo26"
    frames_bgr:
        List of numpy BGR frames to process.
    session_id, organization_id, model:
        Identifiers for the PHP API callback.
    start_frame_number:
        Frame counter offset for this batch.
    max_e2e_latency_ms:
        Skip remaining frames if total batch latency exceeds this.

    Returns
    -------
    Summary dict with keys: processed, skipped, avg_latency_ms.
    """
    engine = _get_engine(
        engine_name,
        model_variant=model_variant,
        multi_person_mode=multi_person_mode,
    )
    batch_start = time.perf_counter()

    scored_frames: list[dict[str, Any]] = []
    skipped = 0

    for i, frame in enumerate(frames_bgr):
        # Latency guard: skip if we're exceeding the budget
        elapsed_ms = (time.perf_counter() - batch_start) * 1000.0
        if elapsed_ms > max_e2e_latency_ms:
            skipped += len(frames_bgr) - i
            print(
                f"[live-worker] latency budget exceeded ({elapsed_ms:.0f}ms > "
                f"{max_e2e_latency_ms}ms), skipping {skipped} frames"
            )
            break

        metrics = engine.estimate(frame)
        if metrics is None:
            continue

        metrics = _apply_stability_controls(
            session_id,
            metrics,
            smoothing_alpha=smoothing_alpha,
            min_joint_confidence=min_joint_confidence,
            tracking_max_distance=tracking_max_distance,
        )
        if metrics is None:
            continue

        scored_frames.append({
            "frame_number": start_frame_number + i,
            "metrics":      metrics,
            "latency_ms":   metrics.get("latency_ms", 0.0),
        })

    # Report to PHP API
    if scored_frames:
        report_frames(session_id, organization_id, model, scored_frames)

    avg_latency = 0.0
    if scored_frames:
        avg_latency = sum(f["latency_ms"] for f in scored_frames) / len(scored_frames)

    return {
        "processed":      len(scored_frames),
        "skipped":        skipped,
        "avg_latency_ms": round(avg_latency, 2),
    }
