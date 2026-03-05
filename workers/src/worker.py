"""WorkEddy video processing worker for Milestone 3."""

from __future__ import annotations

import json
import os
import time
from dataclasses import dataclass
from typing import Any

import cv2
import numpy as np
import pymysql
import redis


@dataclass
class VideoMetrics:
    max_trunk_angle: float
    avg_trunk_angle: float
    shoulder_elevation_duration: float
    repetition_count: int
    processing_confidence: float


def analyze_video(video_path: str) -> VideoMetrics:
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise RuntimeError(f"Unable to open video at {video_path}")

    sampled = 0
    trunk_angles: list[float] = []
    shoulder_counter = 0
    movement_energy: list[float] = []

    prev_gray = None
    idx = 0
    while True:
        ok, frame = cap.read()
        if not ok:
            break

        if idx % 4 != 0:
            idx += 1
            continue

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        edges = cv2.Canny(gray, 80, 160)
        edge_ratio = float(np.count_nonzero(edges)) / max(1.0, float(edges.size))

        trunk_angle = min(90.0, max(0.0, edge_ratio * 280.0))
        trunk_angles.append(trunk_angle)

        if trunk_angle > 35.0:
            shoulder_counter += 1

        if prev_gray is not None:
            diff = cv2.absdiff(gray, prev_gray)
            movement_energy.append(float(np.mean(diff)))
        prev_gray = gray

        sampled += 1
        idx += 1

    cap.release()

    if sampled == 0:
        raise RuntimeError("No frames sampled from video")

    avg_trunk = float(np.mean(trunk_angles))
    max_trunk = float(np.max(trunk_angles))
    shoulder_duration = shoulder_counter / sampled

    avg_movement = float(np.mean(movement_energy)) if movement_energy else 0.0
    repetition_count = int(min(300, max(0, avg_movement * 1.7)))

    confidence = min(1.0, max(0.1, sampled / 120.0))

    return VideoMetrics(
        max_trunk_angle=round(max_trunk, 2),
        avg_trunk_angle=round(avg_trunk, 2),
        shoulder_elevation_duration=round(shoulder_duration, 2),
        repetition_count=repetition_count,
        processing_confidence=round(confidence, 2),
    )


def score_video(metrics: VideoMetrics) -> tuple[float, float, str]:
    score = 0.0
    if metrics.max_trunk_angle > 60:
        score += 30
    elif metrics.max_trunk_angle > 40:
        score += 18

    if metrics.shoulder_elevation_duration > 0.3:
        score += 20
    elif metrics.shoulder_elevation_duration > 0.15:
        score += 10

    if metrics.repetition_count > 60:
        score += 15
    elif metrics.repetition_count > 30:
        score += 8

    score += min(35.0, metrics.avg_trunk_angle * 0.45)
    normalized = round(min(100.0, max(0.0, score)), 2)

    if normalized >= 70:
        category = "high"
    elif normalized >= 40:
        category = "moderate"
    else:
        category = "low"

    return round(score, 2), normalized, category


def db_connection() -> pymysql.connections.Connection:
    return pymysql.connect(
        host=os.getenv("DB_HOST", "localhost"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USER", "workeddy"),
        password=os.getenv("DB_PASS", "workeddy"),
        database=os.getenv("DB_NAME", "workeddy"),
        autocommit=False,
        cursorclass=pymysql.cursors.DictCursor,
    )


def process_job(job: dict[str, Any]) -> None:
    scan_id = int(job["scan_id"])
    video_path = str(job["video_path"])
    org_id = int(job["organization_id"])

    print(f"[worker] processing scan_id={scan_id} org={org_id} video_path={video_path}")

    metrics = analyze_video(video_path)
    raw_score, normalized_score, risk_category = score_video(metrics)

    conn = db_connection()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO video_metrics
                (scan_id, max_trunk_angle, avg_trunk_angle, shoulder_elevation_duration, repetition_count, processing_confidence)
                VALUES (%s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    max_trunk_angle = VALUES(max_trunk_angle),
                    avg_trunk_angle = VALUES(avg_trunk_angle),
                    shoulder_elevation_duration = VALUES(shoulder_elevation_duration),
                    repetition_count = VALUES(repetition_count),
                    processing_confidence = VALUES(processing_confidence)
                """,
                (
                    scan_id,
                    metrics.max_trunk_angle,
                    metrics.avg_trunk_angle,
                    metrics.shoulder_elevation_duration,
                    metrics.repetition_count,
                    metrics.processing_confidence,
                ),
            )
            cur.execute(
                """
                UPDATE scans
                SET raw_score = %s,
                    normalized_score = %s,
                    risk_category = %s,
                    status = 'completed'
                WHERE id = %s AND organization_id = %s
                """,
                (raw_score, normalized_score, risk_category, scan_id, org_id),
            )
            cur.execute(
                """
                INSERT INTO usage_records (organization_id, scan_id, usage_type, created_at)
                VALUES (%s, %s, 'video_scan', NOW())
                """,
                (org_id, scan_id),
            )
        conn.commit()
    except Exception:
        conn.rollback()
        with conn.cursor() as cur:
            cur.execute("UPDATE scans SET status = 'invalid' WHERE id = %s", (scan_id,))
        conn.commit()
        raise
    finally:
        conn.close()


def main() -> None:
    host = os.getenv("REDIS_HOST", "localhost")
    port = int(os.getenv("REDIS_PORT", "6379"))
    queue_name = os.getenv("WORKER_QUEUE", "scan_jobs")

    client = redis.Redis(host=host, port=port, decode_responses=True)
    print(f"[worker] listening queue='{queue_name}' at {host}:{port}")

    while True:
        result = client.brpop(queue_name, timeout=5)
        if result is None:
            continue

        _, payload = result
        try:
            job = json.loads(payload)
            process_job(job)
        except json.JSONDecodeError:
            print(f"[worker] invalid payload: {payload}")
        except Exception as exc:  # noqa: BLE001
            print(f"[worker] unexpected error: {exc}")
            time.sleep(1)


if __name__ == "__main__":
    main()
