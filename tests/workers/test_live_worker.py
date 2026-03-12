"""Tests for the live-worker pose engine abstraction and worker module."""

from __future__ import annotations

import importlib.util
import json
import sys
from pathlib import Path
from typing import Any
from unittest.mock import MagicMock, patch

import numpy as np
import pytest


LIVE_WORKER_DIR = Path(__file__).resolve().parents[2] / "workers" / "live-worker"

# Add live-worker dir to sys.path so we can import pose_engines
if str(LIVE_WORKER_DIR) not in sys.path:
    sys.path.insert(0, str(LIVE_WORKER_DIR))


# ── Load worker module ─────────────────────────────────────────────────
def _load_worker():
    spec = importlib.util.spec_from_file_location(
        "live_worker", LIVE_WORKER_DIR / "worker.py"
    )
    assert spec and spec.loader
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


# ── pose_engines tests ─────────────────────────────────────────────────

def test_available_engines_returns_both():
    from pose_engines import available_engines

    engines = available_engines()
    assert "mediapipe" in engines
    assert "yolo26" in engines


def test_create_engine_rejects_unknown():
    from pose_engines import create_engine

    with pytest.raises(ValueError, match="Unknown pose engine"):
        create_engine("unknown_engine")


def test_mediapipe_engine_has_correct_name():
    from pose_engines import MediaPipeEngine

    engine = MediaPipeEngine()
    assert engine.name() == "mediapipe"


def test_yolo26_engine_has_correct_name():
    from pose_engines import Yolo26Engine

    engine = Yolo26Engine()
    assert engine.name() == "yolo26"


def test_yolo26_engine_accepts_variant():
    from pose_engines import Yolo26Engine

    engine = Yolo26Engine(model_variant="yolo26s-pose")
    assert engine.name() == "yolo26"
    assert engine._variant == "yolo26s-pose"


def test_yolo26_engine_accepts_multi_person_flag():
    from pose_engines import Yolo26Engine

    engine = Yolo26Engine(model_variant="yolo26n-pose", multi_person_mode=True)
    assert engine._multi_person_mode is True


def test_mediapipe_engine_accepts_variant():
    from pose_engines import MediaPipeEngine

    engine = MediaPipeEngine(model_variant="pose_landmarker_full")
    assert engine.name() == "mediapipe"
    assert engine._variant == "pose_landmarker_full"


def test_mediapipe_engine_rejects_multi_person_mode():
    from pose_engines import MediaPipeEngine

    engine = MediaPipeEngine(multi_person_mode=True)
    with pytest.raises(RuntimeError, match="single-person only"):
        engine.init()


# ── Geometry helpers ───────────────────────────────────────────────────

def test_angle_from_vertical_straight_up():
    from pose_engines import _angle_from_vertical

    # Straight up: dx=0, dy=-1 → angle 0°
    angle = _angle_from_vertical(0.0, -1.0)
    assert abs(angle) < 0.1


def test_angle_from_vertical_horizontal():
    from pose_engines import _angle_from_vertical

    # Horizontal: dx=1, dy=0 → angle 90°
    angle = _angle_from_vertical(1.0, 0.0)
    assert abs(angle - 90.0) < 0.1


def test_angle_between_right_angle():
    from pose_engines import _angle_between

    angle = _angle_between((0, 0), (1, 0), (1, 1))
    assert abs(angle - 90.0) < 0.1


# ── Worker module tests ───────────────────────────────────────────────

class _DummyResponse:
    def __init__(self, payload: str = "{}"):
        self._payload = payload.encode("utf-8")
    def read(self) -> bytes:
        return self._payload
    def __enter__(self):
        return self
    def __exit__(self, *args):
        return None


