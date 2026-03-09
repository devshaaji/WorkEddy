# Scan Comparison

## Overview

The scan comparison system compares two completed scans from the same organization using a model-agnostic service:

- Service: `WorkEddy\Services\ScanComparisonService`
- Endpoint: `GET /api/v1/scans/compare?scanA={id}&scanB={id}`

It computes:

- model compatibility
- summary and score delta
- node-level metric deltas
- pose angle deltas (when angle data exists)

## API Usage

### Request

`GET /api/v1/scans/compare?scanA=101&scanB=102`

### Query params

- `scanA` (required, int)
- `scanB` (required, int)

### Response shape

```json
{
  "data": {
    "model": "reba",
    "summary": {
      "scan_a": {
        "id": 101,
        "scan_type": "video",
        "model": "reba",
        "raw_score": 8,
        "normalized_score": 53.33,
        "risk_category": "high",
        "risk_level": "High",
        "created_at": "2026-03-08 09:00:00"
      },
      "scan_b": {
        "id": 102,
        "scan_type": "manual",
        "model": "reba",
        "raw_score": 4,
        "normalized_score": 26.67,
        "risk_category": "low",
        "risk_level": "Low",
        "created_at": "2026-03-08 09:30:00"
      },
      "direction": "improved",
      "compatible": true
    },
    "score_delta": {
      "raw": -4,
      "normalized": -26.66
    },
    "nodes": [
      {
        "node": "TrunkAngle",
        "key": "trunk_angle",
        "scan_a": 40,
        "scan_b": 15,
        "delta": -25
      }
    ],
    "pose_delta": {
      "available": true,
      "reason": null,
      "angles": {
        "trunk_angle": { "scan_a": 40, "scan_b": 15, "delta": -25 }
      }
    }
  }
}
```

## Comparison structure

`ScanComparisonService::compare($organizationId, $scanAId, $scanBId)` returns:

- `model`: shared model name
- `summary`: scan metadata and direction (`improved|worsened|unchanged`)
- `score_delta`: raw and normalized deltas (`scanB - scanA`)
- `nodes`: metric-level deltas
- `pose_delta`: angle deltas for keys ending in `_angle`

## Model extensibility

The service does not hardcode scoring formulas.

It uses the existing `AssessmentEngine` registry to:

1. resolve both scan models (`resolve()`)
2. enforce compatibility (same model required)
3. discover model fields from `modelDescriptors()` for node comparisons

For future models:

- register the model in `AssessmentEngine`
- expose fields in `modelDescriptors()`

The comparison service will automatically use those fields for node-level diffs.

## Error cases

- missing/invalid query params → `422`
- model mismatch (e.g., RULA vs REBA) → runtime error
- missing pose angles on one/both scans → `pose_delta.available = false` with `reason`
