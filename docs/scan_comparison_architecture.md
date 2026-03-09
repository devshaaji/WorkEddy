# Scan Comparison Architecture — Validated Design Proposal

> **Date:** 2026-03-07  
> **Status:** Proposed  
> **Scope:** Model-agnostic scan comparison system for WorkEddy

---

## 1. Current Architecture Summary

### 1.1 Scan Storage Format

| Table | Purpose | Key Columns |
|---|---|---|
| `scans` | Master record | `id`, `organization_id`, `user_id`, `task_id`, `scan_type` (manual\|video), `model` (rula\|reba\|niosh), `raw_score`, `normalized_score`, `risk_category`, `parent_scan_id`, `status`, `video_path` |
| `scan_metrics` | Unified posture/lifting angles | `neck_angle`, `trunk_angle`, `upper_arm_angle`, `lower_arm_angle`, `wrist_angle`, `leg_score`, `load_weight`, `horizontal_distance`, `vertical_start`, `vertical_travel`, `twist_angle`, `frequency`, `coupling`, `shoulder_elevation_duration`, `repetition_count`, `processing_confidence` |
| `scan_results` | Model-specific computed output | `scan_id`, `model`, `score`, `risk_level`, `recommendation` |

- Scans use a unified `scan_metrics` table where columns are nullable (different models use different subsets).
- Legacy tables (`manual_inputs`, `video_metrics`) exist for backward compatibility but are not used by new flows.

### 1.2 Model Architecture

**Pattern:** Strategy + Registry

```
ErgonomicAssessmentInterface (contract)
├── RulaService   implements  (scores 1–7, manual+video)
├── RebaService   implements  (scores 1–15, manual+video)
└── NioshService  implements  (Lifting Index, manual only)

AssessmentEngine (registry/router)
├── register(ErgonomicAssessmentInterface)  ← dynamic model addition
├── resolve(string model)
├── assess(string model, array metrics)
├── availableModels()
└── modelDescriptors()
```

**Interface contract:**
```php
interface ErgonomicAssessmentInterface {
    public function modelName(): string;
    public function supportedInputTypes(): array;    // ['manual','video']
    public function validate(array $metrics): void;
    public function calculateScore(array $metrics): array;
    public function getRiskLevel(float $score): string;
}
```

**Score output contract (all models return):**
```php
[
    'score'            => float,   // model-native scale
    'risk_level'       => string,  // human-readable risk text
    'recommendation'   => string,
    'raw_score'        => float,
    'normalized_score' => float,   // 0–100 scale
    'risk_category'    => string,  // 'low'|'moderate'|'high'
]
```

### 1.3 Pose Data Structure

**Video pipeline:** PHP queue backend (redis/db) → PHP worker API → Python worker → PHP scoring + persistence

Pose detection uses **MediaPipe Pose Landmarker** (33 landmarks). The worker extracts:

```python
{
    "trunk_angle": float,              # torso vs vertical
    "neck_angle": float,               # nose→shoulder→hip angle
    "upper_arm_angle": float,          # shoulder→elbow vs vertical (avg L+R)
    "lower_arm_angle": float,          # elbow flexion angle (avg L+R)
    "wrist_angle": float,              # elbow→wrist deviation (avg L+R)
    "shoulder_elevation_duration": float,  # fraction of frames with elevation
    "repetition_count": int,           # trunk flexion cycles
    "processing_confidence": float,    # avg landmark visibility
}
```

Workers first request jobs from PHP internal endpoint (`/internal/worker/jobs/next`). Extracted metrics are then sent to PHP callback endpoints, where PHP computes the final score and writes to `scan_metrics`/`scan_results`. Raw landmark coordinates are **not persisted** — only derived angles are stored.

### 1.4 API Structure

- **Framework:** FastRoute (PHP) with closure-based handlers
- **Auth:** JWT Bearer tokens
- **Base path:** `/api/v1`
- **Existing scan endpoints:**

| Method | Path | Handler |
|---|---|---|
| GET | `/scans/models` | List available models |
| POST | `/scans/manual` | Create manual scan |
| POST | `/scans/video` | Create video scan |
| GET | `/scans` | List scans (optional `?task_id=`) |
| GET | `/scans/{id}` | Show single scan |
| GET | `/scans/{id}/compare` | Compare with parent scan (legacy) |

### 1.5 Frontend Framework

