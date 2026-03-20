"""Pose estimation utilities for WorkEddy video processing.

This module uses MediaPipe Pose Landmarker (Tasks API) to extract ergonomic
posture metrics from uploaded videos. It is designed for single-person analysis
but can optionally auto-select the dominant subject when multiple people appear.

Improvements in this version:
- Uses MediaPipe VIDEO mode with timestamped inference.
- Supports optional face blurring for privacy using pose landmarks.
- Blurs faces on every written frame, including skipped frames.
- Adds temporal smoothing to reduce noisy angles and false spikes.
- Improves dominant-subject selection for multi-person scenes.
- Produces browser-friendly MP4 output via ffmpeg when available.
- Uses hysteresis for repetition counting to reduce double-counting.

For privacy, call it with:
estimate_pose_metrics(
    video_path=video_path,
    generate_visualization=True,
    blur_faces=True,
    blur_only_selected_subject=False,
)
"""

from __future__ import annotations

from math import degrees, hypot
from pathlib import Path
import shutil
import subprocess
from typing import Iterable

import cv2
import mediapipe as mp
import numpy as np


class _PL:
    NOSE = 0
    LEFT_EYE_INNER = 1
    LEFT_EYE = 2
    LEFT_EYE_OUTER = 3
    RIGHT_EYE_INNER = 4
    RIGHT_EYE = 5
    RIGHT_EYE_OUTER = 6
    LEFT_EAR = 7
    RIGHT_EAR = 8
    LEFT_SHOULDER = 11
    RIGHT_SHOULDER = 12
    LEFT_ELBOW = 13
    RIGHT_ELBOW = 14
    LEFT_WRIST = 15
    RIGHT_WRIST = 16
    LEFT_HIP = 23
    RIGHT_HIP = 24


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
    Path("/opt/mediapipe/pose_landmarker_lite.task"),
    Path("/app/ml/models/pose_landmarker_lite.task"),
    Path(__file__).resolve().parents[2] / "ml" / "models" / "pose_landmarker_lite.task",
    Path(__file__).resolve().parent / "pose_landmarker_lite.task",
]

_MODEL_PATH = next((str(p) for p in _MODEL_CANDIDATES if p.is_file()), None)
if _MODEL_PATH is None:
    raise RuntimeError(
        "Pose landmarker model not found. Expected one of: "
        + ", ".join(str(p) for p in _MODEL_CANDIDATES)
    )

BaseOptions = mp.tasks.BaseOptions
PoseLandmarker = mp.tasks.vision.PoseLandmarker
PoseLandmarkerOptions = mp.tasks.vision.PoseLandmarkerOptions
RunningMode = mp.tasks.vision.RunningMode

_OPTIONS = PoseLandmarkerOptions(
    base_options=BaseOptions(model_asset_path=_MODEL_PATH),
    running_mode=RunningMode.VIDEO,
    num_poses=4,
    min_pose_detection_confidence=0.5,
    min_pose_presence_confidence=0.5,
    min_tracking_confidence=0.5,
    output_segmentation_masks=False,
)


def _clamp(value: float, low: float, high: float) -> float:
    return max(low, min(high, value))


def _safe_mean(values: list[float], default: float = 0.0) -> float:
    return float(np.mean(values)) if values else float(default)


def _ema_append(series: list[float], value: float, alpha: float = 0.35) -> float:
    if not series:
        smoothed = float(value)
    else:
        smoothed = float(alpha * value + (1.0 - alpha) * series[-1])
    series.append(smoothed)
    return smoothed


def _angle_from_vertical(dx: float, dy: float) -> float:
    return abs(degrees(np.arctan2(dx, -dy)))


def _angle_between_points(
    a: tuple[float, float],
    b: tuple[float, float],
    c: tuple[float, float],
) -> float:
    ba = (a[0] - b[0], a[1] - b[1])
    bc = (c[0] - b[0], c[1] - b[1])
    dot = ba[0] * bc[0] + ba[1] * bc[1]
    mag_ba = hypot(ba[0], ba[1])
    mag_bc = hypot(bc[0], bc[1])
    if mag_ba == 0.0 or mag_bc == 0.0:
        return 0.0
    cos_a = _clamp(dot / (mag_ba * mag_bc), -1.0, 1.0)
    return abs(degrees(np.arccos(cos_a)))


