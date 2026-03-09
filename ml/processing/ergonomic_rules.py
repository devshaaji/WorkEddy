"""Ergonomic risk rule engine.

Maps video-derived pose metrics to risk scores using threshold-based rules
aligned with RULA / REBA occupational health guidelines.
"""

from __future__ import annotations

from dataclasses import dataclass


@dataclass
class RiskScore:
    raw_score:         float
    normalized_score:  float
    risk_category:     str

    def to_dict(self) -> dict[str, float | str]:
        return {
            "raw_score":        round(self.raw_score, 2),
            "normalized_score": round(self.normalized_score, 2),
            "risk_category":    self.risk_category,
        }


def _category(score: float) -> str:
    if score >= 70.0:
        return "high"
    if score >= 40.0:
        return "moderate"
    return "low"


def score_from_video_metrics(
    max_trunk_angle: float,
    shoulder_elevation_duration: float,
    repetition_count: int,
    avg_trunk_angle: float = 0.0,
) -> RiskScore:
    """
    Compute a normalised ergonomic risk score (0–100) from pose metrics.

    Rule table
    ----------
    Trunk flexion:
        > 60°   → +30 pts
        > 45°   → +20 pts
        > 20°   → +10 pts

    Shoulder elevation fraction:
        > 30 %  → +20 pts
        > 15 %  → +10 pts

    Repetition count:
        ≥ 25    → +15 pts
        ≥ 10    → +8  pts

    Average trunk angle (sustained posture):
        > 30°   → +5  pts
    """
    score = 0.0

    # Trunk flexion
    if max_trunk_angle > 60.0:
        score += 30
    elif max_trunk_angle > 45.0:
        score += 20
    elif max_trunk_angle > 20.0:
        score += 10

    # Shoulder elevation
    if shoulder_elevation_duration > 0.30:
        score += 20
    elif shoulder_elevation_duration > 0.15:
        score += 10

    # Repetition load
    if repetition_count >= 25:
        score += 15
    elif repetition_count >= 10:
        score += 8

    # Sustained awkward posture penalty
    if avg_trunk_angle > 30.0:
        score += 5

    normalized = max(0.0, min(100.0, round(score, 2)))

    return RiskScore(
        raw_score=score,
        normalized_score=normalized,
        risk_category=_category(normalized),
    )


def score_from_manual_inputs(
    weight:               float,
    frequency:            float,
    duration:             float,
    trunk_angle_estimate: float,
    twisting:             bool,
    overhead:             bool,
    repetition:           float,
) -> RiskScore:
    """
    Compute a normalised ergonomic risk score from manual questionnaire inputs.
    Mirrors the PHP RiskScoreService for consistency.
    """
    score = 0.0
    score += weight               * 1.1
    score += frequency            * 1.3
    score += duration             * 0.6
    score += trunk_angle_estimate * 0.5
    score += repetition           * 1.2
    score += 8.0  if twisting else 0.0
    score += 10.0 if overhead else 0.0

    normalized = max(0.0, min(100.0, round(score, 2)))

    return RiskScore(
        raw_score=score,
        normalized_score=normalized,
        risk_category=_category(normalized),
    )