- **Templating:** Plain PHP with output buffering
- **Interactivity:** Alpine.js v3 (CDN)
- **Styling:** Bootstrap 5.3 + custom CSS variables
- **Charts:** Chart.js 4 (CDN)
- **No build pipeline** — all static assets

### 1.6 Existing Comparison (Limitations)

The current `GET /scans/{id}/compare` endpoint:
- Only compares a scan to its **parent** via `parent_scan_id`
- Returns raw `{ current, parent }` — no delta computation
- No per-node breakdown, no pose comparison
- Cannot compare arbitrary scan pairs
- No model-awareness in comparison logic

---

## 2. Proposed Comparison Architecture

### 2.1 Design Principles

1. **Model-agnostic:** Comparison logic must work with any registered model without model-specific code paths.
2. **Input-type independent:** Video scans and manual scans are comparable because both produce `scan_metrics` + `scan_results`.
3. **Extensible:** New models added via `register()` get comparison support automatically.
4. **Normalized comparison:** All models produce `normalized_score` (0–100), enabling cross-model comparison at the summary level.

### 2.2 Comparison API

#### Endpoint

```
GET /api/v1/scans/compare?scanA={id}&scanB={id}
```

#### Request

| Param | Type | Required | Description |
|---|---|---|---|
| `scanA` | int | Yes | First scan ID |
| `scanB` | int | Yes | Second scan ID |

Both scans must belong to the caller's organization (tenant isolation enforced).

#### Response Schema

```jsonc
{
  "data": {
    "model": {
      "same_model": true,                    // whether both scans use the same model
      "scan_a_model": "reba",
      "scan_b_model": "reba"
    },
    "scan_a": {
      "id": 42,
      "scan_type": "video",
      "model": "reba",
      "raw_score": 9.0,
      "normalized_score": 60.0,
      "risk_category": "high",
      "risk_level": "High – Investigate and change soon",
      "created_at": "2026-03-01 10:00:00",
      "task_id": 5
    },
    "scan_b": {
      "id": 58,
      "scan_type": "manual",
      "model": "reba",
      "raw_score": 4.0,
      "normalized_score": 26.67,
      "risk_category": "low",
      "risk_level": "Low – No action required",
      "created_at": "2026-03-05 14:30:00",
      "task_id": 5
    },
    "summary": {
      "normalized_score_delta": -33.33,      // B minus A (negative = improvement)
      "raw_score_delta": -5.0,
      "risk_category_changed": true,
      "risk_direction": "improved",          // "improved"|"worsened"|"unchanged"
      "improvement_pct": 55.55,              // percent improvement
      "input_type_match": false,             // whether both scans have the same input type
      "comparable": true                     // false when models differ and normalization isn't meaningful
    },
    "nodes": [
      {
        "metric": "trunk_angle",
        "label": "Trunk Angle",
        "unit": "degrees",
        "scan_a_value": 45.0,
        "scan_b_value": 15.0,
        "delta": -30.0,
        "direction": "improved",
        "risk_zone_a": "high",               // per-metric risk classification
        "risk_zone_b": "low",
        "body_region": "trunk"               // for heatmap grouping
      },
      {
        "metric": "neck_angle",
        "label": "Neck Angle",
        "unit": "degrees",
        "scan_a_value": 25.0,
        "scan_b_value": 10.0,
        "delta": -15.0,
        "direction": "improved",
        "risk_zone_a": "moderate",
        "risk_zone_b": "low",
        "body_region": "neck"
      }
      // ... all shared metrics between both scans
    ],
    "pose_delta": {
      "available": true,                     // false if either scan lacks pose data
      "reason": null,                        // explanation when unavailable
      "angles": {
        "trunk_angle":     { "a": 45.0, "b": 15.0, "delta": -30.0 },
        "neck_angle":      { "a": 25.0, "b": 10.0, "delta": -15.0 },
        "upper_arm_angle": { "a": 60.0, "b": 30.0, "delta": -30.0 },
        "lower_arm_angle": { "a": 85.0, "b": 80.0, "delta": -5.0  },
        "wrist_angle":     { "a": 20.0, "b": 8.0,  "delta": -12.0 }
      },
      "confidence": {
        "scan_a": 0.92,
        "scan_b": null                       // null for manual scans
      }
    }
  }
}
```

### 2.3 Generic Comparison Interface

Extend `ErgonomicAssessmentInterface` with an **optional** comparison method using a default trait, so existing and new models get comparison for free:

