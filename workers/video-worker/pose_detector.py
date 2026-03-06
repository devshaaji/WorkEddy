"""Pose estimation helpers — delegates to the canonical ml/processing module.

This module is a thin re-export layer. The actual implementation lives in
``ml/processing/pose_estimation.py`` to avoid code duplication.
"""

from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

# Resolve the canonical ml/processing/pose_estimation module.
# In the Docker image the layout is /app/ml/processing/pose_estimation.py
# while locally the repo root holds ml/processing/pose_estimation.py.
_CANDIDATES = [
    Path(__file__).resolve().parents[2] / "ml" / "processing" / "pose_estimation.py",   # repo
    Path("/app/ml/processing/pose_estimation.py"),                                       # docker
]

_module = None
for _path in _CANDIDATES:
    if _path.is_file():
        _spec = importlib.util.spec_from_file_location("ml_pose_estimation", str(_path))
        if _spec and _spec.loader:
            _module = importlib.util.module_from_spec(_spec)
            _spec.loader.exec_module(_module)
            break

if _module is None:
    raise RuntimeError(
        "Cannot locate ml/processing/pose_estimation.py. "
        f"Searched: {[str(p) for p in _CANDIDATES]}"
    )

# Re-export the public API
estimate_pose_metrics = _module.estimate_pose_metrics
