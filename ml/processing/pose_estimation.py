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


_POSE_CONNECTIONS: tuple[tuple[int, int], ...] = (
    (_PL.LEFT_SHOULDER, _PL.RIGHT_SHOULDER),
    (_PL.LEFT_HIP, _PL.RIGHT_HIP),
    (_PL.LEFT_SHOULDER, _PL.LEFT_HIP),
    (_PL.RIGHT_SHOULDER, _PL.RIGHT_HIP),
    (_PL.LEFT_SHOULDER, _PL.LEFT_ELBOW),
    (_PL.LEFT_ELBOW, _PL.LEFT_WRIST),
    (_PL.RIGHT_SHOULDER, _PL.RIGHT_ELBOW),
    (_PL.RIGHT_ELBOW, _PL.RIGHT_WRIST),
)


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

_OPTIONS = mp.tasks.vision.PoseLandmarkerOptions(
    base_options=mp.tasks.BaseOptions(model_asset_path=_MODEL_PATH),
    running_mode=mp.tasks.vision.RunningMode.IMAGE,
    # Enable multi-person detection so caller policy can reject or auto-select.
    num_poses=4,
    min_pose_detection_confidence=0.5,
    min_pose_presence_confidence=0.5,
    min_tracking_confidence=0.5,
    output_segmentation_masks=False,
)


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


def _dominant_pose_index(pose_landmarks: list[list]) -> int:
    """Pick dominant subject index using torso area * landmark visibility."""
    best_idx = 0
    best_score = -1.0

    for idx, lms in enumerate(pose_landmarks):
        if len(lms) <= _PL.RIGHT_HIP:
            continue

        ls = lms[_PL.LEFT_SHOULDER]
        rs = lms[_PL.RIGHT_SHOULDER]
        lh = lms[_PL.LEFT_HIP]
        rh = lms[_PL.RIGHT_HIP]

        shoulder_width = max(0.0, abs(float(ls.x) - float(rs.x)))
        torso_height = max(0.0, abs(float(((ls.y + rs.y) / 2.0) - ((lh.y + rh.y) / 2.0))))
        area = shoulder_width * torso_height

        visibility = (_vis(ls) + _vis(rs) + _vis(lh) + _vis(rh)) / 4.0
        score = area * visibility

        if score > best_score:
            best_score = score
            best_idx = idx

    return best_idx


def _default_pose_video_path(video_path: str) -> str:
    src = Path(video_path)
    stem = src.stem or "scan"
    return str(src.with_name(f"{stem}.pose.mp4"))


def _draw_pose_overlay(frame: np.ndarray, lms, status: str = "") -> None:
    h, w = frame.shape[:2]

    for a, b in _POSE_CONNECTIONS:
        if a >= len(lms) or b >= len(lms):
            continue
        p1 = lms[a]
        p2 = lms[b]
        x1, y1 = int(p1.x * w), int(p1.y * h)
        x2, y2 = int(p2.x * w), int(p2.y * h)
        cv2.line(frame, (x1, y1), (x2, y2), (0, 255, 0), 2)

    tracked_points = {
        _PL.NOSE,
        _PL.LEFT_SHOULDER, _PL.RIGHT_SHOULDER,
        _PL.LEFT_ELBOW, _PL.RIGHT_ELBOW,
        _PL.LEFT_WRIST, _PL.RIGHT_WRIST,
        _PL.LEFT_HIP, _PL.RIGHT_HIP,
    }
    for idx in tracked_points:
        if idx >= len(lms):
            continue
        p = lms[idx]
        x, y = int(p.x * w), int(p.y * h)
        cv2.circle(frame, (x, y), 4, (0, 200, 255), -1)

    if status:
        cv2.putText(
            frame,
            status,
            (16, 30),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.7,
            (255, 255, 255),
            2,
            cv2.LINE_AA,
        )

