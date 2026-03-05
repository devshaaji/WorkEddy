"""Initial WorkEddy queue worker scaffold."""

from __future__ import annotations

import json
import os
import time
from typing import Any

import redis


def process_job(job: dict[str, Any]) -> None:
    scan_id = job.get("scan_id")
    video_path = job.get("video_path")
    print(f"[worker] processing scan_id={scan_id} video_path={video_path}")

    # Placeholder for full pipeline:
    # 1) decode frames
    # 2) run pose estimation
    # 3) calculate posture metrics
    # 4) compute risk score
    # 5) persist video_metrics + scan completion status


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
