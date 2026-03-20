"""Pose engine abstraction — switchable backend for live pose estimation.

Supports:
  - "mediapipe" — MediaPipe Pose Landmarker (Tasks API)
  - "yolo26"    — Ultralytics YOLO26n-pose (NMS-free, faster)

The active engine is selected per-session via ``pose_engine`` field.
Both backends expose an identical output contract so the live worker
can treat them interchangeably.
"""

from __future__ import annotations

import os
import time
from abc import ABC, abstractmethod
from dataclasses import dataclass
from math import degrees
from pathlib import Path
from typing import Any

import numpy as np

# ─── Output contract ────────────────────────────────────────────────────
# Every engine returns a dict with at least these keys:
#   trunk_angle:      float
#   neck_angle:       float
#   upper_arm_angle:  float
#   lower_arm_angle:  float
#   wrist_angle:      float
#   confidence:       float   (0.0–1.0)
#   latency_ms:       float
# Plus engine-specific extras are allowed.
# ────────────────────────────────────────────────────────────────────────


class PoseEngine(ABC):
    """Abstract base for a pose estimation backend."""

    @abstractmethod
    def name(self) -> str: ...

    @abstractmethod
    def init(self) -> None:
        """Warm-up / model loading — called once before processing starts."""

    @abstractmethod
    def estimate(self, frame: np.ndarray) -> dict[str, Any] | None:
        """Run pose estimation on a single BGR frame.

        Returns metric dict or None if no pose detected.
        """

    def release(self) -> None:
        """Release resources. Default no-op."""


@dataclass(frozen=True)
class EngineRuntimeConfig:
    """Runtime options used when creating a pose engine instance."""

    model_variant: str | None = None
    multi_person_mode: bool = False


# ═══════════════════════════════════════════════════════════════════════
# MediaPipe Engine
# ═══════════════════════════════════════════════════════════════════════

class _PL:
    """MediaPipe PoseLandmark indices (stable across API versions)."""
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


def _angle_from_vertical(dx: float, dy: float) -> float:
    return abs(degrees(np.arctan2(dx, -dy)))


def _angle_between(a: tuple[float, float], b: tuple[float, float], c: tuple[float, float]) -> float:
    ba = (a[0] - b[0], a[1] - b[1])
    bc = (c[0] - b[0], c[1] - b[1])
    dot = ba[0] * bc[0] + ba[1] * bc[1]
    mag_ba = (ba[0] ** 2 + ba[1] ** 2) ** 0.5
    mag_bc = (bc[0] ** 2 + bc[1] ** 2) ** 0.5
    if mag_ba == 0 or mag_bc == 0:
        return 0.0
    cos_a = max(-1.0, min(1.0, dot / (mag_ba * mag_bc)))
    return abs(degrees(np.arccos(cos_a)))


def _mid(a, b) -> tuple[float, float]:
    return ((a.x + b.x) / 2.0, (a.y + b.y) / 2.0)


def _vis(lm) -> float:
    return float(getattr(lm, "visibility", 1.0) or 1.0)


