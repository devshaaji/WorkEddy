"""Pose estimation utilities for WorkEddy video processing.

Uses MediaPipe Pose Landmarker (Tasks API) to extract skeletal landmarks
from video frames, computing trunk flexion angles, neck angles, upper/lower
arm angles, wrist deviation, shoulder elevation metrics, and repetition
counts used by the risk scoring engine.

This module is the canonical implementation the video worker imports
from here rather than maintaining a duplicate.
"""

from __future__ import annotations

from math import degrees
from pathlib import Path

import cv2
import mediapipe as mp
import numpy as np

# â”€â”€ Landmark index constants â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
# Defined locally so we don't depend on the removed mp.solutions legacy API.
# Indices are identical to the old mp.solutions.pose.PoseLandmark enum.
class _PL:
    NOSE            = 0
    LEFT_EAR        = 7
    RIGHT_EAR       = 8
    LEFT_SHOULDER   = 11
    RIGHT_SHOULDER  = 12
    LEFT_ELBOW      = 13
    RIGHT_ELBOW     = 14
    LEFT_WRIST      = 15
    RIGHT_WRIST     = 16
    LEFT_HIP        = 23
    RIGHT_HIP       = 24


# â”€â”€ Locate the pose landmarker model â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
_MODEL_CANDIDATES = [
    Path("/opt/mediapipe/pose_landmarker_lite.task"),                                   # Docker (outside bind-mount)
    Path("/app/ml/models/pose_landmarker_lite.task"),                                 # Docker (fallback)
    Path(__file__).resolve().parents[2] / "ml" / "models" / "pose_landmarker_lite.task",  # repo root
    Path(__file__).resolve().parent / "pose_landmarker_lite.task",                   # same dir
]
_MODEL_PATH = next((str(p) for p in _MODEL_CANDIDATES if p.is_file()), None)
if _MODEL_PATH is None:
    raise RuntimeError(
        "Pose landmarker model not found. "
        "Expected at one of: " + str([str(p) for p in _MODEL_CANDIDATES])
    )

# â”€â”€ Landmarker options (built once at import time) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
_OPTIONS = mp.tasks.vision.PoseLandmarkerOptions(
    base_options=mp.tasks.BaseOptions(model_asset_path=_MODEL_PATH),
    running_mode=mp.tasks.vision.RunningMode.IMAGE,
    num_poses=1,
    min_pose_detection_confidence=0.5,
    min_pose_presence_confidence=0.5,
    min_tracking_confidence=0.5,
    output_segmentation_masks=False,
)


# â”€â”€ Geometry helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

def _angle_from_vertical(dx: float, dy: float) -> float:
    """Return the angle (degrees) between a body-segment vector and the vertical axis."""
    return abs(degrees(np.arctan2(dx, -dy)))


def _angle_between_points(a: tuple[float, float], b: tuple[float, float], c: tuple[float, float]) -> float:
    """Return the angle at point b formed by segments a-b and b-c (degrees)."""
    ba = (a[0] - b[0], a[1] - b[1])
    bc = (c[0] - b[0], c[1] - b[1])
    dot = ba[0] * bc[0] + ba[1] * bc[1]
    mag_ba = (ba[0] ** 2 + ba[1] ** 2) ** 0.5
    mag_bc = (bc[0] ** 2 + bc[1] ** 2) ** 0.5
    if mag_ba == 0 or mag_bc == 0:
        return 0.0
    cos_a = max(-1.0, min(1.0, dot / (mag_ba * mag_bc)))
    return abs(degrees(np.arccos(cos_a)))


def _midpoint(lm_a, lm_b) -> tuple[float, float]:
    """Return the (x, y) midpoint of two landmarks."""
    return ((lm_a.x + lm_b.x) / 2.0, (lm_a.y + lm_b.y) / 2.0)


def _pt(lm) -> tuple[float, float]:
    """Extract (x, y) from a landmark."""
    return (lm.x, lm.y)


def _vis(lm) -> float:
    """Safely extract landmark visibility (Tasks API field is Optional)."""
    return float(getattr(lm, "visibility", 1.0) or 1.0)


# â”€â”€ Main entry point â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

