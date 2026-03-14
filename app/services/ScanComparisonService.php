<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Services\Ergonomics\AssessmentEngine;

final class ScanComparisonService
{
    /** @var string[] */
    private const NON_METRIC_FIELDS = ['id', 'scan_id', 'created_at'];
    /** @var string[] */
    private const POSE_ANGLE_KEYS = ['neck_angle', 'trunk_angle', 'upper_arm_angle', 'lower_arm_angle', 'wrist_angle'];

    public function __construct(
        private readonly ScanRepository $scans,
        private readonly AssessmentEngine $assessmentEngine,
        private readonly ImprovementProofService $improvementProofs,
    ) {}

    public function compare(int $organizationId, int $scanAId, int $scanBId): array
    {
        if ($scanAId <= 0 || $scanBId <= 0) {
            throw new RuntimeException('scanA and scanB must be positive integers');
        }

        if ($scanAId === $scanBId) {
            throw new RuntimeException('scanA and scanB must be different scans');
        }

        $scanA = $this->scans->findAnalysisById($organizationId, $scanAId);
        $scanB = $this->scans->findAnalysisById($organizationId, $scanBId);

        $modelA = strtolower(trim((string) ($scanA['model'] ?? '')));
        $modelB = strtolower(trim((string) ($scanB['model'] ?? '')));

        if ($modelA === '' || $modelB === '') {
            throw new RuntimeException('Missing model information on one or both scans');
        }

        // Ensure the model is registered in the assessment engine.
        $this->assessmentEngine->resolve($modelA);
        $this->assessmentEngine->resolve($modelB);

        if ($modelA !== $modelB) {
            throw new RuntimeException("Cannot compare scans from different models: {$modelA} vs {$modelB}");
        }

        $algorithmA = $this->normalizeAlgorithmVersion($scanA['algorithm_version'] ?? null);
        $algorithmB = $this->normalizeAlgorithmVersion($scanB['algorithm_version'] ?? null);
        if ($algorithmA !== $algorithmB) {
            throw new RuntimeException(
                "Cannot compare scans scored with different algorithm versions: {$algorithmA} vs {$algorithmB}"
            );
        }

        $rawA = $this->toFloat($scanA['result_score'] ?? $scanA['raw_score'] ?? null);
        $rawB = $this->toFloat($scanB['result_score'] ?? $scanB['raw_score'] ?? null);
        $normA = $this->toFloat($scanA['normalized_score'] ?? null);
        $normB = $this->toFloat($scanB['normalized_score'] ?? null);

        $rawDelta = $this->roundOrNull($rawA !== null && $rawB !== null ? $rawB - $rawA : null);
        $normDelta = $this->roundOrNull($normA !== null && $normB !== null ? $normB - $normA : null);

        $nodes = $this->compareNodes($modelA, $scanA, $scanB);

        return [
            'model' => $modelA,
            'algorithm_version' => $algorithmA,
            'summary' => [
                'scan_a' => $this->scanSummary($scanA),
                'scan_b' => $this->scanSummary($scanB),
                'direction' => $this->directionFromNormalizedDelta($normDelta),
                'compatible' => true,
            ],
            'score_delta' => [
                'raw' => $rawDelta,
                'normalized' => $normDelta,
            ],
            'nodes' => $nodes,
            'pose_delta' => $this->comparePoseAngles($scanA, $scanB),
            'improvement_proof' => $this->improvementProofs->build(
                $this->scanSummary($scanA),
                $this->scanSummary($scanB),
                $nodes,
            ),
        ];
    }

    private function scanSummary(array $scan): array
    {
        return [
            'id' => isset($scan['id']) ? (int) $scan['id'] : null,
            'scan_type' => $scan['scan_type'] ?? null,
            'model' => $scan['model'] ?? null,
            'raw_score' => $this->toFloat($scan['result_score'] ?? $scan['raw_score'] ?? null),
            'normalized_score' => $this->toFloat($scan['normalized_score'] ?? null),
            'risk_category' => $scan['risk_category'] ?? null,
            'risk_level' => $scan['risk_level'] ?? null,
            'algorithm_version' => $this->normalizeAlgorithmVersion($scan['algorithm_version'] ?? null),
            'created_at' => $scan['created_at'] ?? null,
        ];
    }

