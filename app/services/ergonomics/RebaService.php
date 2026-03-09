<?php

declare(strict_types=1);

namespace WorkEddy\Services\Ergonomics;

use RuntimeException;

/**
 * REBA - Rapid Entire Body Assessment (official table-driven implementation).
 */
final class RebaService implements ErgonomicAssessmentInterface
{
    private const ALGORITHM_VERSION = 'reba_official_v1';

    /**
     * Official REBA Table A.
     *
     * Dimensions:
     * - trunk score: 1..5
     * - neck score: 1..3
     * - leg score: 1..4
     *
     * @var array<int, array<int, array<int, int>>>
     */
    private const TABLE_A = [
        1 => [1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4], 2 => [1 => 1, 2 => 2, 3 => 3, 4 => 4], 3 => [1 => 3, 2 => 3, 3 => 5, 4 => 6]],
        2 => [1 => [1 => 2, 2 => 3, 3 => 4, 4 => 5], 2 => [1 => 3, 2 => 4, 3 => 5, 4 => 6], 3 => [1 => 4, 2 => 5, 3 => 6, 4 => 7]],
        3 => [1 => [1 => 3, 2 => 4, 3 => 5, 4 => 6], 2 => [1 => 4, 2 => 5, 3 => 6, 4 => 7], 3 => [1 => 5, 2 => 6, 3 => 7, 4 => 8]],
        4 => [1 => [1 => 4, 2 => 5, 3 => 6, 4 => 7], 2 => [1 => 5, 2 => 6, 3 => 7, 4 => 8], 3 => [1 => 6, 2 => 7, 3 => 8, 4 => 9]],
        5 => [1 => [1 => 6, 2 => 7, 3 => 8, 4 => 8], 2 => [1 => 7, 2 => 8, 3 => 9, 4 => 9], 3 => [1 => 8, 2 => 9, 3 => 9, 4 => 9]],
    ];

    /**
     * Official REBA Table B.
     *
     * Dimensions:
     * - upper arm score: 1..6
     * - lower arm score: 1..2
     * - wrist score: 1..3
     *
     * @var array<int, array<int, array<int, int>>>
     */
    private const TABLE_B = [
        1 => [1 => [1 => 1, 2 => 2, 3 => 2], 2 => [1 => 1, 2 => 2, 3 => 3]],
        2 => [1 => [1 => 1, 2 => 2, 3 => 3], 2 => [1 => 2, 2 => 3, 3 => 4]],
        3 => [1 => [1 => 3, 2 => 4, 3 => 5], 2 => [1 => 4, 2 => 5, 3 => 5]],
        4 => [1 => [1 => 4, 2 => 5, 3 => 5], 2 => [1 => 5, 2 => 6, 3 => 7]],
        5 => [1 => [1 => 6, 2 => 7, 3 => 8], 2 => [1 => 7, 2 => 8, 3 => 8]],
        6 => [1 => [1 => 7, 2 => 8, 3 => 8], 2 => [1 => 8, 2 => 9, 3 => 9]],
    ];

    /**
     * Official REBA Table C.
     *
     * Dimensions:
     * - score A: 1..12
     * - score B: 1..12
     *
     * @var array<int, array<int, int>>
     */
    private const TABLE_C = [
        1 => [1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3, 6 => 3, 7 => 4, 8 => 5, 9 => 6, 10 => 7, 11 => 7, 12 => 7],
        2 => [1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 4, 7 => 5, 8 => 6, 9 => 7, 10 => 8, 11 => 8, 12 => 8],
        3 => [1 => 2, 2 => 3, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8, 10 => 9, 11 => 9, 12 => 9],
        4 => [1 => 3, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 9, 11 => 9, 12 => 9],
        5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8, 8 => 8, 9 => 9, 10 => 9, 11 => 9, 12 => 9],
        6 => [1 => 6, 2 => 6, 3 => 6, 4 => 7, 5 => 8, 6 => 8, 7 => 9, 8 => 9, 9 => 10, 10 => 10, 11 => 10, 12 => 10],
        7 => [1 => 7, 2 => 7, 3 => 7, 4 => 8, 5 => 9, 6 => 9, 7 => 9, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
        8 => [1 => 8, 2 => 8, 3 => 8, 4 => 9, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
        9 => [1 => 9, 2 => 9, 3 => 9, 4 => 10, 5 => 10, 6 => 10, 7 => 11, 8 => 11, 9 => 11, 10 => 12, 11 => 12, 12 => 12],
        10 => [1 => 10, 2 => 10, 3 => 10, 4 => 11, 5 => 11, 6 => 11, 7 => 11, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
        11 => [1 => 11, 2 => 11, 3 => 11, 4 => 11, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
        12 => [1 => 12, 2 => 12, 3 => 12, 4 => 12, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
    ];

    public function modelName(): string { return 'reba'; }

    public function supportedInputTypes(): array { return ['manual', 'video']; }

    public function validate(array $m): void
    {
        $required = ['trunk_angle', 'neck_angle', 'upper_arm_angle', 'lower_arm_angle', 'wrist_angle'];
        foreach ($required as $f) {
            if (!isset($m[$f]) && !is_numeric($m[$f] ?? null)) {
                throw new RuntimeException("REBA requires field: {$f}");
            }
        }
    }

    public function calculateScore(array $m): array
    {
        $trunk = $this->applyTrunkAdjustments($this->trunkScore((float) $m['trunk_angle']), $m);
        $neck = $this->applyNeckAdjustments($this->neckScore((float) $m['neck_angle']), $m);
        $legs = $this->legScore($m);

        $groupA = $this->lookupTableA($trunk, $neck, $legs);
        $load = $this->loadScore((float) ($m['load_weight'] ?? 0), $m);
        $scoreA = max(1, min(12, $groupA + $load));

        $upperArm = $this->applyUpperArmAdjustments($this->upperArmScore((float) $m['upper_arm_angle']), $m);
        $lowerArm = $this->lowerArmScore((float) $m['lower_arm_angle']);
        $wrist = $this->applyWristAdjustments($this->wristScore((float) $m['wrist_angle']), $m);

        $groupB = $this->lookupTableB($upperArm, $lowerArm, $wrist);
        $coupling = $this->couplingScore((string) ($m['coupling'] ?? 'fair'));
        $scoreB = max(1, min(12, $groupB + $coupling));

        $tableC = $this->lookupTableC($scoreA, $scoreB);

        $activity = $this->activityScore($m);
        $grand = min(15, max(1, $tableC + $activity));

        [$actionLevelCode, $actionLevelLabel] = $this->actionLevel($grand);
        $riskLevel = $this->getRiskLevel((float) $grand);
        $normalized = min(100.0, round($grand / 15 * 100, 2));

        return [
            'score' => $grand,
            'risk_level' => $riskLevel,
            'recommendation' => $this->recommendation($grand),
            'raw_score' => (float) $grand,
            'normalized_score' => $normalized,
            'risk_category' => $this->riskCategoryFromActionLevel($actionLevelCode),
            'action_level_code' => $actionLevelCode,
            'action_level_label' => $actionLevelLabel,
            'algorithm_version' => self::ALGORITHM_VERSION,
        ];
    }

    public function getRiskLevel(float $score): string
    {
        if ($score >= 11) {
            return 'Very High - Implement change now';
        }
        if ($score >= 8) {
            return 'High - Investigate and implement change soon';
        }
        if ($score >= 4) {
            return 'Medium - Investigate and implement change';
        }
        if ($score >= 2) {
            return 'Low - Change may be needed';
        }

        return 'Negligible - No action required';
    }

    private function trunkScore(float $angle): int
    {
        if ($angle < 0) {
            return 2;
        }
        if (abs($angle) < 0.001) {
            return 1;
        }
        if ($angle <= 20) {
            return 2;
        }
        if ($angle <= 60) {
            return 3;
        }

        return 4;
    }

    private function applyTrunkAdjustments(int $base, array $m): int
    {
        $score = $base;
        if ($this->flag($m, 'trunk_twisted', 'trunk_rotation')) {
            $score += 1;
        }
        if ($this->flag($m, 'trunk_side_bent', 'trunk_sidebend')) {
            $score += 1;
        }

        return max(1, min(5, $score));
    }

    private function neckScore(float $angle): int
    {
        if ($angle < 0) {
            return 2;
        }

        return $angle <= 20 ? 1 : 2;
    }

    private function applyNeckAdjustments(int $base, array $m): int
    {
        $score = $base;
        if ($this->flag($m, 'neck_twisted', 'neck_rotation')) {
            $score += 1;
        }
        if ($this->flag($m, 'neck_side_bent', 'neck_sidebend')) {
            $score += 1;
        }

        return max(1, min(3, $score));
    }

    private function legScore(array $m): int
    {
        $score = 1;
        if (isset($m['leg_score']) && is_numeric($m['leg_score'])) {
            $score = max(1, min(4, (int) $m['leg_score']));
        } else {
            $legs = strtolower(trim((string) ($m['legs'] ?? 'supported')));
            $score = in_array($legs, ['supported', 'bilateral', 'both'], true) ? 1 : 2;

            if (isset($m['knee_angle']) && is_numeric($m['knee_angle'])) {
                $knee = abs((float) $m['knee_angle']);
                if ($knee > 60) {
                    $score += 2;
                } elseif ($knee > 30) {
                    $score += 1;
                }
            }
        }

        return max(1, min(4, $score));
    }

    private function loadScore(float $kg, array $m = []): int
    {
        $score = 0;
        if ($kg > 10) {
            $score = 2;
        } elseif ($kg >= 5) {
            $score = 1;
        }

        if ($this->flag($m, 'shock_load', 'sudden_force')) {
            $score += 1;
        }

        return max(0, min(3, $score));
    }

    private function upperArmScore(float $angle): int
    {
        if ($angle < -20) {
            return 2;
        }
        if ($angle <= 20) {
            return 1;
        }
        if ($angle <= 45) {
            return 2;
        }
        if ($angle <= 90) {
            return 3;
        }

        return 4;
    }

    private function applyUpperArmAdjustments(int $base, array $m): int
    {
        $score = $base;
        if ($this->flag($m, 'shoulder_raised', 'raised_shoulder')) {
            $score += 1;
        }
        if ($this->flag($m, 'upper_arm_abducted', 'arm_abducted')) {
            $score += 1;
        }
        if ($this->flag($m, 'arm_supported', 'leaning', 'arm_support')) {
            $score -= 1;
        }

        return max(1, min(6, $score));
    }

    private function lowerArmScore(float $angle): int
    {
        return ($angle >= 60 && $angle <= 100) ? 1 : 2;
    }

    private function wristScore(float $angle): int
    {
        return abs($angle) <= 15 ? 1 : 2;
    }

    private function applyWristAdjustments(int $base, array $m): int
    {
        $score = $base;
        if ($this->flag($m, 'wrist_twist', 'wrist_bent_from_midline', 'wrist_deviation')) {
            $score += 1;
        }

        return max(1, min(3, $score));
    }

    private function couplingScore(string $coupling): int
    {
        return match (strtolower(trim($coupling))) {
            'good' => 0,
            'fair' => 1,
            'poor' => 2,
            'unacceptable' => 3,
            default => 1,
        };
    }

    private function lookupTableA(int $trunk, int $neck, int $legs): int
    {
        $trunk = max(1, min(5, $trunk));
        $neck = max(1, min(3, $neck));
        $legs = max(1, min(4, $legs));

        return self::TABLE_A[$trunk][$neck][$legs];
    }

    private function lookupTableB(int $upperArm, int $lowerArm, int $wrist): int
    {
        $upperArm = max(1, min(6, $upperArm));
        $lowerArm = max(1, min(2, $lowerArm));
        $wrist = max(1, min(3, $wrist));

        return self::TABLE_B[$upperArm][$lowerArm][$wrist];
    }

    private function lookupTableC(int $scoreA, int $scoreB): int
    {
        $scoreA = max(1, min(12, $scoreA));
        $scoreB = max(1, min(12, $scoreB));

        return self::TABLE_C[$scoreA][$scoreB];
    }

    private function activityScore(array $m): int
    {
        $activity = 0;
        if (!empty($m['static_posture'])) {
            $activity++;
        }
        if (!empty($m['repetitive'])) {
            $activity++;
        }
        if (!empty($m['rapid_change'])) {
            $activity++;
        }

        return max(0, min(3, $activity));
    }

    /**
     * @return array{int, string}
     */
    private function actionLevel(int $score): array
    {
        return match (true) {
            $score >= 11 => [4, 'Action Level 4: Very high risk, implement change now'],
            $score >= 8  => [3, 'Action Level 3: High risk, implement change soon'],
            $score >= 4  => [2, 'Action Level 2: Medium risk, investigate and implement change'],
            $score >= 2  => [1, 'Action Level 1: Low risk, change may be needed'],
            default      => [0, 'Action Level 0: Negligible risk'],
        };
    }

    private function riskCategoryFromActionLevel(int $actionLevel): string
    {
        return match (true) {
            $actionLevel <= 1 => 'low',
            $actionLevel === 2 => 'moderate',
            default => 'high',
        };
    }

    private function recommendation(int $score): string
    {
        return match (true) {
            $score >= 11 => 'Very high risk detected. Implement immediate corrective action.',
            $score >= 8  => 'High risk detected. Investigate and implement changes as soon as possible.',
            $score >= 4  => 'Medium risk. Investigate and implement ergonomic improvements.',
            $score >= 2  => 'Low risk. Changes may be beneficial depending on exposure duration.',
            default      => 'Negligible risk. No immediate action is required.',
        };
    }

    private function flag(array $metrics, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $metrics)) {
                continue;
            }

            $value = $metrics[$key];
            if (is_bool($value)) {
                if ($value) {
                    return true;
                }
                continue;
            }

            if (is_numeric($value)) {
                if ((float) $value > 0.0) {
                    return true;
                }
                continue;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