def estimate_pose_metrics(
    video_path: str,
    sample_every_n: int | None = None,
    target_fps: float = 10.0,
    generate_visualization: bool = False,
    output_video_path: str | None = None,
    multi_person_policy: str = "dominant_subject",
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

    policy = multi_person_policy.strip().lower()
    if policy not in {"dominant_subject", "reject"}:
        raise ValueError("multi_person_policy must be 'dominant_subject' or 'reject'")

    # Determine frame-skip interval
    native_fps = cap.get(cv2.CAP_PROP_FPS) or 30.0
    if sample_every_n is not None:
        skip = sample_every_n
    else:
        skip = max(1, round(native_fps / target_fps))

    resolved_output_video_path: str | None = None
    if generate_visualization:
        resolved_output_video_path = output_video_path or _default_pose_video_path(video_path)
        output_dir = Path(resolved_output_video_path).parent
        output_dir.mkdir(parents=True, exist_ok=True)

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
    multi_person_detected_frames  = 0
    max_persons_detected          = 0
    writer: cv2.VideoWriter | None = None

    with mp.tasks.vision.PoseLandmarker.create_from_options(_OPTIONS) as landmarker:
        try:
            while True:
                ok, frame = cap.read()
                if not ok:
                    break

                if generate_visualization and writer is None and resolved_output_video_path is not None:
                    frame_h, frame_w = frame.shape[:2]
                    fps_for_output = native_fps if native_fps > 1.0 else max(1.0, target_fps)
                    fourcc = cv2.VideoWriter_fourcc(*"mp4v")
                    writer = cv2.VideoWriter(resolved_output_video_path, fourcc, fps_for_output, (frame_w, frame_h))
                    if not writer.isOpened():
                        raise RuntimeError(f"Cannot create pose visualization video: {resolved_output_video_path}")

                frame_idx += 1
                if frame_idx % skip != 0:
                    if writer is not None:
                        writer.write(frame)
                    continue

                sampled_frames += 1
                rgb      = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb)
                result   = landmarker.detect(mp_image)

                if not result.pose_landmarks:
                    if writer is not None:
                        _draw_pose_overlay(frame, [], f"Frame {frame_idx}: no pose detected")
                        writer.write(frame)
                    continue

                persons = result.pose_landmarks
                person_count = len(persons)
                max_persons_detected = max(max_persons_detected, person_count)

                chosen_idx = 0
                if person_count > 1:
                    multi_person_detected_frames += 1
                    if policy == "reject":
                        raise RuntimeError(
                            "Multiple people detected in video. "
                            "This scan is configured for single-person analysis."
                        )
                    chosen_idx = _dominant_pose_index(persons)

                lms = persons[chosen_idx]   # list[NormalizedLandmark]

                if writer is not None:
                    status = f"Frame {frame_idx}: analysed"
                    if person_count > 1:
                        status += f" ({person_count} persons, dominant #{chosen_idx + 1})"
                    _draw_pose_overlay(frame, lms, status)
                    writer.write(frame)

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

                trunk_angle = _angle_from_vertical(
                    mid_shoulder[0] - mid_hip[0],
                    mid_shoulder[1] - mid_hip[1],
                )
                trunk_angles.append(float(trunk_angle))

                neck_angle  = _angle_between_points(
                    (nose.x, nose.y), mid_shoulder, mid_hip,
                )
                neck_flexion = abs(180.0 - neck_angle) if neck_angle > 90 else neck_angle
                neck_angles.append(float(neck_flexion))

                l_upper = _angle_from_vertical(le.x - ls.x, le.y - ls.y)
                r_upper = _angle_from_vertical(re.x - rs.x, re.y - rs.y)
                upper_arm_angles.append(float((l_upper + r_upper) / 2.0))

                l_lower = _angle_between_points(_pt(ls), _pt(le), _pt(lw))
                r_lower = _angle_between_points(_pt(rs), _pt(re), _pt(rw))
                lower_arm_angles.append(float((l_lower + r_lower) / 2.0))

                l_wrist  = _angle_from_vertical(lw.x - le.x, lw.y - le.y)
                r_wrist  = _angle_from_vertical(rw.x - re.x, rw.y - re.y)
                wrist_dev = abs(float((l_wrist + r_wrist) / 2.0) - 90.0)
                wrist_angles.append(wrist_dev)

                # Shoulder elevation heuristic: normalised y < 0.35 hands above shoulder
                if mid_shoulder[1] < 0.35:
                    shoulder_elevated_frames += 1

                vis = (_vis(ls) + _vis(rs) + _vis(lh) + _vis(rh)
                       + _vis(le) + _vis(re) + _vis(lw) + _vis(rw)) / 8.0
                confidence_total += vis
                confidence_count += 1

        finally:
            cap.release()
            if writer is not None:
                writer.release()

    if not trunk_angles:
        raise RuntimeError("No pose landmarks detected in sampled frames")

    avg_trunk_angle             = float(np.mean(trunk_angles))
    max_trunk_angle             = float(np.max(trunk_angles))
    shoulder_elevation_duration = shoulder_elevated_frames / max(1, sampled_frames)

    # Count repetitions: each transition from
    repetition_count = 0
    prev_high        = False
    for a in trunk_angles:
        high = a >= 30.0
        if high and not prev_high:
            repetition_count += 1
        prev_high = high

    processing_confidence = confidence_total / max(1, confidence_count)

    metrics = {
        "max_trunk_angle":             round(max_trunk_angle, 2),
        "avg_trunk_angle":             round(avg_trunk_angle, 2),
        "neck_angle":                  round(float(np.mean(neck_angles)), 2)      if neck_angles      else 10.0,
        "upper_arm_angle":             round(float(np.mean(upper_arm_angles)), 2) if upper_arm_angles else 20.0,
        "lower_arm_angle":             round(float(np.mean(lower_arm_angles)), 2) if lower_arm_angles else 80.0,
        "wrist_angle":                 round(float(np.mean(wrist_angles)), 2)     if wrist_angles     else 0.0,
        "shoulder_elevation_duration": round(float(shoulder_elevation_duration), 4),
        "repetition_count":            repetition_count,
        "processing_confidence":       round(processing_confidence, 4),
        "multi_person_detected_frames": int(multi_person_detected_frames),
        "max_persons_detected": int(max_persons_detected),
        "multi_person_policy": policy,
    }

    if generate_visualization and resolved_output_video_path is not None:
        metrics["pose_video_path"] = resolved_output_video_path

    return metrics