```php
interface ComparableAssessmentInterface extends ErgonomicAssessmentInterface
{
    /**
     * Return metric metadata for this model: which scan_metrics columns
     * are relevant, their labels, units, body regions, and risk thresholds.
     *
     * @return array<string, array{
     *     label: string,
     *     unit: string,
     *     body_region: string,
     *     risk_thresholds: array{low: float, moderate: float, high: float},
     *     direction: string
     * }>
     */
    public function metricDescriptors(): array;
}
```

**Default implementation via trait** (all 3 models use this):

```php
trait ComparesAssessments
{
    /**
     * Generic comparison: works for ANY model because it operates
     * on scan_metrics columns + normalized scores.
     */
    public function compare(array $scanA, array $scanB): array
    {
        $descriptors = $this->metricDescriptors();
        $metricsA    = $scanA['metrics'] ?? [];
        $metricsB    = $scanB['metrics'] ?? [];

        $nodes = [];
        foreach ($descriptors as $key => $meta) {
            $valA = isset($metricsA[$key]) ? (float) $metricsA[$key] : null;
            $valB = isset($metricsB[$key]) ? (float) $metricsB[$key] : null;

            if ($valA === null && $valB === null) continue;

            $delta = ($valA !== null && $valB !== null) ? $valB - $valA : null;

            $nodes[] = [
                'metric'      => $key,
                'label'       => $meta['label'],
                'unit'        => $meta['unit'],
                'scan_a_value'=> $valA,
                'scan_b_value'=> $valB,
                'delta'       => $delta,
                'direction'   => $this->deltaDirection($delta, $meta['direction']),
                'risk_zone_a' => $valA !== null ? $this->classifyRisk($valA, $meta['risk_thresholds']) : null,
                'risk_zone_b' => $valB !== null ? $this->classifyRisk($valB, $meta['risk_thresholds']) : null,
                'body_region' => $meta['body_region'],
            ];
        }

        return $nodes;
    }
}
```

This trait is **not model-specific** — it reads column metadata from `metricDescriptors()`. Each model only defines *which* metrics matter and their thresholds.

#### Example: REBA `metricDescriptors()`

```php
public function metricDescriptors(): array
{
    return [
        'trunk_angle'     => ['label' => 'Trunk Angle',     'unit' => '°', 'body_region' => 'trunk',     'risk_thresholds' => ['low' => 20, 'moderate' => 45, 'high' => 60],  'direction' => 'lower_better'],
        'neck_angle'      => ['label' => 'Neck Angle',      'unit' => '°', 'body_region' => 'neck',      'risk_thresholds' => ['low' => 10, 'moderate' => 20, 'high' => 30],  'direction' => 'lower_better'],
        'upper_arm_angle' => ['label' => 'Upper Arm Angle', 'unit' => '°', 'body_region' => 'shoulder',  'risk_thresholds' => ['low' => 20, 'moderate' => 45, 'high' => 90],  'direction' => 'lower_better'],
        'lower_arm_angle' => ['label' => 'Lower Arm Angle', 'unit' => '°', 'body_region' => 'elbow',     'risk_thresholds' => ['low' => 100, 'moderate' => 60, 'high' => 45], 'direction' => 'range_optimal'],
        'wrist_angle'     => ['label' => 'Wrist Angle',     'unit' => '°', 'body_region' => 'wrist',     'risk_thresholds' => ['low' => 5,  'moderate' => 15, 'high' => 25],  'direction' => 'lower_better'],
        'leg_score'       => ['label' => 'Leg Score',       'unit' => '',  'body_region' => 'legs',      'risk_thresholds' => ['low' => 1,  'moderate' => 2,  'high' => 3],   'direction' => 'lower_better'],
    ];
}
```

#### Example: NIOSH `metricDescriptors()`

