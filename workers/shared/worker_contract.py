from __future__ import annotations

import json
from functools import lru_cache
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
CONTRACT_DIR = ROOT / "shared" / "worker-contracts"


@lru_cache(maxsize=4)
def load_contract(name: str) -> dict[str, Any]:
    path = CONTRACT_DIR / f"{name}.json"
    if not path.is_file():
        raise RuntimeError(f"Worker contract [{name}] not found at {path}")

    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, dict):
        raise RuntimeError(f"Worker contract [{name}] must decode to an object")

    return data


def route(contract: dict[str, Any], name: str) -> str:
    routes = contract.get("routes")
    if not isinstance(routes, dict):
        raise RuntimeError("Worker contract routes are missing")

    value = str(routes.get(name, "")).strip()
    if value == "":
        raise RuntimeError(f"Worker contract route [{name}] is missing")

    return value


def queue_name(contract: dict[str, Any]) -> str:
    value = str(contract.get("queue_name", "")).strip()
    if value == "":
        raise RuntimeError("Worker contract queue_name is missing")

    return value


def required_fields(contract: dict[str, Any], payload_name: str) -> list[str]:
    payloads = contract.get("payloads")
    if not isinstance(payloads, dict):
        raise RuntimeError("Worker contract payloads are missing")

    payload = payloads.get(payload_name)
    if not isinstance(payload, dict):
        raise RuntimeError(f"Worker contract payload [{payload_name}] is missing")

    required = payload.get("required", [])
    if not isinstance(required, list):
        raise RuntimeError(f"Worker contract payload [{payload_name}] required fields are invalid")

    return [str(field) for field in required]


def validate_payload(contract: dict[str, Any], payload_name: str, payload: dict[str, Any]) -> None:
    missing = [field for field in required_fields(contract, payload_name) if field not in payload]
    if missing:
        raise RuntimeError(
            f"Worker payload [{payload_name}] is missing required fields: {', '.join(missing)}"
        )