class MediaPipeEngine(PoseEngine):
    """MediaPipe Pose Landmarker via Tasks API."""

    _MODEL_CANDIDATES = [
        Path("/opt/mediapipe/pose_landmarker_lite.task"),
        Path("/app/ml/models/pose_landmarker_lite.task"),
    ]

    def __init__(self, model_variant: str = "pose_landmarker_lite", multi_person_mode: bool = False) -> None:
        self._variant = model_variant
        self._multi_person_mode = multi_person_mode
        self._landmarker: Any = None

    def name(self) -> str:
        return "mediapipe"

    def init(self) -> None:
        if self._multi_person_mode:
            raise RuntimeError(
                "MediaPipe live engine is single-person only. "
                "Disable LIVE_MULTI_PERSON_MODE or switch to yolo26."
            )

        import mediapipe as mp  # noqa: lazy

        model_path = next(
            (str(p) for p in self._MODEL_CANDIDATES if p.is_file()),
            None,
        )
        if model_path is None:
            raise RuntimeError(
                f"MediaPipe model not found at: {[str(p) for p in self._MODEL_CANDIDATES]}"
            )

        options = mp.tasks.vision.PoseLandmarkerOptions(
            base_options=mp.tasks.BaseOptions(model_asset_path=model_path),
            running_mode=mp.tasks.vision.RunningMode.IMAGE,
            num_poses=1,
            min_pose_detection_confidence=0.5,
            min_pose_presence_confidence=0.5,
            min_tracking_confidence=0.5,
            output_segmentation_masks=False,
        )
        self._landmarker = mp.tasks.vision.PoseLandmarker.create_from_options(options)

    def estimate(self, frame: np.ndarray) -> dict[str, Any] | None:
        import mediapipe as mp  # noqa: lazy

        t0 = time.perf_counter()
        # Convert BGR -> RGB without requiring OpenCV at import/runtime.
        rgb = frame[..., ::-1].copy()
        mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb)
        result = self._landmarker.detect(mp_image)

        if not result.pose_landmarks:
            return None

        lms = result.pose_landmarks[0]
        ls, rs = lms[_PL.LEFT_SHOULDER], lms[_PL.RIGHT_SHOULDER]
        lh, rh = lms[_PL.LEFT_HIP], lms[_PL.RIGHT_HIP]
        le, re = lms[_PL.LEFT_ELBOW], lms[_PL.RIGHT_ELBOW]
        lw, rw = lms[_PL.LEFT_WRIST], lms[_PL.RIGHT_WRIST]
        nose = lms[_PL.NOSE]

        mid_sh = _mid(ls, rs)
        mid_hp = _mid(lh, rh)

        trunk = _angle_from_vertical(mid_sh[0] - mid_hp[0], mid_sh[1] - mid_hp[1])

        neck_raw = _angle_between((nose.x, nose.y), mid_sh, mid_hp)
        neck = abs(180.0 - neck_raw) if neck_raw > 90 else neck_raw

        l_upper = _angle_from_vertical(le.x - ls.x, le.y - ls.y)
        r_upper = _angle_from_vertical(re.x - rs.x, re.y - rs.y)
        upper_arm = (l_upper + r_upper) / 2.0

        l_lower = _angle_between((ls.x, ls.y), (le.x, le.y), (lw.x, lw.y))
        r_lower = _angle_between((rs.x, rs.y), (re.x, re.y), (rw.x, rw.y))
        lower_arm = (l_lower + r_lower) / 2.0

        l_wr = _angle_from_vertical(lw.x - le.x, lw.y - le.y)
        r_wr = _angle_from_vertical(rw.x - re.x, rw.y - re.y)
        wrist = abs((l_wr + r_wr) / 2.0 - 90.0)

        vis_avg = (
            _vis(ls) + _vis(rs) + _vis(lh) + _vis(rh)
            + _vis(le) + _vis(re) + _vis(lw) + _vis(rw)
        ) / 8.0

        latency = (time.perf_counter() - t0) * 1000.0

        return {
            "trunk_angle":     round(trunk, 2),
            "neck_angle":      round(neck, 2),
            "upper_arm_angle": round(upper_arm, 2),
            "lower_arm_angle": round(lower_arm, 2),
            "wrist_angle":     round(wrist, 2),
            "confidence":      round(vis_avg, 4),
            "latency_ms":      round(latency, 2),
            "subject_center_x": round(float(mid_sh[0]), 4),
            "subject_center_y": round(float(mid_sh[1]), 4),
        }

    def release(self) -> None:
        if self._landmarker is not None:
            self._landmarker.close()
            self._landmarker = None


# ═══════════════════════════════════════════════════════════════════════
# YOLO26 Engine
# ═══════════════════════════════════════════════════════════════════════