```php
public function metricDescriptors(): array
{
    return [
        'load_weight'         => ['label' => 'Load Weight',         'unit' => 'kg', 'body_region' => 'load',  'risk_thresholds' => ['low' => 10, 'moderate' => 18, 'high' => 23], 'direction' => 'lower_better'],
        'horizontal_distance' => ['label' => 'Horizontal Distance', 'unit' => 'cm', 'body_region' => 'reach', 'risk_thresholds' => ['low' => 25, 'moderate' => 40, 'high' => 63], 'direction' => 'lower_better'],
        'vertical_start'      => ['label' => 'Vertical Height',     'unit' => 'cm', 'body_region' => 'lift',  'risk_thresholds' => ['low' => 75, 'moderate' => 50, 'high' => 25], 'direction' => 'range_optimal'],
        'vertical_travel'     => ['label' => 'Vertical Travel',     'unit' => 'cm', 'body_region' => 'lift',  'risk_thresholds' => ['low' => 25, 'moderate' => 50, 'high' => 100],'direction' => 'lower_better'],
        'twist_angle'         => ['label' => 'Twist Angle',         'unit' => '°',  'body_region' => 'trunk', 'risk_thresholds' => ['low' => 30, 'moderate' => 60, 'high' => 90], 'direction' => 'lower_better'],
        'frequency'           => ['label' => 'Lifting Frequency',   'unit' => '/min','body_region' => 'load', 'risk_thresholds' => ['low' => 1,  'moderate' => 5,  'high' => 10], 'direction' => 'lower_better'],
    ];
}
```

**Dynamic models** need only implement `metricDescriptors()` to get comparison support automatically.

### 2.4 Comparison Service

New service that orchestrates comparison without model-specific code:

```
app/services/ScanComparisonService.php
```

```php
final class ScanComparisonService
{
    public function __construct(
        private readonly ScanRepository   $scans,
        private readonly AssessmentEngine $engine,
    ) {}

    public function compare(int $orgId, int $scanAId, int $scanBId): array
    {
        // 1. Load both scans (with metrics)
        $scanA = $this->scans->findById($orgId, $scanAId);
        $scanB = $this->scans->findById($orgId, $scanBId);

        // 2. Validate both are completed
        $this->assertCompleted($scanA);
        $this->assertCompleted($scanB);

        // 3. Determine comparison strategy
        $sameModel = $scanA['model'] === $scanB['model'];

        // 4. Build node-level comparison
        $nodes = $sameModel
            ? $this->modelNodeComparison($scanA, $scanB)
            : $this->crossModelNodeComparison($scanA, $scanB);

        // 5. Build pose delta
        $poseDelta = $this->buildPoseDelta($scanA, $scanB);

        // 6. Build summary
        $summary = $this->buildSummary($scanA, $scanB, $sameModel);

        return [
            'model'     => [
                'same_model'   => $sameModel,
                'scan_a_model' => $scanA['model'],
                'scan_b_model' => $scanB['model'],
            ],
            'scan_a'    => $this->formatScanSummary($scanA),
            'scan_b'    => $this->formatScanSummary($scanB),
            'summary'   => $summary,
            'nodes'     => $nodes,
            'pose_delta'=> $poseDelta,
        ];
    }

    /**
     * Same-model comparison: use the model's metricDescriptors
     */
    private function modelNodeComparison(array $a, array $b): array
    {
        $model = $this->engine->resolve($a['model']);
        if ($model instanceof ComparableAssessmentInterface) {
            return $model->compare($a, $b);
        }
        // Fallback: raw metric diff for models without descriptors
        return $this->rawMetricDiff($a['metrics'] ?? [], $b['metrics'] ?? []);
    }

    /**
     * Cross-model comparison: only compare overlapping metrics
     */
    private function crossModelNodeComparison(array $a, array $b): array
    {
        return $this->rawMetricDiff($a['metrics'] ?? [], $b['metrics'] ?? []);
    }
}
```

### 2.5 Comparison Data Structure

#### Internal PHP DTO

```php
final class ScanComparison
{
    public function __construct(
        public readonly array  $model,      // {same_model, scan_a_model, scan_b_model}
        public readonly array  $scanA,      // formatted scan summary
        public readonly array  $scanB,
        public readonly array  $summary,    // {normalized_score_delta, risk_direction, ...}
        public readonly array  $nodes,      // node-level metric comparisons
        public readonly array  $poseDelta,  // {available, angles, confidence}
    ) {}

    public function toArray(): array
    {
        return [
            'model'      => $this->model,
            'scan_a'     => $this->scanA,
            'scan_b'     => $this->scanB,
            'summary'    => $this->summary,
            'nodes'      => $this->nodes,
            'pose_delta' => $this->poseDelta,
        ];
    }
}
```

### 2.6 Edge Case Handling

#### Case 1: Different Models