def _midpoint(lm_a, lm_b) -> tuple[float, float]:
    return ((float(lm_a.x) + float(lm_b.x)) / 2.0, (float(lm_a.y) + float(lm_b.y)) / 2.0)


def _pt(lm) -> tuple[float, float]:
    return (float(lm.x), float(lm.y))


def _vis(lm) -> float:
    return float(getattr(lm, "visibility", 1.0) or 1.0)


def _dominant_pose_index(pose_landmarks: list[list], frame_w: int, frame_h: int) -> int:
    best_idx = 0
    best_score = -1.0

    frame_cx = frame_w / 2.0
    frame_cy = frame_h / 2.0
    max_dist = hypot(frame_cx, frame_cy) or 1.0

    for idx, lms in enumerate(pose_landmarks):
        if len(lms) <= _PL.RIGHT_HIP:
            continue

        ls = lms[_PL.LEFT_SHOULDER]
        rs = lms[_PL.RIGHT_SHOULDER]
        lh = lms[_PL.LEFT_HIP]
        rh = lms[_PL.RIGHT_HIP]

        shoulder_mid = _midpoint(ls, rs)
        hip_mid = _midpoint(lh, rh)

        shoulder_width = max(0.0, abs(float(ls.x) - float(rs.x)))
        torso_height = max(0.0, abs(float(shoulder_mid[1] - hip_mid[1])))
        torso_area = shoulder_width * torso_height

        visibility = (_vis(ls) + _vis(rs) + _vis(lh) + _vis(rh)) / 4.0

        px = shoulder_mid[0] * frame_w
        py = shoulder_mid[1] * frame_h
        center_dist = hypot(px - frame_cx, py - frame_cy)
        center_score = 1.0 - _clamp(center_dist / max_dist, 0.0, 1.0)

        score = torso_area * (0.60 + 0.40 * visibility) * (0.75 + 0.25 * center_score)

        if score > best_score:
            best_score = score
            best_idx = idx

    return best_idx


def _compute_face_boxes_from_poses(
    pose_landmarks: list[list],
    frame_w: int,
    frame_h: int,
) -> list[tuple[int, int, int, int]]:
    boxes: list[tuple[int, int, int, int]] = []

    for lms in pose_landmarks:
        if len(lms) <= _PL.RIGHT_SHOULDER:
            continue

        nose = lms[_PL.NOSE]
        ls = lms[_PL.LEFT_SHOULDER]
        rs = lms[_PL.RIGHT_SHOULDER]

        shoulder_width_px = abs(float(ls.x) - float(rs.x)) * frame_w
        if shoulder_width_px <= 1.0:
            continue

        candidate_points: list[tuple[float, float]] = []

        for idx in (
            _PL.NOSE,
            _PL.LEFT_EAR,
            _PL.RIGHT_EAR,
            _PL.LEFT_EYE,
            _PL.RIGHT_EYE,
            _PL.LEFT_EYE_INNER,
            _PL.RIGHT_EYE_INNER,
            _PL.LEFT_EYE_OUTER,
            _PL.RIGHT_EYE_OUTER,
        ):
            if idx < len(lms):
                p = lms[idx]
                if _vis(p) >= 0.20:
                    candidate_points.append((float(p.x) * frame_w, float(p.y) * frame_h))

        nx = float(nose.x) * frame_w
        ny = float(nose.y) * frame_h

        face_size = max(32.0, shoulder_width_px * 0.60)

        if candidate_points:
            xs = [p[0] for p in candidate_points]
            ys = [p[1] for p in candidate_points]
            x1 = min(xs) - face_size * 0.35
            x2 = max(xs) + face_size * 0.35
            y1 = min(ys) - face_size * 0.55
            y2 = max(ys) + face_size * 0.65
        else:
            x1 = nx - face_size * 0.50
            x2 = nx + face_size * 0.50
            y1 = ny - face_size * 0.65
            y2 = ny + face_size * 0.70

        x1 = int(_clamp(x1, 0, frame_w - 1))
        y1 = int(_clamp(y1, 0, frame_h - 1))
        x2 = int(_clamp(x2, 0, frame_w - 1))
        y2 = int(_clamp(y2, 0, frame_h - 1))

        if x2 > x1 and y2 > y1:
            boxes.append((x1, y1, x2, y2))

    return boxes


