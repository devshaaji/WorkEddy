"""Ergonomic angle calculation utilities.

Provides functions for computing joint angles from 3-D or 2-D pose landmarks,
following standard occupational ergonomics conventions.
"""

from __future__ import annotations

from math import acos, degrees, sqrt


def vector_angle(v1: tuple[float, float], v2: tuple[float, float]) -> float:
    """Return the angle in degrees between two 2-D vectors."""
    dot   = v1[0] * v2[0] + v1[1] * v2[1]
    mag1  = sqrt(v1[0] ** 2 + v1[1] ** 2)
    mag2  = sqrt(v2[0] ** 2 + v2[1] ** 2)
    if mag1 == 0 or mag2 == 0:
        return 0.0
    cos_a = max(-1.0, min(1.0, dot / (mag1 * mag2)))
    return degrees(acos(cos_a))


def trunk_flexion_angle(
    hip: tuple[float, float],
    shoulder: tuple[float, float],
) -> float:
    """
    Compute trunk flexion as the angle between the torso segment
    and the vertical axis (positive = forward flexion).

    Parameters
    ----------
    hip:       (x, y) normalised coordinates of mid-hip point.
    shoulder:  (x, y) normalised coordinates of mid-shoulder point.
    """
    torso_vec   = (shoulder[0] - hip[0], shoulder[1] - hip[1])
    vertical    = (0.0, -1.0)                                   # upward in image coords
    return vector_angle(torso_vec, vertical)


def shoulder_elevation_angle(
    elbow: tuple[float, float],
    shoulder: tuple[float, float],
) -> float:
    """
    Compute upper arm elevation angle relative to horizontal.

    Parameters
    ----------
    elbow:    (x, y) elbow landmark.
    shoulder: (x, y) shoulder landmark.
    """
    arm_vec    = (elbow[0] - shoulder[0], elbow[1] - shoulder[1])
    horizontal = (1.0, 0.0)
    return vector_angle(arm_vec, horizontal)


def classify_trunk_flexion(angle_deg: float) -> str:
    """
    Return a risk band label for a given trunk flexion angle.

    Bands:
        0–20°  → low
        20–45° → moderate
        >45°   → high
    """
    if angle_deg > 45.0:
        return "high"
    if angle_deg > 20.0:
        return "moderate"
    return "low"


def classify_shoulder_elevation(angle_deg: float) -> str:
    """
    Return a risk band label for shoulder elevation.

    Bands:
        0–30°  → low
        30–60° → moderate
        >60°   → high
    """
    if angle_deg > 60.0:
        return "high"
    if angle_deg > 30.0:
        return "moderate"
    return "low"