```jsonc
{
  "model": { "same_model": false, "scan_a_model": "rula", "scan_b_model": "niosh" },
  "summary": {
    "comparable": false,          // raw_score_delta is meaningless across scales
    "normalized_score_delta": -15.0,  // still computed on 0-100 scale
    "raw_score_delta": null,          // null — different scales
    "risk_direction": "improved",     // determined from normalized scores
    "cross_model_note": "Scans use different assessment models. Only normalized scores (0-100) and shared metrics are compared."
  },
  "nodes": [ /* only metrics that exist in BOTH scans' scan_metrics rows */ ]
}
```

**Logic:** When models differ, `raw_score_delta` is set to `null` (RULA 1–7 vs NIOSH Lifting Index are incomparable). Only `normalized_score` (0–100) and overlapping `scan_metrics` columns are compared. The `comparable` flag lets the frontend show a warning.

#### Case 2: Missing Pose Data

```jsonc
{
  "pose_delta": {
    "available": false,
    "reason": "Scan A is a manual input and has no pose angle data.",
    "angles": {},
    "confidence": { "scan_a": null, "scan_b": 0.91 }
  }
}
```

**Logic:** `scan_metrics` rows may be empty for manual scans that only stored data in the legacy `manual_inputs` table. The service checks for null angle columns and gracefully degrades.

#### Case 3: Different Input Types (video vs manual)

Both input types write to `scan_metrics`, so metric comparison works. The response includes:

```jsonc
{
  "summary": {
    "input_type_match": false,
    "input_type_note": "Scan A used video analysis, Scan B used manual input. Confidence levels may differ."
  },
  "pose_delta": {
    "available": true,        // both have angle data in scan_metrics
    "confidence": {
      "scan_a": 0.89,         // video processing confidence
      "scan_b": null           // manual scans have no confidence metric
    }
  }
}
```

#### Case 4: Incomplete/Processing Scans

If either scan has `status != 'completed'`, the API returns:

```json
{ "error": "Scan {id} is not yet completed (status: processing). Cannot compare." }
```

HTTP status: `422 Unprocessable Entity`.

### 2.7 Database Assessment

#### Current Schema Support

| Requirement | Supported? | Table/Column |
|---|---|---|
| Scan metadata | ✅ | `scans.scan_type`, `scans.model`, `scans.status`, `scans.created_at` |
| Model type storage | ✅ | `scans.model` (ENUM) |
| Posture metrics | ✅ | `scan_metrics.*` (all angle columns) |
| Normalized scores | ✅ | `scans.normalized_score` (0–100) |
| Risk categorization | ✅ | `scans.risk_category`, `scan_results.risk_level` |
| Arbitrary pair comparison | ✅ | No schema change needed — `findById()` loads any scan |
| Model-specific result details | ✅ | `scan_results.score`, `scan_results.risk_level` |
| Processing confidence | ✅ | `scan_metrics.processing_confidence` |

#### Required Schema Change

The `scans.model` and `scan_results.model` columns are currently `ENUM('rula','reba','niosh')`. To support dynamic model addition, these must be migrated to `VARCHAR(50)`:

```sql
-- Migration: support dynamic models
ALTER TABLE scans MODIFY COLUMN model VARCHAR(50) NOT NULL DEFAULT 'reba';
ALTER TABLE scan_results MODIFY COLUMN model VARCHAR(50) NOT NULL;
```

#### Optional: Comparison Cache Table

For frequently compared scan pairs, an optional cache table avoids recomputation:

```sql
CREATE TABLE IF NOT EXISTS scan_comparisons (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_a_id   BIGINT UNSIGNED NOT NULL,
    scan_b_id   BIGINT UNSIGNED NOT NULL,
    result_json JSON NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comp_a FOREIGN KEY (scan_a_id) REFERENCES scans(id) ON DELETE CASCADE,
    CONSTRAINT fk_comp_b FOREIGN KEY (scan_b_id) REFERENCES scans(id) ON DELETE CASCADE,
    UNIQUE KEY uq_comparison_pair (scan_a_id, scan_b_id)
);
```

This is **optional** — comparisons can be computed on-the-fly since the data is small and the logic is arithmetic only.

### 2.8 Visualization Data Structures

#### 2.8.1 Skeleton Comparison

Since raw landmark keypoints are **not persisted** (only derived angles), skeleton visualization uses angle data mapped to a canonical body model:

