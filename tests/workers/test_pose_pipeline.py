from __future__ import annotations

import json
import os
from pathlib import Path

import pytest

from pose_pipeline_utils import assert_metrics_close, metrics_from_pose_landmarks

ROOT = Path(__file__).resolve().parents[2]
POSE_FIXTURES = sorted((ROOT / "tests" / "postures" / "pose").glob("*.json"))


@pytest.mark.parametrize("fixture_path", POSE_FIXTURES, ids=lambda p: p.stem)
def test_pose_landmark_pipeline_to_metrics(fixture_path: Path) -> None:
    case = json.loads(fixture_path.read_text(encoding="utf-8"))

    metrics = metrics_from_pose_landmarks(case["landmarks"])

    if "expected_metrics" in case:
        assert_metrics_close(metrics, case["expected_metrics"])

    required_keys = {
        "trunk_angle",
        "neck_angle",
        "upper_arm_angle",
        "lower_arm_angle",
        "wrist_angle",
        "leg_score",
        "shoulder_elevation_duration",
        "repetition_count",
        "processing_confidence",
    }
    assert required_keys.issubset(metrics.keys())

    if os.getenv("ERGONOMICS_DEBUG_TRACE") == "1":
        print(f"\n[DEBUG] {case['name']}")
        print(f"  Model: {case['model']}")
        print(f"  Metrics: {metrics}")