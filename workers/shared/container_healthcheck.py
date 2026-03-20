from __future__ import annotations

import os
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parent
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from worker_contract import load_contract  # noqa: E402


def main() -> int:
    contract_name = (sys.argv[1] if len(sys.argv) > 1 else "video-worker").strip() or "video-worker"
    token = os.getenv("WORKER_API_TOKEN", "").strip()

    if token == "":
        raise RuntimeError("WORKER_API_TOKEN is not configured")

    load_contract(contract_name)
    print(f"{contract_name} container healthcheck passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
