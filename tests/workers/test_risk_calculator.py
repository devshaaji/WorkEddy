from __future__ import annotations

import importlib.util
import json
from pathlib import Path
from typing import Any

import pytest


MODULE_PATH = Path(__file__).resolve().parents[2] / "workers" / "video-worker" / "worker.py"
spec = importlib.util.spec_from_file_location("video_worker", MODULE_PATH)
assert spec and spec.loader
video_worker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(video_worker)


class _DummyResponse:
    def __init__(self, payload: str = "{}") -> None:
        self._payload = payload.encode("utf-8")

    def read(self) -> bytes:
        return self._payload

    def __enter__(self) -> "_DummyResponse":
        return self

    def __exit__(self, exc_type, exc, tb) -> None:
        return None


def test_build_scoring_metrics_maps_pose_fields() -> None:
    pose_metrics: dict[str, Any] = {
        "max_trunk_angle": 34.5,
        "neck_angle": 12.1,
        "upper_arm_angle": 48.0,
        "lower_arm_angle": 92.2,
        "wrist_angle": 14.0,
        "shoulder_elevation_duration": 0.28,
        "repetition_count": 17,
        "processing_confidence": 0.93,
    }

    mapped = video_worker.build_scoring_metrics(pose_metrics)

    assert mapped == {
        "trunk_angle": 34.5,
        "neck_angle": 12.1,
        "upper_arm_angle": 48.0,
        "lower_arm_angle": 92.2,
        "wrist_angle": 14.0,
        "leg_score": 1,
        "shoulder_elevation_duration": 0.28,
        "repetition_count": 17,
        "processing_confidence": 0.93,
    }


def test_mark_scan_invalid_posts_to_worker_api(monkeypatch: pytest.MonkeyPatch) -> None:
    captured: dict[str, Any] = {}

    def fake_urlopen(request, timeout: float):
        captured["url"] = request.full_url
        captured["timeout"] = timeout
        captured["headers"] = {k.lower(): v for k, v in request.header_items()}
        captured["payload"] = json.loads(request.data.decode("utf-8"))
        return _DummyResponse('{"ok":true}')

    monkeypatch.setenv("WORKER_API_TOKEN", "test-worker-token")
    monkeypatch.setattr(video_worker, "API_BASE_URL", "http://api.internal")
    monkeypatch.setattr(video_worker.urllib.request, "urlopen", fake_urlopen)

    video_worker.mark_scan_invalid(42, 7, "pose extraction failed")

    assert captured["url"] == "http://api.internal/api/v1/internal/worker/scans/fail"
    assert captured["headers"]["x-worker-token"] == "test-worker-token"
    assert captured["payload"] == {
        "scan_id": 42,
        "organization_id": 7,
        "error_message": "pose extraction failed",
    }


def test_process_scan_job_posts_metrics_for_php_scoring(monkeypatch: pytest.MonkeyPatch) -> None:
    captured: dict[str, Any] = {}
    detector_kwargs: dict[str, Any] = {}

    class _FrameExtractor:
        @staticmethod
        def sample_frame_stats(**kwargs):
            return None

    class _PoseDetector:
        @staticmethod
        def estimate_pose_metrics(**kwargs):
            detector_kwargs.update(kwargs)
            return {
                "max_trunk_angle": 20.0,
                "neck_angle": 8.0,
                "upper_arm_angle": 35.0,
                "lower_arm_angle": 85.0,
                "wrist_angle": 10.0,
                "shoulder_elevation_duration": 0.15,
                "repetition_count": 12,
                "processing_confidence": 0.88,
                "pose_video_path": "/storage/uploads/videos/sample.pose.mp4",
            }

    monkeypatch.setattr(video_worker, "_frame_extractor_module", lambda: _FrameExtractor())
    monkeypatch.setattr(video_worker, "_pose_detector_module", lambda: _PoseDetector())
    monkeypatch.setenv("VIDEO_MULTI_PERSON_POLICY", "reject")

    def fake_post(endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
        captured["endpoint"] = endpoint
        captured["payload"] = payload
        return {"ok": True}

    monkeypatch.setattr(video_worker, "_api_post", fake_post)

    video_worker.process_scan_job(
        {
            "scan_id": 101,
            "organization_id": 3,
            "video_path": "/storage/uploads/videos/sample.mp4",
            "model": "reba",
        }
    )

    assert captured["endpoint"] == "/api/v1/internal/worker/scans/complete"
    assert captured["payload"]["scan_id"] == 101
    assert captured["payload"]["organization_id"] == 3
    assert captured["payload"]["model"] == "reba"
    assert captured["payload"]["metrics"]["trunk_angle"] == 20.0
    assert captured["payload"]["pose_video_path"] == "/storage/uploads/videos/sample.pose.mp4"
    assert detector_kwargs["multi_person_policy"] == "reject"


def test_process_scan_job_rejects_niosh_video() -> None:
    with pytest.raises(ValueError, match="does not support video scans"):
        video_worker.process_scan_job(
            {
                "scan_id": 10,
                "organization_id": 1,
                "video_path": "/storage/uploads/videos/sample.mp4",
                "model": "niosh",
            }
        )

def test_fetch_next_job_returns_payload(monkeypatch: pytest.MonkeyPatch) -> None:
    def fake_request(endpoint: str, **kwargs):
        assert endpoint == "/api/v1/internal/worker/jobs/next"
        return {
            "data": {
                "scan_id": 55,
                "organization_id": 9,
                "video_path": "/storage/uploads/videos/queued.mp4",
                "model": "rula",
            }
        }

    monkeypatch.setattr(video_worker, "_api_request", fake_request)

    job = video_worker.fetch_next_job()

    assert job is not None
    assert job["scan_id"] == 55
    assert job["model"] == "rula"


def test_fetch_next_job_returns_none_when_queue_empty(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(video_worker, "_api_request", lambda endpoint, **kwargs: None)
    assert video_worker.fetch_next_job() is None
