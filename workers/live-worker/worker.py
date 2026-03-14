"""Live worker processor implementation.

This worker handles real-time pose estimation for live streaming sessions.
It supports switching between MediaPipe and YOLO26 backends per-session.

Like the video worker, this module only extracts pose metrics and reports
them back to the PHP API. PHP remains the single scoring authority.
"""

from __future__ import annotations

import base64
import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

import numpy as np
try:
    import cv2
except ModuleNotFoundError:  # pragma: no cover - exercised in non-worker envs
    cv2 = None

from pose_engines import EngineRuntimeConfig, PoseEngineManager

ROOT = Path(__file__).resolve().parent
SHARED_ROOT = ROOT.parent / "shared"

if str(SHARED_ROOT) not in sys.path:
    sys.path.append(str(SHARED_ROOT))

from worker_contract import load_contract, route, validate_payload  # noqa: E402

API_BASE_URL = os.getenv("WORKER_API_BASE_URL", "http://nginx").rstrip("/")
API_TIMEOUT_SECONDS = float(os.getenv("WORKER_API_TIMEOUT_SECONDS", "20"))

LIVE_CONTRACT = load_contract("live-worker")
NEXT_JOB_ENDPOINT = f"/api/v1{route(LIVE_CONTRACT, 'next_job')}"
NEXT_BATCH_ENDPOINT = f"/api/v1{route(LIVE_CONTRACT, 'next_batch')}"
FRAMES_ENDPOINT = f"/api/v1{route(LIVE_CONTRACT, 'frames')}"
COMPLETE_ENDPOINT = f"/api/v1{route(LIVE_CONTRACT, 'complete')}"
FAIL_ENDPOINT = f"/api/v1{route(LIVE_CONTRACT, 'fail')}"


# ── Decoupled engine manager ────────────────────────────────────────────
_engine_manager = PoseEngineManager()
_session_states: dict[int, dict[str, Any]] = {}
_engine_log_keys: set[tuple[str, str | None, bool]] = set()

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
    log_key = (engine_name, model_variant, multi_person_mode)
    if log_key not in _engine_log_keys:
        print(
            "[live-worker] initialised engine: "
            f"{engine_name} (variant={model_variant or 'default'}, multi_person={multi_person_mode})"
        )
        _engine_log_keys.add(log_key)
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

    validate_payload(LIVE_CONTRACT, "job", job)

    return job


def fetch_next_frame_batch() -> dict[str, Any] | None:
    """Poll the PHP API for the next browser-uploaded frame batch."""
    response = _api_request(NEXT_BATCH_ENDPOINT, method="POST", payload={}, allow_no_content=True)
    if response is None:
        return None

    job = response.get("data")
    if job is None or not isinstance(job, dict):
        return None

    validate_payload(LIVE_CONTRACT, "frame_batch", job)

    return job