    private function compareNodes(string $model, array $scanA, array $scanB): array
    {
        $fields = $this->metricFieldsForModel($model, $scanA, $scanB);
        $nodes = [];

        foreach ($fields as $field) {
            $aVal = $this->metricValue($scanA, $field);
            $bVal = $this->metricValue($scanB, $field);

            if ($aVal === null && $bVal === null) {
                continue;
            }

            if ($aVal !== null && $bVal !== null && is_numeric($aVal) && is_numeric($bVal)) {
                $delta = round((float) $bVal - (float) $aVal, 2);
                $nodes[] = [
                    'node' => $this->prettyNodeName($field),
                    'key' => $field,
                    'scan_a' => (float) $aVal,
                    'scan_b' => (float) $bVal,
                    'delta' => $delta,
                ];
            }
        }

        return $nodes;
    }

    private function comparePoseAngles(array $scanA, array $scanB): array
    {
        $anglesA = $this->extractPoseAngles($scanA);
        $anglesB = $this->extractPoseAngles($scanB);

        if ($anglesA === [] || $anglesB === []) {
            return [
                'available' => false,
                'reason' => 'Missing pose angle data on one or both scans',
                'angles' => [],
            ];
        }

        $keys = array_values(array_intersect(array_keys($anglesA), array_keys($anglesB)));
        if ($keys === []) {
            return [
                'available' => false,
                'reason' => 'No shared pose angle nodes found between scans',
                'angles' => [],
            ];
        }

        $angles = [];
        foreach ($keys as $key) {
            $a = $anglesA[$key];
            $b = $anglesB[$key];
            $angles[$key] = [
                'scan_a' => $a,
                'scan_b' => $b,
                'delta' => round($b - $a, 2),
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'angles' => $angles,
        ];
    }

    /** @return array<string,float> */
    private function extractPoseAngles(array $scan): array
    {
        $metrics = $scan['metrics'] ?? [];
        if (!is_array($metrics)) {
            return [];
        }

        $angles = [];
        foreach ($metrics as $key => $value) {
            if (!is_string($key) || !in_array($key, self::POSE_ANGLE_KEYS, true)) {
                continue;
            }
            if (!is_numeric($value)) {
                continue;
            }
            $angles[$key] = (float) $value;
        }

        return $angles;
    }

    /** @return string[] */
    private function metricFieldsForModel(string $model, array $scanA, array $scanB): array
    {
        $descriptors = $this->assessmentEngine->modelDescriptors();
        foreach ($descriptors as $descriptor) {
            if (($descriptor['value'] ?? null) !== $model) {
                continue;
            }

            $fields = array_values(array_filter(
                $descriptor['fields'] ?? [],
                fn ($f) => is_string($f) && $f !== '' && !in_array($f, self::NON_METRIC_FIELDS, true)
            ));

            if ($fields !== []) {
                return $fields;
            }
        }

        $metricsA = is_array($scanA['metrics'] ?? null) ? array_keys($scanA['metrics']) : [];
        $metricsB = is_array($scanB['metrics'] ?? null) ? array_keys($scanB['metrics']) : [];
        $keys = array_values(array_intersect($metricsA, $metricsB));

        return array_values(array_filter($keys, fn ($f) => is_string($f) && !in_array($f, self::NON_METRIC_FIELDS, true)));
    }

    private function metricValue(array $scan, string $field): float|string|int|null
    {
        $metrics = $scan['metrics'] ?? null;
        if (is_array($metrics) && array_key_exists($field, $metrics)) {
            return $metrics[$field];
        }

        return $scan[$field] ?? null;
    }

    private function prettyNodeName(string $field): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
    }

    private function directionFromNormalizedDelta(?float $delta): string
    {
        if ($delta === null || abs($delta) < 0.0001) {
            return 'unchanged';
        }

        return $delta < 0 ? 'improved' : 'worsened';
    }

    private function normalizeAlgorithmVersion(mixed $value): string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return 'legacy_v1';
        }

        return strtolower($raw);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function roundOrNull(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }
}