```jsonc
{
  "skeleton": {
    "type": "angle_based",                   // vs "keypoint_based" if landmarks were stored
    "body_segments": [
      { "segment": "trunk",     "joint": "hip",      "angle_a": 45.0, "angle_b": 15.0, "risk_a": "high",     "risk_b": "low"      },
      { "segment": "neck",      "joint": "c7",       "angle_a": 25.0, "angle_b": 10.0, "risk_a": "moderate", "risk_b": "low"      },
      { "segment": "upper_arm", "joint": "shoulder",  "angle_a": 60.0, "angle_b": 30.0, "risk_a": "moderate", "risk_b": "low"      },
      { "segment": "lower_arm", "joint": "elbow",     "angle_a": 85.0, "angle_b": 80.0, "risk_a": "low",      "risk_b": "low"      },
      { "segment": "wrist",     "joint": "wrist",     "angle_a": 20.0, "angle_b": 8.0,  "risk_a": "moderate", "risk_b": "low"      }
    ],
    "reference_skeleton": "canonical"         // use a fixed reference SVG body model
  }
}
```

**Frontend approach:** Use a static SVG skeleton where each body segment is color-coded by risk zone and rotated/styled to reflect the angle. This avoids needing raw keypoints.

#### 2.8.2 Joint Risk Heatmap

Maps body regions to risk intensity for each scan, derived from the `nodes[]` array:

```jsonc
{
  "heatmap": {
    "regions": {
      "neck":     { "risk_a": 0.7, "risk_b": 0.3, "delta": -0.4, "color_a": "#ef4444", "color_b": "#22c55e" },
      "shoulder": { "risk_a": 0.5, "risk_b": 0.2, "delta": -0.3, "color_a": "#f59e0b", "color_b": "#22c55e" },
      "trunk":    { "risk_a": 0.9, "risk_b": 0.3, "delta": -0.6, "color_a": "#ef4444", "color_b": "#22c55e" },
      "elbow":    { "risk_a": 0.2, "risk_b": 0.2, "delta":  0.0, "color_a": "#22c55e", "color_b": "#22c55e" },
      "wrist":    { "risk_a": 0.6, "risk_b": 0.3, "delta": -0.3, "color_a": "#f59e0b", "color_b": "#22c55e" },
      "legs":     { "risk_a": 0.3, "risk_b": 0.3, "delta":  0.0, "color_a": "#22c55e", "color_b": "#22c55e" }
    },
    "scale": { "low": "#22c55e", "moderate": "#f59e0b", "high": "#ef4444" }
  }
}
```

Risk intensity values (0.0–1.0) are computed by normalizing each metric value against its `risk_thresholds` from `metricDescriptors()`.

#### 2.8.3 Score Breakdown

Structured for Chart.js rendering (horizontal bar or radar chart):

```jsonc
{
  "score_breakdown": {
    "chart_type": "horizontal_bar",
    "labels": ["Trunk", "Neck", "Shoulder", "Elbow", "Wrist", "Legs"],
    "datasets": [
      {
        "label": "Scan A (Mar 1)",
        "data": [45.0, 25.0, 60.0, 85.0, 20.0, 1],
        "risk_colors": ["#ef4444", "#f59e0b", "#f59e0b", "#22c55e", "#f59e0b", "#22c55e"]
      },
      {
        "label": "Scan B (Mar 5)",
        "data": [15.0, 10.0, 30.0, 80.0, 8.0, 1],
        "risk_colors": ["#22c55e", "#22c55e", "#22c55e", "#22c55e", "#22c55e", "#22c55e"]
      }
    ],
    "overall": {
      "scan_a": { "score": 9.0, "normalized": 60.0, "risk": "high"     },
      "scan_b": { "score": 4.0, "normalized": 26.67, "risk": "low"     }
    }
  }
}
```

---

## 3. Route & Controller Changes

### 3.1 New Route

```php
// In routes/api.php — add under Scans section:
$r->addRoute('GET', '/scans/compare', fn ($v, $b) => $c->scanCtrl()->compareArbitrary($c->auth()));
```

### 3.2 Controller Method

```php
// In ScanController.php:
public function compareArbitrary(array $claims): never
{
    Auth::requireRoles($claims, ['admin', 'supervisor', 'worker', 'observer']);

    $scanA = (int) ($_GET['scanA'] ?? 0);
    $scanB = (int) ($_GET['scanB'] ?? 0);

    if ($scanA <= 0 || $scanB <= 0) {
        Response::error('Both scanA and scanB query parameters are required', 422);
    }
    if ($scanA === $scanB) {
        Response::error('Cannot compare a scan with itself', 422);
    }

    $result = $this->comparisonService->compare(Auth::orgId($claims), $scanA, $scanB);
    Response::json(['data' => $result]);
}
```