def report_frames(
    session_id: int,
    organization_id: int,
    frames: list[dict[str, Any]],
    telemetry: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Report a batch of analysed frames to the PHP API."""
    payload = {
        "session_id":      session_id,
        "organization_id": organization_id,
        "frames":          frames,
    }
    if telemetry:
        payload["telemetry"] = telemetry
    validate_payload(LIVE_CONTRACT, "frames", payload)
    return _api_post(FRAMES_ENDPOINT, payload)


def complete_session(
    session_id: int,
    organization_id: int,
    summary_metrics: dict[str, Any],
) -> dict[str, Any]:
    """Mark a live session as completed with summary metrics."""
    payload = {
        "session_id":      session_id,
        "organization_id": organization_id,
        "summary_metrics": summary_metrics,
    }
    validate_payload(LIVE_CONTRACT, "complete", payload)
    return _api_post(COMPLETE_ENDPOINT, payload)


def fail_session(
    session_id: int,
    organization_id: int,
    error_message: str = "",
) -> None:
    """Mark a live session as failed."""
    payload = {
        "session_id":      session_id,
        "organization_id": organization_id,
        "error_message":   (error_message or "Live processing failed").strip(),
    }
    validate_payload(LIVE_CONTRACT, "fail", payload)
    _api_post(FAIL_ENDPOINT, payload)


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


def _decode_jpeg_base64(image_base64: str) -> Any | None:
    """Decode a base64 JPEG payload into a BGR OpenCV frame."""
    if cv2 is None:
        return None

    try:
        blob = base64.b64decode(image_base64, validate=True)
    except Exception:  # noqa: BLE001
        return None

    array = np.frombuffer(blob, dtype=np.uint8)
    if array.size == 0:
        return None

    return cv2.imdecode(array, cv2.IMREAD_COLOR)


def _build_worker_telemetry(
    *,
    processed: int,
    skipped: int,
    decode_failures: int,
    worker_lags_ms: list[float],
) -> dict[str, Any]:
    telemetry: dict[str, Any] = {
        "worker_processed_frames": max(0, int(processed)),
        "worker_skipped_frames": max(0, int(skipped)),
        "worker_decode_failures": max(0, int(decode_failures)),
        "worker_lag_samples": len(worker_lags_ms),
        "last_worker_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
    }

    if worker_lags_ms:
        telemetry["worker_lag_ms_avg"] = round(sum(worker_lags_ms) / len(worker_lags_ms), 2)
        telemetry["worker_lag_ms_max"] = round(max(worker_lags_ms), 2)
    else:
        telemetry["worker_lag_ms_avg"] = 0.0
        telemetry["worker_lag_ms_max"] = 0.0

    return telemetry


def _is_stale_frame_batch(
    worker_lags_ms: list[float],
    *,
    max_e2e_latency_ms: int,
    multiplier: float,
) -> bool:
    if not worker_lags_ms:
        return False

    threshold = max(1.0, float(max_e2e_latency_ms) * max(0.5, multiplier))
    return min(worker_lags_ms) > threshold


def process_uploaded_frame_batch(job: dict[str, Any]) -> dict[str, Any]:
    """Decode and process a browser-uploaded frame batch."""
    frames = job.get("frames", [])
    if not isinstance(frames, list) or len(frames) == 0:
        return {"processed": 0, "skipped": 0, "avg_latency_ms": 0.0}

    valid_items: list[dict[str, Any]] = []
    skipped = 0
    decode_failures = 0
    worker_lags_ms: list[float] = []
    now_ms = int(time.time() * 1000)
    stale_multiplier = float(job.get("stale_batch_drop_multiplier", 1.0) or 1.0)
    max_e2e_latency_ms = int(job.get("max_e2e_latency_ms", 2000))

    for item in frames:
        if not isinstance(item, dict):
            skipped += 1
            continue

        image_base64 = str(item.get("image_jpeg_base64", "")).strip()
        frame_number = int(item.get("frame_number", 0))
        if image_base64 == "" or frame_number <= 0:
            skipped += 1
            continue

        captured_at_ms = int(item.get("captured_at_ms", 0) or 0)
        if captured_at_ms > 0:
            worker_lags_ms.append(float(max(0, now_ms - captured_at_ms)))
        valid_items.append(item)

    if valid_items and _is_stale_frame_batch(
        worker_lags_ms,
        max_e2e_latency_ms=max_e2e_latency_ms,
        multiplier=stale_multiplier,
    ):
        stale_frames = len(valid_items)
        telemetry = _build_worker_telemetry(
            processed=0,
            skipped=skipped + stale_frames,
            decode_failures=decode_failures,
            worker_lags_ms=worker_lags_ms,
        )
        telemetry["stale_frame_batches_dropped"] = 1
        telemetry["stale_frames_dropped"] = stale_frames
        report_frames(
            int(job["session_id"]),
            int(job["organization_id"]),
            [],
            telemetry,
        )
        return {
            "processed": 0,
            "skipped": skipped + stale_frames,
            "avg_latency_ms": 0.0,
            "telemetry": telemetry,
            "dropped_reason": "stale_batch",
        }

    frame_numbers: list[int] = []
    frames_bgr: list[Any] = []

    for item in valid_items:
        frame = _decode_jpeg_base64(str(item.get("image_jpeg_base64", "")).strip())
        if frame is None:
            skipped += 1
            decode_failures += 1
            continue

        frames_bgr.append(frame)
        frame_numbers.append(int(item.get("frame_number", 0)))

    if not frames_bgr:
        telemetry = _build_worker_telemetry(
            processed=0,
            skipped=skipped,
            decode_failures=decode_failures,
            worker_lags_ms=worker_lags_ms,
        )
        report_frames(
            int(job["session_id"]),
            int(job["organization_id"]),
            [],
            telemetry,
        )
        return {"processed": 0, "skipped": skipped, "avg_latency_ms": 0.0, "telemetry": telemetry}

    telemetry = _build_worker_telemetry(
        processed=0,
        skipped=skipped,
        decode_failures=decode_failures,
        worker_lags_ms=worker_lags_ms,
    )

    result = process_frame_batch(
        engine_name=str(job.get("pose_engine", "yolo26")),
        frames_bgr=frames_bgr,
        session_id=int(job["session_id"]),
        organization_id=int(job["organization_id"]),
        model_variant=str(job.get("model_variant", "")).strip() or None,
        multi_person_mode=bool(job.get("multi_person_mode", False)),
        smoothing_alpha=float(job.get("smoothing_alpha", 0.35)),
        min_joint_confidence=float(job.get("min_joint_confidence", 0.45)),
        tracking_max_distance=float(job.get("tracking_max_distance", 0.15)),
        frame_numbers=frame_numbers,
        max_e2e_latency_ms=max_e2e_latency_ms,
        telemetry=telemetry,
    )
    result["skipped"] = int(result.get("skipped", 0)) + skipped
    result["telemetry"] = _build_worker_telemetry(
        processed=int(result.get("processed", 0)),
        skipped=int(result.get("skipped", 0)),
        decode_failures=decode_failures,
        worker_lags_ms=worker_lags_ms,
    )
    return result


def process_frame_batch(
    engine_name: str,
    frames_bgr: list[Any],
    session_id: int,
    organization_id: int,
    model_variant: str | None = None,
    multi_person_mode: bool = False,
    smoothing_alpha: float = 0.35,
    min_joint_confidence: float = 0.45,
    tracking_max_distance: float = 0.15,
    start_frame_number: int = 0,
    frame_numbers: list[int] | None = None,
    max_e2e_latency_ms: int = 2000,
    telemetry: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Process a batch of BGR frames through the selected pose engine.

    Parameters
    ----------
    engine_name:
        "mediapipe" or "yolo26"
    frames_bgr:
        List of numpy BGR frames to process.
    session_id, organization_id:
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

        frame_number = (
            int(frame_numbers[i])
            if frame_numbers is not None and i < len(frame_numbers)
            else start_frame_number + i
        )

        scored_frames.append({
            "frame_number": frame_number,
            "metrics":      metrics,
            "latency_ms":   metrics.get("latency_ms", 0.0),
        })

    telemetry_payload = dict(telemetry or {})
    telemetry_payload["worker_processed_frames"] = int(telemetry_payload.get("worker_processed_frames", 0)) + len(scored_frames)
    telemetry_payload["worker_skipped_frames"] = int(telemetry_payload.get("worker_skipped_frames", 0)) + skipped

    # Report to PHP API
    if scored_frames or telemetry_payload:
        report_frames(session_id, organization_id, scored_frames, telemetry_payload)

    avg_latency = 0.0
    if scored_frames:
        avg_latency = sum(f["latency_ms"] for f in scored_frames) / len(scored_frames)

    return {
        "processed":      len(scored_frames),
        "skipped":        skipped,
        "avg_latency_ms": round(avg_latency, 2),
        "telemetry":      telemetry_payload,
    }
