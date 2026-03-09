from __future__ import annotations

import math
from typing import Any

_IDX = {
    "NOSE": 0,
    "LEFT_SHOULDER": 11,
    "RIGHT_SHOULDER": 12,
    "LEFT_ELBOW": 13,
    "RIGHT_ELBOW": 14,
    "LEFT_WRIST": 15,
    "RIGHT_WRIST": 16,
    "LEFT_HIP": 23,
    "RIGHT_HIP": 24,
}


def _angle_from_vertical(dx: float, dy: float) -> float:
    return abs(math.degrees(math.atan2(dx, -dy)))


def _angle_between_points(a: tuple[float, float], b: tuple[float, float], c: tuple[float, float]) -> float:
    ba = (a[0] - b[0], a[1] - b[1])
    bc = (c[0] - b[0], c[1] - b[1])
    dot = ba[0] * bc[0] + ba[1] * bc[1]
    mag_ba = (ba[0] ** 2 + ba[1] ** 2) ** 0.5
    mag_bc = (bc[0] ** 2 + bc[1] ** 2) ** 0.5
    if mag_ba == 0 or mag_bc == 0:
        return 0.0
    cos_a = max(-1.0, min(1.0, dot / (mag_ba * mag_bc)))
    return abs(math.degrees(math.acos(cos_a)))


def _midpoint(a: dict[str, float], b: dict[str, float]) -> tuple[float, float]:
    return ((a["x"] + b["x"]) / 2.0, (a["y"] + b["y"]) / 2.0)


def _pt(a: dict[str, float]) -> tuple[float, float]:
    return (a["x"], a["y"])


def metrics_from_pose_landmarks(landmarks: list[dict[str, float]]) -> dict[str, float | int]:
    ls = landmarks[_IDX["LEFT_SHOULDER"]]
    rs = landmarks[_IDX["RIGHT_SHOULDER"]]
    lh = landmarks[_IDX["LEFT_HIP"]]
    rh = landmarks[_IDX["RIGHT_HIP"]]
    le = landmarks[_IDX["LEFT_ELBOW"]]
    re = landmarks[_IDX["RIGHT_ELBOW"]]
    lw = landmarks[_IDX["LEFT_WRIST"]]
    rw = landmarks[_IDX["RIGHT_WRIST"]]
    nose = landmarks[_IDX["NOSE"]]

    mid_shoulder = _midpoint(ls, rs)
    mid_hip = _midpoint(lh, rh)

    trunk_angle = _angle_from_vertical(mid_shoulder[0] - mid_hip[0], mid_shoulder[1] - mid_hip[1])

    neck_raw = _angle_between_points((nose["x"], nose["y"]), mid_shoulder, mid_hip)
    neck_flexion = abs(180.0 - neck_raw) if neck_raw > 90 else neck_raw

    l_upper = _angle_from_vertical(le["x"] - ls["x"], le["y"] - ls["y"])
    r_upper = _angle_from_vertical(re["x"] - rs["x"], re["y"] - rs["y"])
    upper_arm_angle = (l_upper + r_upper) / 2.0

    l_lower = _angle_between_points(_pt(ls), _pt(le), _pt(lw))
    r_lower = _angle_between_points(_pt(rs), _pt(re), _pt(rw))
    lower_arm_angle = (l_lower + r_lower) / 2.0

    l_wrist = _angle_from_vertical(lw["x"] - le["x"], lw["y"] - le["y"])
    r_wrist = _angle_from_vertical(rw["x"] - re["x"], rw["y"] - re["y"])
    wrist_angle = abs(((l_wrist + r_wrist) / 2.0) - 90.0)

    return {
        "trunk_angle": round(trunk_angle, 2),
        "neck_angle": round(neck_flexion, 2),
        "upper_arm_angle": round(upper_arm_angle, 2),
        "lower_arm_angle": round(lower_arm_angle, 2),
        "wrist_angle": round(wrist_angle, 2),
        "leg_score": 1,
        "shoulder_elevation_duration": 0.0,
        "repetition_count": 1,
        "processing_confidence": 1.0,
    }


def assert_metrics_close(actual: dict[str, Any], expected: dict[str, Any], tolerance: float = 0.15) -> None:
    for key, exp in expected.items():
        assert key in actual, f"Missing metric key: {key}"
        act = actual[key]
        if isinstance(exp, float):
            assert abs(float(act) - exp) <= tolerance, (
                f"Metric '{key}' mismatch. expected={exp}, actual={act}, tolerance={tolerance}"
            )
        else:
            assert act == exp, f"Metric '{key}' mismatch. expected={exp}, actual={act}"