# YOLO26 pose keypoint indices (COCO 17-keypoint layout)
class _YK:
    NOSE            = 0
    LEFT_EYE        = 1
    RIGHT_EYE       = 2
    LEFT_EAR        = 3
    RIGHT_EAR       = 4
    LEFT_SHOULDER   = 5
    RIGHT_SHOULDER  = 6
    LEFT_ELBOW      = 7
    RIGHT_ELBOW     = 8
    LEFT_WRIST      = 9
    RIGHT_WRIST     = 10
    LEFT_HIP        = 11
    RIGHT_HIP       = 12
    LEFT_KNEE       = 13
    RIGHT_KNEE      = 14
    LEFT_ANKLE      = 15
    RIGHT_ANKLE     = 16


class Yolo26Engine(PoseEngine):
    """Ultralytics YOLO26n-pose via the ultralytics Python package."""

    def __init__(self, model_variant: str = "yolo26n-pose", multi_person_mode: bool = False) -> None:
        self._variant = model_variant
        self._multi_person_mode = multi_person_mode
        self._model: Any = None

    def name(self) -> str:
        return "yolo26"

    def init(self) -> None:
        from ultralytics import YOLO  # noqa: lazy

        # Model weights auto-download on first use to ~/.cache/ultralytics
        model_name = self._variant
        if not model_name.endswith(".pt"):
            model_name += ".pt"

        self._model = YOLO(model_name)
        print(f"[yolo26-engine] loaded {model_name}")

    def estimate(self, frame: np.ndarray) -> dict[str, Any] | None:
        t0 = time.perf_counter()

        results = self._model(frame, verbose=False)
        if not results or len(results) == 0:
            return None

        r = results[0]
        if r.keypoints is None or len(r.keypoints.data) == 0:
            return None

        persons = r.keypoints.data  # shape: (N, 17, 3)
        if len(persons) == 0:
            return None

        frame_h, frame_w = frame.shape[:2]

        def _to_metrics(kpts) -> dict[str, float]:
            if kpts.shape[0] < 17:
                return {}

            def _kp(idx: int) -> tuple[float, float, float]:
                return (float(kpts[idx][0]), float(kpts[idx][1]), float(kpts[idx][2]))

            ls_x, ls_y, ls_c = _kp(_YK.LEFT_SHOULDER)
            rs_x, rs_y, rs_c = _kp(_YK.RIGHT_SHOULDER)
            lh_x, lh_y, lh_c = _kp(_YK.LEFT_HIP)
            rh_x, rh_y, rh_c = _kp(_YK.RIGHT_HIP)
            le_x, le_y, le_c = _kp(_YK.LEFT_ELBOW)
            re_x, re_y, re_c = _kp(_YK.RIGHT_ELBOW)
            lw_x, lw_y, lw_c = _kp(_YK.LEFT_WRIST)
            rw_x, rw_y, rw_c = _kp(_YK.RIGHT_WRIST)
            n_x, n_y, _n_c = _kp(_YK.NOSE)

            mid_sh = ((ls_x + rs_x) / 2.0, (ls_y + rs_y) / 2.0)
            mid_hp = ((lh_x + rh_x) / 2.0, (lh_y + rh_y) / 2.0)

            trunk = _angle_from_vertical(mid_sh[0] - mid_hp[0], mid_sh[1] - mid_hp[1])

            neck_raw = _angle_between((n_x, n_y), mid_sh, mid_hp)
            neck = abs(180.0 - neck_raw) if neck_raw > 90 else neck_raw

            l_upper = _angle_from_vertical(le_x - ls_x, le_y - ls_y)
            r_upper = _angle_from_vertical(re_x - rs_x, re_y - rs_y)
            upper_arm = (l_upper + r_upper) / 2.0

            l_lower = _angle_between((ls_x, ls_y), (le_x, le_y), (lw_x, lw_y))
            r_lower = _angle_between((rs_x, rs_y), (re_x, re_y), (rw_x, rw_y))
            lower_arm = (l_lower + r_lower) / 2.0

            l_wr = _angle_from_vertical(lw_x - le_x, lw_y - le_y)
            r_wr = _angle_from_vertical(rw_x - re_x, rw_y - re_y)
            wrist = abs((l_wr + r_wr) / 2.0 - 90.0)

            conf_avg = (ls_c + rs_c + lh_c + rh_c + le_c + re_c + lw_c + rw_c) / 8.0

            return {
                "trunk_angle": trunk,
                "neck_angle": neck,
                "upper_arm_angle": upper_arm,
                "lower_arm_angle": lower_arm,
                "wrist_angle": wrist,
                "confidence": float(conf_avg),
                "subject_center_x": float(mid_sh[0] / max(1.0, float(frame_w))),
                "subject_center_y": float(mid_sh[1] / max(1.0, float(frame_h))),
            }

        candidate_metrics: list[dict[str, float]] = []
        if self._multi_person_mode:
            for p in persons:
                m = _to_metrics(p)
                if m:
                    candidate_metrics.append(m)
        else:
            m = _to_metrics(persons[0])
            if m:
                candidate_metrics.append(m)

        if not candidate_metrics:
            return None

        # In multi-person mode, choose the highest-trunk-angle person as the representative risk.
        chosen = max(candidate_metrics, key=lambda m: m["trunk_angle"])

        latency = (time.perf_counter() - t0) * 1000.0

        return {
            "trunk_angle":     round(chosen["trunk_angle"], 2),
            "neck_angle":      round(chosen["neck_angle"], 2),
            "upper_arm_angle": round(chosen["upper_arm_angle"], 2),
            "lower_arm_angle": round(chosen["lower_arm_angle"], 2),
            "wrist_angle":     round(chosen["wrist_angle"], 2),
            "confidence":      round(chosen["confidence"], 4),
            "latency_ms":      round(latency, 2),
            "subject_center_x": round(chosen["subject_center_x"], 4),
            "subject_center_y": round(chosen["subject_center_y"], 4),
            "persons_detected": int(len(persons)),
            "multi_person_mode": bool(self._multi_person_mode),
        }

    def release(self) -> None:
        self._model = None