def test_report_frames_posts_to_live_worker_api(monkeypatch: pytest.MonkeyPatch):
    live_worker = _load_worker()
    captured: dict[str, Any] = {}

    def fake_post(endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
        captured["endpoint"] = endpoint
        captured["payload"] = payload
        return {"ok": True}

    monkeypatch.setattr(live_worker, "_api_post", fake_post)

    live_worker.report_frames(
        session_id=10,
        organization_id=3,
        model="reba",
        frames=[
            {"frame_number": 1, "metrics": {"trunk_angle": 15.0}, "latency_ms": 30.0},
            {"frame_number": 2, "metrics": {"trunk_angle": 20.0}, "latency_ms": 35.0},
        ],
    )

    assert captured["endpoint"] == "/api/v1/internal/live-worker/frames"
    assert captured["payload"]["session_id"] == 10
    assert len(captured["payload"]["frames"]) == 2


def test_fail_session_posts_error(monkeypatch: pytest.MonkeyPatch):
    live_worker = _load_worker()
    captured: dict[str, Any] = {}

    def fake_post(endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
        captured["endpoint"] = endpoint
        captured["payload"] = payload
        return {"ok": True}

    monkeypatch.setattr(live_worker, "_api_post", fake_post)

    live_worker.fail_session(10, 3, "engine crashed")

    assert captured["endpoint"] == "/api/v1/internal/live-worker/sessions/fail"
    assert captured["payload"]["error_message"] == "engine crashed"
    assert captured["payload"]["session_id"] == 10


def test_complete_session_posts_summary(monkeypatch: pytest.MonkeyPatch):
    live_worker = _load_worker()
    captured: dict[str, Any] = {}

    def fake_post(endpoint: str, payload: dict[str, Any]) -> dict[str, Any]:
        captured["endpoint"] = endpoint
        captured["payload"] = payload
        return {"ok": True}

    monkeypatch.setattr(live_worker, "_api_post", fake_post)

    live_worker.complete_session(10, 3, {"avg_trunk_angle": 18.5, "total_frames": 100})

    assert captured["endpoint"] == "/api/v1/internal/live-worker/sessions/complete"
    assert captured["payload"]["summary_metrics"]["avg_trunk_angle"] == 18.5


def test_process_frame_batch_respects_latency_budget(monkeypatch: pytest.MonkeyPatch):
    """When latency budget is exceeded, remaining frames should be skipped."""
    live_worker = _load_worker()

    call_count = 0

    class SlowEngine:
        def name(self):
            return "slow"
        def estimate(self, frame):
            nonlocal call_count
            call_count += 1
            import time
            time.sleep(0.05)  # 50ms per frame
            return {
                "trunk_angle": 10.0,
                "neck_angle": 5.0,
                "upper_arm_angle": 20.0,
                "lower_arm_angle": 80.0,
                "wrist_angle": 5.0,
                "confidence": 0.9,
                "latency_ms": 50.0,
            }

    # Inject the slow engine through the manager abstraction
    class FakeManager:
        def get_engine(self, engine_name, config=None):
            return SlowEngine()

    monkeypatch.setattr(live_worker, "_engine_manager", FakeManager())

    # Mock report_frames to avoid HTTP calls
    monkeypatch.setattr(live_worker, "report_frames", lambda *a, **kw: None)

    frames = [np.zeros((100, 100, 3), dtype=np.uint8) for _ in range(20)]

    result = live_worker.process_frame_batch(
        engine_name="slow",
        frames_bgr=frames,
        session_id=1,
        organization_id=1,
        model="reba",
        model_variant="slow-v1",
        multi_person_mode=True,
        max_e2e_latency_ms=100,  # Only ~2 frames will fit in 100ms
    )

    # Should have skipped some frames
    assert result["skipped"] > 0
    assert result["processed"] + result["skipped"] <= 20


def test_process_frame_batch_applies_confidence_filter_and_smoothing(monkeypatch: pytest.MonkeyPatch):
    live_worker = _load_worker()
    live_worker._session_states.clear()

    class StableEngine:
        def __init__(self):
            self._idx = 0

        def estimate(self, frame):
            seq = [
                # accepted
                {
                    "trunk_angle": 10.0,
                    "neck_angle": 5.0,
                    "upper_arm_angle": 20.0,
                    "lower_arm_angle": 80.0,
                    "wrist_angle": 5.0,
                    "confidence": 0.9,
                    "latency_ms": 10.0,
                    "subject_center_x": 0.50,
                    "subject_center_y": 0.50,
                },
                # filtered out (low confidence)
                {
                    "trunk_angle": 40.0,
                    "neck_angle": 15.0,
                    "upper_arm_angle": 40.0,
                    "lower_arm_angle": 90.0,
                    "wrist_angle": 20.0,
                    "confidence": 0.2,
                    "latency_ms": 10.0,
                    "subject_center_x": 0.51,
                    "subject_center_y": 0.50,
                },
                # accepted and smoothed with frame 1
                {
                    "trunk_angle": 30.0,
                    "neck_angle": 9.0,
                    "upper_arm_angle": 30.0,
                    "lower_arm_angle": 85.0,
                    "wrist_angle": 10.0,
                    "confidence": 0.95,
                    "latency_ms": 10.0,
                    "subject_center_x": 0.52,
                    "subject_center_y": 0.50,
                },
            ]
            item = seq[min(self._idx, len(seq) - 1)]
            self._idx += 1
            return dict(item)

    class FakeManager:
        def get_engine(self, engine_name, config=None):
            return StableEngine()

    captured_frames: list[dict[str, Any]] = []

    def fake_report_frames(session_id, organization_id, model, frames):
        captured_frames.extend(frames)
        return {"ok": True}

    monkeypatch.setattr(live_worker, "_engine_manager", FakeManager())
    monkeypatch.setattr(live_worker, "report_frames", fake_report_frames)

    frames = [np.zeros((50, 50, 3), dtype=np.uint8) for _ in range(3)]

    result = live_worker.process_frame_batch(
        engine_name="yolo26",
        frames_bgr=frames,
        session_id=99,
        organization_id=1,
        model="reba",
        smoothing_alpha=0.5,
        min_joint_confidence=0.45,
        tracking_max_distance=0.2,
    )

    # one frame dropped by confidence filter
    assert result["processed"] == 2
    assert len(captured_frames) == 2

    # frame 1 remains unchanged; frame 3 trunk is smoothed: 0.5*30 + 0.5*10 = 20
    assert captured_frames[0]["metrics"]["trunk_angle"] == 10.0
    assert captured_frames[1]["metrics"]["trunk_angle"] == 20.0

    # same subject track id retained for nearby center points
    assert captured_frames[0]["metrics"]["subject_track_id"] == 1
    assert captured_frames[1]["metrics"]["subject_track_id"] == 1
