"""Live worker runner — polls PHP internal queue for live-session jobs.

This is the entrypoint for the live-worker Docker service.
Runs independently from the video-worker (separate queue, separate process).
"""

from __future__ import annotations

import os
import sys
import time
from pathlib import Path
from typing import Any

# Ensure sibling modules are importable
ROOT = Path(__file__).resolve().parent
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

import worker as live_worker  # noqa: E402

POLL_INTERVAL_SECONDS = float(os.getenv("LIVE_WORKER_POLL_INTERVAL_SECONDS", "1"))


def run() -> None:
    print("[live-worker-runner] starting live session poller")
    print(f"[live-worker-runner] poll interval: {POLL_INTERVAL_SECONDS}s")

    # Pre-warm the default engine so the first session starts fast
    default_engine = os.getenv("LIVE_POSE_ENGINE", "yolo26")
    default_variant = os.getenv("LIVE_YOLO_MODEL_VARIANT", "yolo26n-pose") if default_engine == "yolo26" else os.getenv("LIVE_MEDIAPIPE_MODEL_VARIANT", "pose_landmarker_lite")
    multi_person_mode = os.getenv("LIVE_MULTI_PERSON_MODE", "0").strip().lower() in {"1", "true", "yes", "on"}
    print(
        "[live-worker-runner] pre-warming default engine: "
        f"{default_engine} (variant={default_variant}, multi_person={multi_person_mode})"
    )
    try:
        live_worker._get_engine(
            default_engine,
            model_variant=default_variant,
            multi_person_mode=multi_person_mode,
        )
        print(f"[live-worker-runner] engine {default_engine} ready")
    except Exception as exc:  # noqa: BLE001
        print(f"[live-worker-runner] WARNING: failed to pre-warm {default_engine}: {exc}")

    while True:
        job: dict[str, Any] | None = None
        frame_batch: dict[str, Any] | None = None

        try:
            job = live_worker.fetch_next_job()
            if job is not None:
                session_id = job.get("session_id", "?")
                print(f"[live-worker-runner] processing session_id={session_id}")

                live_worker.process_live_session(job)
                print(f"[live-worker-runner] session_id={session_id} initialised")
                continue

            frame_batch = live_worker.fetch_next_frame_batch()
            if frame_batch is not None:
                session_id = frame_batch.get("session_id", "?")
                frame_count = len(frame_batch.get("frames", [])) if isinstance(frame_batch.get("frames"), list) else 0
                print(
                    f"[live-worker-runner] processing frame batch for session_id={session_id} "
                    f"frames={frame_count}"
                )
                result = live_worker.process_uploaded_frame_batch(frame_batch)
                telemetry = result.get("telemetry") if isinstance(result, dict) else {}
                telemetry = telemetry if isinstance(telemetry, dict) else {}
                print(
                    f"[live-worker-runner] frame batch processed session_id={session_id} "
                    f"processed={result.get('processed', 0)} skipped={result.get('skipped', 0)} "
                    f"avg_latency_ms={result.get('avg_latency_ms', 0.0)} "
                    f"worker_lag_ms_avg={telemetry.get('worker_lag_ms_avg', 0.0)} "
                    f"decode_failures={telemetry.get('worker_decode_failures', 0)} "
                    f"reason={result.get('dropped_reason', 'processed')}"
                )
                continue

            time.sleep(POLL_INTERVAL_SECONDS)
            continue

        except Exception as exc:  # noqa: BLE001
            print(f"[live-worker-runner] job failed: {exc}")

            try:
                if isinstance(job, dict) and "session_id" in job and "organization_id" in job:
                    live_worker.fail_session(
                        int(job["session_id"]),
                        int(job["organization_id"]),
                        str(exc),
                    )
            except Exception as mark_exc:  # noqa: BLE001
                print(f"[live-worker-runner] failed to mark session as failed: {mark_exc}")

            time.sleep(POLL_INTERVAL_SECONDS)


if __name__ == "__main__":
    run()