# ═══════════════════════════════════════════════════════════════════════
# Factory
# ═══════════════════════════════════════════════════════════════════════

_ENGINE_REGISTRY: dict[str, type[PoseEngine]] = {
    "mediapipe": MediaPipeEngine,
    "yolo26":    Yolo26Engine,
}


def create_engine(engine_name: str, **kwargs: Any) -> PoseEngine:
    """Create and initialise a pose engine by name.

    Parameters
    ----------
    engine_name:
        One of "mediapipe", "yolo26".
    **kwargs:
        Forwarded to the engine constructor (e.g. ``model_variant``).

    Returns
    -------
    An initialised PoseEngine ready for ``estimate()`` calls.
    """
    cls = _ENGINE_REGISTRY.get(engine_name)
    if cls is None:
        raise ValueError(
            f"Unknown pose engine '{engine_name}'. "
            f"Available: {list(_ENGINE_REGISTRY.keys())}"
        )

    engine = cls(**kwargs)
    engine.init()
    return engine


def available_engines() -> list[str]:
    """Return list of registered engine names."""
    return list(_ENGINE_REGISTRY.keys())


class PoseEngineManager:
    """Decoupled engine manager with cache + runtime config support."""

    def __init__(self) -> None:
        self._cache: dict[str, PoseEngine] = {}

    def get_engine(self, engine_name: str, config: EngineRuntimeConfig | None = None) -> PoseEngine:
        cfg = config or EngineRuntimeConfig()
        cache_key = f"{engine_name}:{cfg.model_variant or 'default'}:{int(cfg.multi_person_mode)}"
        if cache_key not in self._cache:
            kwargs: dict[str, Any] = {
                "multi_person_mode": cfg.multi_person_mode,
            }
            if cfg.model_variant:
                kwargs["model_variant"] = cfg.model_variant
            self._cache[cache_key] = create_engine(engine_name, **kwargs)
        return self._cache[cache_key]

    def clear(self) -> None:
        for engine in self._cache.values():
            engine.release()
        self._cache = {}
