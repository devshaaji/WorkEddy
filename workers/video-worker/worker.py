"""Video worker processor implementation.

This worker only extracts pose metrics and reports them back to the PHP API.
PHP remains the single scoring and persistence authority.
"""

from __future__ import annotations

import importlib.util
import json
import os
import sys
import urllib.error
import urllib.request
from functools import lru_cache
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent
SHARED_ROOT = ROOT.parent / "shared"

if str(SHARED_ROOT) not in sys.path:
    sys.path.append(str(SHARED_ROOT))

from worker_contract import load_contract, route, validate_payload  # noqa: E402

API_BASE_URL = os.getenv("WORKER_API_BASE_URL", "http://nginx").rstrip("/")
API_TIMEOUT_SECONDS = float(os.getenv("WORKER_API_TIMEOUT_SECONDS", "20"))
VIDEO_CONTRACT = load_contract("video-worker")
NEXT_JOB_ENDPOINT = f"/api/v1{route(VIDEO_CONTRACT, 'next_job')}"
COMPLETE_ENDPOINT = f"/api/v1{route(VIDEO_CONTRACT, 'complete')}"
FAIL_ENDPOINT = f"/api/v1{route(VIDEO_CONTRACT, 'fail')}"


def _load(name: str, filename: str):
    spec = importlib.util.spec_from_file_location(name, ROOT / filename)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load module {filename}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


@lru_cache(maxsize=1)
def _frame_extractor_module():
    return _load("frame_extractor", "frame_extractor.py")


@lru_cache(maxsize=1)
def _pose_detector_module():
    return _load("pose_estimation", "pose_estimation.py")


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
    headers = {
        "X-Worker-Token": token,
    }

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
            f"Worker API request failed with status {exc.code}: {error_body or str(exc)}"
        ) from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Worker API request failed: {exc.reason}") from exc

    if raw == "":
        return None if allow_no_content else {}

    try:
        parsed = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Worker API returned non-JSON response: {raw}") from exc

    if isinstance(parsed, dict) and parsed.get("error"):
        raise RuntimeError(f"Worker API returned error: {parsed['error']}")

    return parsed if isinstance(parsed, dict) else {}


def _api_post(endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
    response = _api_request(endpoint, method="POST", payload=payload)
    return response if isinstance(response, dict) else {}


def fetch_next_job() -> dict[str, Any] | None:
    response = _api_request(NEXT_JOB_ENDPOINT, method="POST", payload={}, allow_no_content=True)
    if response is None:
        return None

    job = response.get("data")
    if job is None:
        return None

    if not isinstance(job, dict):
        raise RuntimeError("Worker job response must contain an object under 'data'")

    validate_payload(VIDEO_CONTRACT, "job", job)

    return job


def build_scoring_metrics(pose_metrics: dict[str, Any]) -> dict[str, float | int]:
    return {
        "trunk_angle": float(pose_metrics["max_trunk_angle"]),
        "neck_angle": float(pose_metrics["neck_angle"]),
        "upper_arm_angle": float(pose_metrics["upper_arm_angle"]),
        "lower_arm_angle": float(pose_metrics["lower_arm_angle"]),
        "wrist_angle": float(pose_metrics["wrist_angle"]),
        "leg_score": 1,
        "shoulder_elevation_duration": float(pose_metrics["shoulder_elevation_duration"]),
        "repetition_count": int(pose_metrics["repetition_count"]),
        "processing_confidence": float(pose_metrics["processing_confidence"]),
    }


def process_scan_job(job: dict[str, Any]) -> None:
    scan_id = int(job["scan_id"])
    organization_id = int(job["organization_id"])
    video_path = str(job["video_path"])
    model = str(job.get("model", "reba")).lower()
    multi_person_policy = str(os.getenv("VIDEO_MULTI_PERSON_POLICY", "dominant_subject")).strip().lower()

    if model == "niosh":
        raise ValueError("NIOSH model does not support video scans")

    frame_extractor = _frame_extractor_module()
    pose_detector = _pose_detector_module()

    frame_extractor.sample_frame_stats(video_path=video_path, sample_every_n=4)
    pose_metrics = pose_detector.estimate_pose_metrics(
        video_path=video_path,
        generate_visualization=True,
        blur_faces=True,
        multi_person_policy=multi_person_policy,
    )

    metrics_for_scoring = build_scoring_metrics(pose_metrics)

    payload: dict[str, Any] = {
        "scan_id": scan_id,
        "organization_id": organization_id,
        "model": model,
        "metrics": metrics_for_scoring,
    }
    pose_video_path = str(pose_metrics.get("pose_video_path", "")).strip()
    if pose_video_path.startswith("/storage/uploads/pose/") or pose_video_path.startswith("/storage/uploads/videos/"):
        payload["pose_video_path"] = pose_video_path

    validate_payload(VIDEO_CONTRACT, "complete", payload)

    _api_post(
        COMPLETE_ENDPOINT,
        payload,
    )


def mark_scan_invalid(scan_id: int, organization_id: int, error_message: str = "") -> None:
    payload = {
        "scan_id": int(scan_id),
        "organization_id": int(organization_id),
        "error_message": (error_message or "Processing failed").strip(),
    }
    validate_payload(VIDEO_CONTRACT, "fail", payload)
    _api_post(FAIL_ENDPOINT, payload)
