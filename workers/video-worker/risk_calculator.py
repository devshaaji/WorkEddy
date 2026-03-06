"""Risk scoring for video metrics — supports RULA, REBA, and legacy scoring."""

from __future__ import annotations


# ── RULA sub-score helpers ──────────────────────────────────────────────────

def _rula_upper_arm(angle: float) -> int:
    a = abs(angle)
    if a <= 20:
        return 1
    if a <= 45:
        return 2
    if a <= 90:
        return 3
    return 4


def _rula_lower_arm(angle: float) -> int:
    if 60 <= angle <= 100:
        return 1
    return 2


def _rula_wrist(angle: float) -> int:
    a = abs(angle)
    if a <= 5:
        return 1
    if a <= 15:
        return 2
    return 3


def _rula_neck(angle: float) -> int:
    if 0 <= angle <= 10:
        return 1
    if 10 < angle <= 20:
        return 2
    if angle > 20:
        return 3
    return 4  # extension


def _rula_trunk(angle: float) -> int:
    if angle == 0:
        return 1
    if angle <= 20:
        return 2
    if angle <= 60:
        return 3
    return 4


def score_rula(metrics: dict) -> dict:
    """Compute RULA final score (1–7) from angle metrics."""
    upper_arm = _rula_upper_arm(metrics.get("upper_arm_angle", 0))
    lower_arm = _rula_lower_arm(metrics.get("lower_arm_angle", 80))
    wrist = _rula_wrist(metrics.get("wrist_angle", 0))
    neck = _rula_neck(metrics.get("neck_angle", 10))
    trunk = _rula_trunk(metrics.get("trunk_angle", 0))
    leg = int(metrics.get("leg_score", 1))

    group_a = min(7, upper_arm + lower_arm + wrist)
    group_b = min(7, neck + trunk + leg)
    final = min(7, max(group_a, group_b) + 1)

    if final <= 2:
        risk_level = "low"
    elif final <= 4:
        risk_level = "moderate"
    elif final <= 6:
        risk_level = "high"
    else:
        risk_level = "very high"

    return {
        "score": final,
        "raw_score": float(final),
        "normalized_score": round(final / 7.0 * 100, 2),
        "risk_level": risk_level,
        "risk_category": risk_level,
        "recommendation": f"RULA score {final}/7 — {risk_level} risk.",
    }


# ── REBA sub-score helpers ──────────────────────────────────────────────────

def _reba_trunk(angle: float) -> int:
    if angle == 0:
        return 1
    if angle <= 20:
        return 2
    if angle <= 60:
        return 3
    return 4


def _reba_neck(angle: float) -> int:
    if 0 <= angle <= 20:
        return 1
    return 2


def _reba_upper_arm(angle: float) -> int:
    a = abs(angle)
    if a <= 20:
        return 1
    if a <= 45:
        return 2
    if a <= 90:
        return 3
    return 4


def _reba_lower_arm(angle: float) -> int:
    if 60 <= angle <= 100:
        return 1
    return 2


def _reba_wrist(angle: float) -> int:
    if abs(angle) <= 15:
        return 1
    return 2


def score_reba(metrics: dict) -> dict:
    """Compute REBA final score (1–15) from angle metrics."""
    trunk = _reba_trunk(metrics.get("trunk_angle", 0))
    neck = _reba_neck(metrics.get("neck_angle", 10))
    leg = int(metrics.get("leg_score", 1))
    upper_arm = _reba_upper_arm(metrics.get("upper_arm_angle", 0))
    lower_arm = _reba_lower_arm(metrics.get("lower_arm_angle", 80))
    wrist = _reba_wrist(metrics.get("wrist_angle", 0))

    group_a = trunk + neck + leg
    group_b = upper_arm + lower_arm + wrist
    final = min(15, max(group_a, group_b) + 1)

    if final <= 1:
        risk_level = "negligible"
    elif final <= 3:
        risk_level = "low"
    elif final <= 7:
        risk_level = "medium"
    elif final <= 10:
        risk_level = "high"
    else:
        risk_level = "very high"

    return {
        "score": final,
        "raw_score": float(final),
        "normalized_score": round(final / 15.0 * 100, 2),
        "risk_level": risk_level,
        "risk_category": risk_level,
        "recommendation": f"REBA score {final}/15 — {risk_level} risk.",
    }


# ── Model router ────────────────────────────────────────────────────────────

def score_video_model(model: str, metrics: dict) -> dict:
    """Score video metrics using the specified model."""
    model = model.lower()
    if model == "rula":
        return score_rula(metrics)
    if model == "reba":
        return score_reba(metrics)
    raise ValueError(f"Unsupported video model: {model}")


# ── Legacy scorer (backwards compatible) ────────────────────────────────────

def score_video(max_trunk_angle: float, shoulder_elevation_duration: float, repetition_count: int) -> dict[str, float | str]:
    score = 0.0

    if max_trunk_angle > 60:
        score += 30
    elif max_trunk_angle > 45:
        score += 20
    elif max_trunk_angle > 20:
        score += 10

    if shoulder_elevation_duration > 0.3:
        score += 20
    elif shoulder_elevation_duration > 0.15:
        score += 10

    if repetition_count >= 25:
        score += 15
    elif repetition_count >= 10:
        score += 8

    normalized = max(0.0, min(100.0, round(score, 2)))
    if normalized >= 70:
        category = "high"
    elif normalized >= 40:
        category = "moderate"
    else:
        category = "low"

    return {
        "raw_score": round(score, 2),
        "normalized_score": normalized,
        "risk_category": category,
    }