def _blur_boxes(
    frame: np.ndarray,
    boxes: Iterable[tuple[int, int, int, int]],
    kernel_size: int = 99,
) -> None:
    if kernel_size % 2 == 0:
        kernel_size += 1

    h, w = frame.shape[:2]
    for x1, y1, x2, y2 in boxes:
        x1 = int(_clamp(x1, 0, w - 1))
        y1 = int(_clamp(y1, 0, h - 1))
        x2 = int(_clamp(x2, 0, w - 1))
        y2 = int(_clamp(y2, 0, h - 1))
        if x2 <= x1 or y2 <= y1:
            continue

        roi = frame[y1:y2, x1:x2]
        if roi.size == 0:
            continue

        face_w = max(1, x2 - x1)
        dynamic_kernel = max(kernel_size, (face_w // 2) | 1)
        frame[y1:y2, x1:x2] = cv2.GaussianBlur(roi, (dynamic_kernel, dynamic_kernel), 0)


def _default_pose_video_path(video_path: str) -> str:
    src = Path(video_path)
    stem = src.stem or "scan"

    if str(src).startswith("/storage/uploads/videos/"):
        return str(Path("/storage/uploads/pose") / f"{stem}.pose.mp4")

    return str(src.with_name(f"{stem}.pose.mp4"))


def _create_video_writer(output_path: str, width: int, height: int, fps: float) -> cv2.VideoWriter:
    candidates = ("mp4v",)
    for codec in candidates:
        fourcc = cv2.VideoWriter_fourcc(*codec)
        writer = cv2.VideoWriter(output_path, fourcc, fps, (width, height))
        if writer.isOpened():
            return writer
        writer.release()

    raise RuntimeError(f"Cannot create video writer for: {output_path}")


def _transcode_to_web_mp4(video_path: str) -> None:
    ffmpeg = shutil.which("ffmpeg")
    if ffmpeg is None:
        return

    src = Path(video_path)
    if not src.is_file():
        return

    tmp = src.with_suffix(".web.tmp.mp4")
    cmd = [
        ffmpeg,
        "-y",
        "-i",
        str(src),
        "-c:v",
        "libx264",
        "-pix_fmt",
        "yuv420p",
        "-movflags",
        "+faststart",
        "-an",
        str(tmp),
    ]

    result = subprocess.run(
        cmd,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        check=False,
    )

    if result.returncode == 0 and tmp.is_file() and tmp.stat().st_size > 0:
        tmp.replace(src)
    elif tmp.exists():
        tmp.unlink()


def _draw_pose_overlay(
    frame: np.ndarray,
    lms: list | None,
    status: str = "",
) -> None:
    h, w = frame.shape[:2]

    if lms:
        for a, b in _POSE_CONNECTIONS:
            if a >= len(lms) or b >= len(lms):
                continue
            p1 = lms[a]
            p2 = lms[b]
            x1, y1 = int(float(p1.x) * w), int(float(p1.y) * h)
            x2, y2 = int(float(p2.x) * w), int(float(p2.y) * h)
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
            x, y = int(float(p.x) * w), int(float(p.y) * h)
            cv2.circle(frame, (x, y), 4, (0, 200, 255), -1)

    if status:
        cv2.putText(
            frame,
            status,
            (16, 30),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.68,
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
    blur_faces: bool = False,
    blur_kernel_size: int = 99,
    smoothing_alpha: float = 0.35,
    blur_only_selected_subject: bool = False,
) -> dict[str, float | int | str | bool]:
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise RuntimeError(f"Cannot open video: {video_path}")

    policy = multi_person_policy.strip().lower()
    if policy not in {"dominant_subject", "reject"}:
        cap.release()
        raise ValueError("multi_person_policy must be 'dominant_subject' or 'reject'")

    native_fps = float(cap.get(cv2.CAP_PROP_FPS) or 30.0)
    if native_fps <= 1.0:
        native_fps = 30.0

    if sample_every_n is not None:
        skip = max(1, int(sample_every_n))
        analysis_fps = native_fps / skip
    else:
        skip = max(1, round(native_fps / max(1.0, target_fps)))
        analysis_fps = native_fps / skip

    resolved_output_video_path: str | None = None
    if generate_visualization:
        resolved_output_video_path = output_video_path or _default_pose_video_path(video_path)
        Path(resolved_output_video_path).parent.mkdir(parents=True, exist_ok=True)

    trunk_angles: list[float] = []
    neck_angles: list[float] = []
    upper_arm_angles: list[float] = []
    lower_arm_angles: list[float] = []
    wrist_angles: list[float] = []

    shoulder_elevated_frames = 0
    sampled_frames = 0
    processed_pose_frames = 0
    confidence_total = 0.0
    confidence_count = 0
    frame_idx = 0
    multi_person_detected_frames = 0
    max_persons_detected = 0

    writer: cv2.VideoWriter | None = None
    last_face_boxes: list[tuple[int, int, int, int]] = []
    last_overlay_landmarks: list | None = None
    last_status = ""
    video_w = 0
    video_h = 0

    with PoseLandmarker.create_from_options(_OPTIONS) as landmarker:
        try:
            while True:
                ok, frame = cap.read()
                if not ok:
                    break

                frame_idx += 1
                if video_w == 0 or video_h == 0:
                    video_h, video_w = frame.shape[:2]

                if generate_visualization and writer is None and resolved_output_video_path is not None:
                    fps_for_output = native_fps if native_fps > 1.0 else max(1.0, target_fps)
                    writer = _create_video_writer(resolved_output_video_path, video_w, video_h, fps_for_output)

                should_process = (frame_idx % skip == 0)

                if not should_process:
                    if blur_faces and last_face_boxes:
                        _blur_boxes(frame, last_face_boxes, blur_kernel_size)

                    if writer is not None:
                        _draw_pose_overlay(frame, last_overlay_landmarks, last_status)
                        writer.write(frame)
                    continue

                sampled_frames += 1

                rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb)
                timestamp_ms = int(round((frame_idx / native_fps) * 1000.0))
                result = landmarker.detect_for_video(mp_image, timestamp_ms)

                if not result.pose_landmarks:
                    last_face_boxes = []
                    last_overlay_landmarks = None
                    last_status = f"Frame {frame_idx}: no pose detected"

                    if writer is not None:
                        _draw_pose_overlay(frame, None, last_status)
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
                    chosen_idx = _dominant_pose_index(persons, video_w, video_h)

                lms = persons[chosen_idx]
                processed_pose_frames += 1
                last_overlay_landmarks = lms

                if blur_faces:
                    if blur_only_selected_subject:
                        face_boxes = _compute_face_boxes_from_poses([lms], video_w, video_h)
                    else:
                        face_boxes = _compute_face_boxes_from_poses(persons, video_w, video_h)

                    last_face_boxes = face_boxes
                    if face_boxes:
                        _blur_boxes(frame, face_boxes, blur_kernel_size)
                else:
                    last_face_boxes = []

                last_status = f"Frame {frame_idx}: analysed"
                if person_count > 1:
                    last_status += f" ({person_count} persons, dominant #{chosen_idx + 1})"
                if blur_faces:
                    last_status += " [faces blurred]"

                if writer is not None:
                    _draw_pose_overlay(frame, lms, last_status)
                    writer.write(frame)

                if len(lms) <= _PL.RIGHT_WRIST:
                    continue

                ls = lms[_PL.LEFT_SHOULDER]
                rs = lms[_PL.RIGHT_SHOULDER]
                lh = lms[_PL.LEFT_HIP]
                rh = lms[_PL.RIGHT_HIP]
                le = lms[_PL.LEFT_ELBOW]
                re = lms[_PL.RIGHT_ELBOW]
                lw = lms[_PL.LEFT_WRIST]
                rw = lms[_PL.RIGHT_WRIST]
                nose = lms[_PL.NOSE]

                mid_shoulder = _midpoint(ls, rs)
                mid_hip = _midpoint(lh, rh)

                raw_trunk = _angle_from_vertical(
                    mid_shoulder[0] - mid_hip[0],
                    mid_shoulder[1] - mid_hip[1],
                )
                _ema_append(trunk_angles, raw_trunk, smoothing_alpha)

                raw_neck = _angle_between_points(
                    (float(nose.x), float(nose.y)),
                    mid_shoulder,
                    mid_hip,
                )
                neck_flexion = abs(180.0 - raw_neck) if raw_neck > 90.0 else raw_neck
                _ema_append(neck_angles, neck_flexion, smoothing_alpha)

                l_upper = _angle_from_vertical(float(le.x) - float(ls.x), float(le.y) - float(ls.y))
                r_upper = _angle_from_vertical(float(re.x) - float(rs.x), float(re.y) - float(rs.y))
                _ema_append(upper_arm_angles, (l_upper + r_upper) / 2.0, smoothing_alpha)

                l_lower = _angle_between_points(_pt(ls), _pt(le), _pt(lw))
                r_lower = _angle_between_points(_pt(rs), _pt(re), _pt(rw))
                _ema_append(lower_arm_angles, (l_lower + r_lower) / 2.0, smoothing_alpha)

                l_wrist = _angle_from_vertical(float(lw.x) - float(le.x), float(lw.y) - float(le.y))
                r_wrist = _angle_from_vertical(float(rw.x) - float(re.x), float(rw.y) - float(re.y))
                wrist_dev = abs(((l_wrist + r_wrist) / 2.0) - 90.0)
                _ema_append(wrist_angles, wrist_dev, smoothing_alpha)

                shoulder_line_y = min(float(ls.y), float(rs.y))
                hands_above_shoulder = (
                    min(float(lw.y), float(le.y)) < shoulder_line_y - 0.02
                    or min(float(rw.y), float(re.y)) < shoulder_line_y - 0.02
                )
                arm_elevated = (_safe_mean([l_upper, r_upper]) >= 45.0)
                if hands_above_shoulder or arm_elevated:
                    shoulder_elevated_frames += 1

                vis = (
                    _vis(ls) + _vis(rs) + _vis(lh) + _vis(rh)
                    + _vis(le) + _vis(re) + _vis(lw) + _vis(rw)
                ) / 8.0
                confidence_total += vis
                confidence_count += 1

        finally:
            cap.release()
            if writer is not None:
                writer.release()

    if generate_visualization and resolved_output_video_path is not None:
        _transcode_to_web_mp4(resolved_output_video_path)

    if not trunk_angles:
        raise RuntimeError("No pose landmarks detected in sampled frames")

    avg_trunk_angle = float(np.mean(trunk_angles))
    max_trunk_angle = float(np.max(trunk_angles))
    shoulder_elevation_duration = shoulder_elevated_frames / max(1, processed_pose_frames)

    repetition_count = 0
    high_threshold = 30.0
    low_threshold = 15.0
    state_high = False
    for angle in trunk_angles:
        if not state_high and angle >= high_threshold:
            repetition_count += 1
            state_high = True
        elif state_high and angle <= low_threshold:
            state_high = False

    processing_confidence = confidence_total / max(1, confidence_count)

    metrics: dict[str, float | int | str | bool] = {
        "max_trunk_angle": round(max_trunk_angle, 2),
        "avg_trunk_angle": round(avg_trunk_angle, 2),
        "neck_angle": round(_safe_mean(neck_angles, 10.0), 2),
        "upper_arm_angle": round(_safe_mean(upper_arm_angles, 20.0), 2),
        "lower_arm_angle": round(_safe_mean(lower_arm_angles, 80.0), 2),
        "wrist_angle": round(_safe_mean(wrist_angles, 0.0), 2),
        "shoulder_elevation_duration": round(float(shoulder_elevation_duration), 4),
        "repetition_count": int(repetition_count),
        "processing_confidence": round(float(processing_confidence), 4),
        "multi_person_detected_frames": int(multi_person_detected_frames),
        "max_persons_detected": int(max_persons_detected),
        "multi_person_policy": policy,
        "faces_blurred": bool(blur_faces),
        "sampled_frames": int(sampled_frames),
        "processed_pose_frames": int(processed_pose_frames),
        "analysis_fps": round(float(analysis_fps), 3),
    }

    if generate_visualization and resolved_output_video_path is not None:
        metrics["pose_video_path"] = resolved_output_video_path

    return metrics