def estimate_pose_metrics(
    video_path: str,
    sample_every_n: int | None = None,
    target_fps: float = 10.0,
) -> dict[str, float | int]:
    """
    Process a video and return comprehensive ergonomic pose metrics.

    Parameters
    ----------
    video_path:
        Absolute path to the video file.
    sample_every_n:
        If provided, process every Nth frame (legacy mode).
        If None, sampling is calculated from the video's native FPS and ``target_fps``.
    target_fps:
        Desired analysis frame rate. Only used when ``sample_every_n`` is None.

    Returns
    -------
    dict with keys:
        max_trunk_angle, avg_trunk_angle, neck_angle, upper_arm_angle,
        lower_arm_angle, wrist_angle, shoulder_elevation_duration,
        repetition_count, processing_confidence
    """
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise RuntimeError(f"Cannot open video: {video_path}")

    # Determine frame-skip interval
    if sample_every_n is not None:
        skip = sample_every_n
    else:
        native_fps = cap.get(cv2.CAP_PROP_FPS) or 30.0
        skip = max(1, round(native_fps / target_fps))

    trunk_angles: list[float]     = []
    neck_angles: list[float]      = []
    upper_arm_angles: list[float] = []
    lower_arm_angles: list[float] = []
    wrist_angles: list[float]     = []
    shoulder_elevated_frames      = 0
    sampled_frames                = 0
    confidence_total              = 0.0
    confidence_count              = 0
    frame_idx                     = 0

    with mp.tasks.vision.PoseLandmarker.create_from_options(_OPTIONS) as landmarker:
        try:
            while True:
                ok, frame = cap.read()
                if not ok:
                    break

                frame_idx += 1
                if frame_idx % skip != 0:
                    continue

                sampled_frames += 1
                rgb      = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb)
                result   = landmarker.detect(mp_image)

                if not result.pose_landmarks:
                    continue

                lms = result.pose_landmarks[0]   # list[NormalizedLandmark]

                # â”€â”€ Landmarks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                ls   = lms[_PL.LEFT_SHOULDER]
                rs   = lms[_PL.RIGHT_SHOULDER]
                lh   = lms[_PL.LEFT_HIP]
                rh   = lms[_PL.RIGHT_HIP]
                le   = lms[_PL.LEFT_ELBOW]
                re   = lms[_PL.RIGHT_ELBOW]
                lw   = lms[_PL.LEFT_WRIST]
                rw   = lms[_PL.RIGHT_WRIST]
                nose = lms[_PL.NOSE]

                mid_shoulder = _midpoint(ls, rs)
                mid_hip      = _midpoint(lh, rh)

                # â”€â”€ Trunk flexion (torso vs vertical) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                trunk_angle = _angle_from_vertical(
                    mid_shoulder[0] - mid_hip[0],
                    mid_shoulder[1] - mid_hip[1],
                )
                trunk_angles.append(float(trunk_angle))

                # â”€â”€ Neck flexion â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                neck_angle  = _angle_between_points(
                    (nose.x, nose.y), mid_shoulder, mid_hip,
                )
                neck_flexion = abs(180.0 - neck_angle) if neck_angle > 90 else neck_angle
                neck_angles.append(float(neck_flexion))

                # â”€â”€ Upper arm elevation (shoulder â†’ elbow vs vertical) â”€â”€â”€
                l_upper = _angle_from_vertical(le.x - ls.x, le.y - ls.y)
                r_upper = _angle_from_vertical(re.x - rs.x, re.y - rs.y)
                upper_arm_angles.append(float((l_upper + r_upper) / 2.0))

                # â”€â”€ Lower arm (elbow angle: shoulderâ€“elbowâ€“wrist) â”€â”€â”€â”€â”€â”€â”€â”€
                l_lower = _angle_between_points(_pt(ls), _pt(le), _pt(lw))
                r_lower = _angle_between_points(_pt(rs), _pt(re), _pt(rw))
                lower_arm_angles.append(float((l_lower + r_lower) / 2.0))

                # â”€â”€ Wrist deviation (elbowâ€“wrist vs vertical) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                l_wrist  = _angle_from_vertical(lw.x - le.x, lw.y - le.y)
                r_wrist  = _angle_from_vertical(rw.x - re.x, rw.y - re.y)
                wrist_dev = abs(float((l_wrist + r_wrist) / 2.0) - 90.0)
                wrist_angles.append(wrist_dev)

                # Shoulder elevation heuristic: normalised y < 0.35 â†’ hands above shoulder
                if mid_shoulder[1] < 0.35:
                    shoulder_elevated_frames += 1

                vis = (_vis(ls) + _vis(rs) + _vis(lh) + _vis(rh)
                       + _vis(le) + _vis(re) + _vis(lw) + _vis(rw)) / 8.0
                confidence_total += vis
                confidence_count += 1

        finally:
            cap.release()

    if not trunk_angles:
        raise RuntimeError("No pose landmarks detected in sampled frames")

    avg_trunk_angle             = float(np.mean(trunk_angles))
    max_trunk_angle             = float(np.max(trunk_angles))
    shoulder_elevation_duration = shoulder_elevated_frames / max(1, sampled_frames)

    # Count repetitions: each transition from â‰¥30Â° to <30Â° is one cycle
    repetition_count = 0
    prev_high        = False
    for a in trunk_angles:
        high = a >= 30.0
        if high and not prev_high:
            repetition_count += 1
        prev_high = high

    processing_confidence = confidence_total / max(1, confidence_count)

    return {
        "max_trunk_angle":             round(max_trunk_angle, 2),
        "avg_trunk_angle":             round(avg_trunk_angle, 2),
        "neck_angle":                  round(float(np.mean(neck_angles)), 2)      if neck_angles      else 10.0,
        "upper_arm_angle":             round(float(np.mean(upper_arm_angles)), 2) if upper_arm_angles else 20.0,
        "lower_arm_angle":             round(float(np.mean(lower_arm_angles)), 2) if lower_arm_angles else 80.0,
        "wrist_angle":                 round(float(np.mean(wrist_angles)), 2)     if wrist_angles     else 0.0,
        "shoulder_elevation_duration": round(float(shoulder_elevation_duration), 4),
        "repetition_count":            repetition_count,
        "processing_confidence":       round(processing_confidence, 4),
    }
