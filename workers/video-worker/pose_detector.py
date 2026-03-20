"""Backward-compat shim.

The worker now loads `pose_estimation.py` directly.
Keep this module only for stale imports during transition.
"""

from __future__ import annotations

from pose_estimation import estimate_pose_metrics
