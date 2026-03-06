"""Video worker processor implementation."""

from __future__ import annotations

import importlib.util
from pathlib import Path

import pymysql

ROOT = Path(__file__).resolve().parent


def _load(name: str, filename: str):
    spec = importlib.util.spec_from_file_location(name, ROOT / filename)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Unable to load module {filename}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


frame_extractor = _load("frame_extractor", "frame_extractor.py")
pose_detector = _load("pose_detector", "pose_detector.py")
risk_calculator = _load("risk_calculator", "risk_calculator.py")


def process_scan_job(job: dict, conn: pymysql.connections.Connection) -> None:
    scan_id = int(job["scan_id"])
    organization_id = int(job["organization_id"])
    video_path = str(job["video_path"])
    model = str(job.get("model", "reba")).lower()

    # Validate model supports video
    if model == "niosh":
        raise ValueError("NIOSH model does not support video scans")

    frame_extractor.sample_frame_stats(video_path=video_path, sample_every_n=4)
    pose_metrics = pose_detector.estimate_pose_metrics(video_path=video_path, target_fps=10.0)

    # Build metrics dict for model scoring using actual extracted joint angles
    metrics_for_scoring = {
        "trunk_angle": float(pose_metrics["max_trunk_angle"]),
        "neck_angle": float(pose_metrics["neck_angle"]),
        "upper_arm_angle": float(pose_metrics["upper_arm_angle"]),
        "lower_arm_angle": float(pose_metrics["lower_arm_angle"]),
        "wrist_angle": float(pose_metrics["wrist_angle"]),
        "leg_score": 1,
        "shoulder_elevation_duration": float(pose_metrics["shoulder_elevation_duration"]),
        "repetition_count": int(pose_metrics["repetition_count"]),
        "processing_confidence": float(pose_metrics["processing_confidence"]),
    }

    # Score using the model-specific calculator
    risk = risk_calculator.score_video_model(model, metrics_for_scoring)

    with conn.cursor() as cursor:
        # Insert into scan_metrics (new unified table)
        cursor.execute(
            """
            INSERT INTO scan_metrics (scan_id, neck_angle, trunk_angle, upper_arm_angle, lower_arm_angle, wrist_angle, leg_score,
                                      shoulder_elevation_duration, repetition_count, processing_confidence)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                neck_angle = VALUES(neck_angle),
                trunk_angle = VALUES(trunk_angle),
                upper_arm_angle = VALUES(upper_arm_angle),
                lower_arm_angle = VALUES(lower_arm_angle),
                wrist_angle = VALUES(wrist_angle),
                leg_score = VALUES(leg_score),
                shoulder_elevation_duration = VALUES(shoulder_elevation_duration),
                repetition_count = VALUES(repetition_count),
                processing_confidence = VALUES(processing_confidence)
            """,
            (
                scan_id,
                metrics_for_scoring["neck_angle"],
                metrics_for_scoring["trunk_angle"],
                metrics_for_scoring["upper_arm_angle"],
                metrics_for_scoring["lower_arm_angle"],
                metrics_for_scoring["wrist_angle"],
                metrics_for_scoring["leg_score"],
                metrics_for_scoring["shoulder_elevation_duration"],
                metrics_for_scoring["repetition_count"],
                metrics_for_scoring["processing_confidence"],
            ),
        )

        # Insert into scan_results
        cursor.execute(
            """
            INSERT INTO scan_results (scan_id, model, score, risk_level, recommendation, created_at)
            VALUES (%s, %s, %s, %s, %s, NOW())
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                risk_level = VALUES(risk_level),
                recommendation = VALUES(recommendation)
            """,
            (
                scan_id,
                model,
                risk["score"],
                risk["risk_level"],
                risk["recommendation"],
            ),
        )

        # Update scans summary columns
        cursor.execute(
            """
            UPDATE scans
            SET raw_score = %s,
                normalized_score = %s,
                risk_category = %s,
                status = 'completed'
            WHERE id = %s
            """,
            (risk['raw_score'], risk['normalized_score'], risk['risk_category'], scan_id),
        )

        # Record usage
        cursor.execute(
            """
            INSERT INTO usage_records (organization_id, scan_id, usage_type, created_at)
            VALUES (%s, %s, 'video_scan', NOW())
            """,
            (organization_id, scan_id),
        )


def mark_scan_invalid(scan_id: int, conn: pymysql.connections.Connection) -> None:
    with conn.cursor() as cursor:
        cursor.execute("UPDATE scans SET status = 'invalid' WHERE id = %s", (scan_id,))
