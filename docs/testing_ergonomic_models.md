# Testing Ergonomic Models

This repository includes deterministic tests for official table-driven ergonomic scoring in PHP:

- RULA (official worksheet tables A/B/C)
- REBA (official worksheet tables A/B/C)
- NIOSH
- Video worker pose landmark conversion to posture metrics

## Coverage

### 1. Unit + Boundary Tests (PHP)

Location: `tests/services/`

- `RulaServiceTest.php`
- `RebaServiceTest.php`
- `NioshServiceTest.php`
- `RulaBoundaryThresholdTest.php`
- `RebaBoundaryThresholdTest.php`

These verify:

- posture bucket boundaries
- table lookup stability
- action-level outputs
- output contract fields (`score`, `risk_level`, `risk_category`, `action_level_code`, `algorithm_version`)

### 2. Regression Dataset Tests (PHP)

Location:

- Fixtures: `tests/postures/*.json`
- Dataset test: `tests/services/ErgonomicPostureDatasetTest.php`

Fixture corpus includes:

- 30 RULA manual cases
- 30 REBA manual cases

Each case stores expected score snapshots and metadata:

```json
{
  "model": "rula",
  "inputs": {
    "upper_arm_angle": 60,
    "lower_arm_angle": 90,
    "wrist_angle": 20,
    "neck_angle": 20,
    "trunk_angle": 10,
    "legs": "supported"
  },
  "expected_score": 5,
  "expected_raw_score": 5,
  "expected_normalized_score": 71.43,
  "expected_risk_level": "High - Investigation and changes required soon",
  "expected_risk_category": "high",
  "expected_action_level_code": 3,
  "expected_action_level_label": "Action Level 3: Investigation and changes required soon",
  "expected_algorithm_version": "rula_official_v1"
}
```

### 3. Pose Fixture Scoring Integration (PHP)

Location:

- Pose fixtures: `tests/postures/pose/*.json`
- Integration test: `tests/services/PoseFixtureScoringIntegrationTest.php`

Flow validated:

`pose fixture expected_metrics -> AssessmentEngine -> official RULA/REBA score`

### 4. Worker Pose Pipeline Tests (Python)

Location:

- `tests/workers/test_pose_pipeline.py`
- `tests/workers/test_risk_calculator.py`

These verify:

- landmark geometry conversion into posture metrics
- worker callback payloads to internal PHP endpoints
- worker remains metrics-only (no scorer duplication)

## Automated Runner (Debug Trace)

Runner: `scripts/run_ergonomic_posture_tests.php`

Run all manual fixtures:

```bash
php scripts/run_ergonomic_posture_tests.php
```

Run one model:

```bash
php scripts/run_ergonomic_posture_tests.php --model=rula
```

Enable debug trace:

```bash
php scripts/run_ergonomic_posture_tests.php --debug
```

Debug mode prints intermediate table-driven steps:

- group posture components
- table A/B/C lookups
- load/coupling/activity adjustments
- final score and action level

## Continuous Test Commands

PHP unit tests:

```bash
php vendor/bin/phpunit
```

Python worker tests:

```bash
pytest tests/workers
```

Composite commands:

```bash
make test
composer test
```

## Notes

- PHP is the single scoring authority for manual and video-derived flows.
- Worker-side scoring is intentionally disabled.
- `scan_results.algorithm_version` records scorer provenance (`rula_official_v1`, `reba_official_v1`, etc.).