### 3.3 DI Container Registration

```php
// In Container.php:
public function scanComparisonService(): ScanComparisonService
{
    return new ScanComparisonService($this->scanRepo(), $this->assessmentEngine());
}
```

---

## 4. File Plan

| File | Action | Purpose |
|---|---|---|
| `app/services/ergonomics/ComparableAssessmentInterface.php` | **Create** | Extends `ErgonomicAssessmentInterface` with `metricDescriptors()` |
| `app/services/ergonomics/ComparesAssessments.php` | **Create** | Trait with generic `compare()` using metric descriptors |
| `app/services/ScanComparisonService.php` | **Create** | Orchestrator service |
| `app/models/ScanComparison.php` | **Create** | DTO for comparison result |
| `app/services/ergonomics/RulaService.php` | **Modify** | Add `implements ComparableAssessmentInterface`, add `metricDescriptors()` |
| `app/services/ergonomics/RebaService.php` | **Modify** | Same as above |
| `app/services/ergonomics/NioshService.php` | **Modify** | Same as above |
| `app/controllers/ScanController.php` | **Modify** | Add `compareArbitrary()` method |
| `routes/api.php` | **Modify** | Add `GET /scans/compare` route |
| `app/core/Container.php` | **Modify** | Register `ScanComparisonService` |
| `database/migrations/XXXXXX_varchar_model_column.php` | **Create** | ENUM → VARCHAR migration |

---

## 5. Sequence Diagram

```
Client                    ScanController         ScanComparisonService     ScanRepository    AssessmentEngine
  │                            │                         │                       │                  │
  │ GET /scans/compare         │                         │                       │                  │
  │   ?scanA=42&scanB=58       │                         │                       │                  │
  │───────────────────────────▶│                         │                       │                  │
  │                            │  compare(org, 42, 58)   │                       │                  │
  │                            │────────────────────────▶│                       │                  │
  │                            │                         │  findById(org, 42)    │                  │
  │                            │                         │──────────────────────▶│                  │
  │                            │                         │  ◀─ scanA + metrics   │                  │
  │                            │                         │  findById(org, 58)    │                  │
  │                            │                         │──────────────────────▶│                  │
  │                            │                         │  ◀─ scanB + metrics   │                  │
  │                            │                         │                       │                  │
  │                            │                         │  resolve('reba')      │                  │
  │                            │                         │─────────────────────────────────────────▶│
  │                            │                         │  ◀─ RebaService                         │
  │                            │                         │                       │                  │
  │                            │                         │  metricDescriptors()  │                  │
  │                            │                         │  compare(scanA,scanB) │                  │
  │                            │                         │  (via trait)          │                  │
  │                            │                         │                       │                  │
  │                            │  ◀─ ComparisonResult    │                       │                  │
  │  ◀──── JSON response ──────│                         │                       │                  │
```

---

## 6. Validation Checklist

| Requirement | Met? | How |
|---|---|---|
| Works with ANY model dynamically | ✅ | `metricDescriptors()` is data-driven; trait handles comparison generically |
| Accepts video OR manual input | ✅ | Both write to `scan_metrics`; comparison reads from there |
| Works regardless of input type | ✅ | `input_type_match` flag + graceful null handling for missing metrics |
| No model-specific code in comparison | ✅ | `ComparesAssessments` trait is shared; models only declare descriptors |
| Different models edge case | ✅ | `same_model` flag, null `raw_score_delta`, normalized-only comparison |
| Missing pose data edge case | ✅ | `pose_delta.available` flag with `reason` string |
| Different input types edge case | ✅ | `input_type_match` flag, null confidence for manual |
| Skeleton comparison data | ✅ | Angle-based body segments mapped to canonical SVG model |
| Joint risk heatmap data | ✅ | Body region → risk intensity from `metricDescriptors()` thresholds |
| Score breakdown data | ✅ | Chart.js-ready datasets with per-bar risk colors |
| Current schema sufficient | ✅ | Only ENUM→VARCHAR migration needed for dynamic models |
| Backward compatible | ✅ | Existing `GET /scans/{id}/compare` (parent-child) remains unchanged |
