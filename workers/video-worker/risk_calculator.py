"""Deprecated worker-side scoring module.

Scoring is now centralized in PHP (AssessmentEngine) via internal worker callbacks.
This module is intentionally non-functional to prevent split scoring logic.
"""

from __future__ import annotations


def score_video_model(model: str, metrics: dict) -> dict:
    raise RuntimeError(
        "Worker-side score_video_model() is disabled. "
        "Use PHP internal endpoint /api/v1/internal/worker/scans/complete."
    )


def score_video(max_trunk_angle: float, shoulder_elevation_duration: float, repetition_count: int) -> dict[str, float | str]:
    raise RuntimeError(
        "Worker-side score_video() is disabled. "
        "Use PHP AssessmentEngine for all scoring."
    )
