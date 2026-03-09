<?php

declare(strict_types=1);

namespace WorkEddy\Services\Ergonomics;

use RuntimeException;

/**
 * RULA - Rapid Upper Limb Assessment (official table-driven implementation).
 */
final class RulaService implements ErgonomicAssessmentInterface
{
    private const ALGORITHM_VERSION = 'rula_official_v1';

    /**
     * Official RULA Table A.
     *
     * Dimensions:
     * - upper arm score: 1..6
     * - lower arm score: 1..3
     * - wrist score: 1..4
     * - wrist twist score: 1..2
     *
     * @var array<int, array<int, list<int>>>
     */
    private const TABLE_A = [
        1 => [
            1 => [1, 2, 2, 2, 2, 3, 3, 3],
            2 => [2, 2, 2, 2, 3, 3, 3, 3],
            3 => [2, 3, 2, 3, 3, 3, 4, 4],
        ],
        2 => [
            1 => [2, 2, 2, 3, 3, 3, 4, 4],
            2 => [2, 2, 2, 3, 3, 3, 4, 4],
            3 => [2, 3, 3, 3, 3, 4, 4, 5],
        ],
        3 => [
            1 => [2, 3, 3, 3, 4, 4, 5, 5],
            2 => [2, 3, 3, 3, 4, 4, 5, 5],
            3 => [2, 3, 3, 4, 4, 4, 5, 5],
        ],
        4 => [
            1 => [3, 4, 4, 4, 4, 4, 5, 5],
            2 => [3, 4, 4, 4, 4, 4, 5, 5],
            3 => [3, 4, 4, 5, 5, 5, 6, 6],
        ],
        5 => [
            1 => [5, 5, 5, 5, 5, 6, 6, 7],
            2 => [5, 6, 6, 6, 6, 7, 7, 7],
            3 => [6, 6, 6, 7, 7, 7, 7, 8],
        ],
        6 => [
            1 => [7, 7, 7, 7, 7, 8, 8, 9],
            2 => [7, 8, 8, 8, 8, 9, 9, 9],
            3 => [9, 9, 9, 9, 9, 9, 9, 9],
        ],
    ];

    /**
     * Official RULA Table B.
     *
     * Dimensions:
     * - neck score: 1..6
     * - trunk score: 1..6
     * - leg score: 1..2
     *
     * @var array<int, array<int, array<int, int>>>
     */
    private const TABLE_B = [
        1 => [1 => [1 => 1, 2 => 3], 2 => [1 => 2, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 5, 2 => 5], 5 => [1 => 6, 2 => 6], 6 => [1 => 7, 2 => 7]],
        2 => [1 => [1 => 2, 2 => 3], 2 => [1 => 2, 2 => 3], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5], 5 => [1 => 6, 2 => 7], 6 => [1 => 7, 2 => 7]],
        3 => [1 => [1 => 3, 2 => 3], 2 => [1 => 3, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 6], 5 => [1 => 6, 2 => 7], 6 => [1 => 7, 2 => 7]],
        4 => [1 => [1 => 5, 2 => 5], 2 => [1 => 5, 2 => 6], 3 => [1 => 6, 2 => 7], 4 => [1 => 7, 2 => 7], 5 => [1 => 7, 2 => 7], 6 => [1 => 8, 2 => 8]],
        5 => [1 => [1 => 7, 2 => 7], 2 => [1 => 7, 2 => 7], 3 => [1 => 7, 2 => 8], 4 => [1 => 8, 2 => 8], 5 => [1 => 8, 2 => 8], 6 => [1 => 8, 2 => 8]],
        6 => [1 => [1 => 8, 2 => 8], 2 => [1 => 8, 2 => 8], 3 => [1 => 8, 2 => 8], 4 => [1 => 8, 2 => 9], 5 => [1 => 9, 2 => 9], 6 => [1 => 9, 2 => 9]],
    ];

    /**
     * Official RULA Table C.
     *
     * Dimensions:
     * - score A (table A + modifiers): 1..8+
     * - score B (table B + modifiers): 1..7+
     *
     * @var array<int, array<int, int>>
     */
    private const TABLE_C = [
        1 => [1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 5],
        2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 5],
        3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 6],
        4 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 6],
        5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 7],
        6 => [1 => 4, 2 => 4, 3 => 5, 4 => 6, 5 => 6, 6 => 7, 7 => 7],
        7 => [1 => 5, 2 => 5, 3 => 6, 4 => 6, 5 => 7, 6 => 7, 7 => 7],
        8 => [1 => 5, 2 => 5, 3 => 6, 4 => 7, 5 => 7, 6 => 7, 7 => 7],
    ];

    public function modelName(): string { return 'rula'; }

    public function supportedInputTypes(): array { return ['manual', 'video']; }

    public function validate(array $m): void
    {
        $required = ['upper_arm_angle', 'lower_arm_angle', 'wrist_angle', 'neck_angle', 'trunk_angle'];
        foreach ($required as $f) {
            if (!isset($m[$f]) && !is_numeric($m[$f] ?? null)) {
                throw new RuntimeException("RULA requires field: {$f}");
            }
        }
    }

    public function calculateScore(array $m): array
    {
        $upperArm = $this->applyUpperArmAdjustments($this->upperArmScore((float) $m['upper_arm_angle']), $m);
        $lowerArm = $this->applyLowerArmAdjustments($this->lowerArmScore((float) $m['lower_arm_angle']), $m);
        $wrist = $this->applyWristAdjustments($this->wristScore((float) $m['wrist_angle']), $m);
        $wristTwist = $this->wristTwistScore($m['wrist_twist'] ?? null);

        $groupA = $this->lookupTableA($upperArm, $lowerArm, $wrist, $wristTwist);

        $muscleUse = $this->muscleUseScore($m);
        $forceLoad = $this->forceScore((float) ($m['load_weight'] ?? 0), $m);
        $scoreA = max(1, min(8, $groupA + $muscleUse + $forceLoad));

        $neck = $this->applyNeckAdjustments($this->neckScore((float) $m['neck_angle']), $m);
        $trunk = $this->applyTrunkAdjustments($this->trunkScore((float) $m['trunk_angle']), $m);
        $legs = $this->legScore($m);

        $groupB = $this->lookupTableB($neck, $trunk, $legs);
        $scoreB = max(1, min(7, $groupB + $muscleUse + $forceLoad));

        $grand = $this->lookupTableC($scoreA, $scoreB);
        [$actionLevelCode, $actionLevelLabel] = $this->actionLevel($grand);

        $riskLevel = $this->getRiskLevel((float) $grand);
        $normalized = min(100.0, round($grand / 7 * 100, 2));

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
        if ($score >= 7) {
            return 'Very High - Investigate and implement change immediately';
        }
        if ($score >= 5) {
            return 'High - Investigation and changes required soon';
        }
        if ($score >= 3) {
            return 'Moderate - Further investigation, change may be needed';
        }

        return 'Low - Acceptable posture';
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

    private function applyLowerArmAdjustments(int $base, array $m): int
    {
        $score = $base;
        if ($this->flag($m, 'lower_arm_out_of_plane', 'across_midline', 'out_to_side')) {
            $score += 1;
        }

        return max(1, min(3, $score));
    }

    private function wristScore(float $angle): int
    {
        $abs = abs($angle);
        if ($abs < 0.001) {
            return 1;
        }
        if ($abs <= 15) {
            return 2;
        }

        return 3;
    }

    private function applyWristAdjustments(int $base, array $m): int
    {
        $score = $base;
        if ($this->flag($m, 'wrist_bent_from_midline', 'wrist_deviation', 'bent_from_midline')) {
            $score += 1;
        }

        return max(1, min(4, $score));
    }

    private function wristTwistScore(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(1, min(2, (int) $value));
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['end', 'end_range', 'twisted', '2'], true)) {
                return 2;
            }

            return 1;
        }

        return !empty($value) ? 2 : 1;
    }

    private function neckScore(float $angle): int
    {
        if ($angle < 0) {
            return 4;
        }
        if ($angle <= 10) {
            return 1;
        }
        if ($angle <= 20) {
            return 2;
        }

        return 3;
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

        return max(1, min(6, $score));
    }

    private function trunkScore(float $angle): int
    {
        if ($angle < 0) {
            return 3;
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

        return max(1, min(6, $score));
    }

    private function legScore(array $m): int
    {
        if (isset($m['leg_score']) && is_numeric($m['leg_score'])) {
            return max(1, min(2, (int) $m['leg_score']));
        }

        $legs = strtolower(trim((string) ($m['legs'] ?? 'supported')));
        if (in_array($legs, ['supported', 'bilateral', 'both'], true)) {
            return 1;
        }

        return 2;
    }

    private function muscleUseScore(array $m): int
    {
        return (!empty($m['static_posture']) || !empty($m['repetitive'])) ? 1 : 0;
    }

    private function forceScore(float $kg, array $m = []): int
    {
        $staticOrRepeated = !empty($m['static_posture']) || !empty($m['repetitive']);
        $shockLoad = $this->flag($m, 'shock_load', 'sudden_force', 'rapid_change');

        if ($kg <= 2) {
            return 0;
        }

        if ($kg <= 10) {
            return $staticOrRepeated ? 2 : 1;
        }

        return $shockLoad ? 3 : 2;
    }

    private function lookupTableA(int $upperArm, int $lowerArm, int $wrist, int $wristTwist): int
    {
        $upperArm = max(1, min(6, $upperArm));
        $lowerArm = max(1, min(3, $lowerArm));
        $wrist = max(1, min(4, $wrist));
        $wristTwist = max(1, min(2, $wristTwist));

        $column = (($wrist - 1) * 2) + $wristTwist;

        return self::TABLE_A[$upperArm][$lowerArm][$column - 1];
    }

    private function lookupTableB(int $neck, int $trunk, int $legs): int
    {
        $neck = max(1, min(6, $neck));
        $trunk = max(1, min(6, $trunk));
        $legs = max(1, min(2, $legs));

        return self::TABLE_B[$neck][$trunk][$legs];
    }

    private function lookupTableC(int $scoreA, int $scoreB): int
    {
        $scoreA = max(1, min(8, $scoreA));
        $scoreB = max(1, min(7, $scoreB));

        return self::TABLE_C[$scoreA][$scoreB];
    }

    /**
     * @return array{int, string}
     */
    private function actionLevel(int $score): array
    {
        return match (true) {
            $score >= 7 => [4, 'Action Level 4: Investigate and implement change immediately'],
            $score >= 5 => [3, 'Action Level 3: Investigation and changes required soon'],
            $score >= 3 => [2, 'Action Level 2: Further investigation, change may be needed'],
            default     => [1, 'Action Level 1: Posture acceptable'],
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
            $score >= 7 => 'Immediate posture intervention is required. Redesign workstation and task now.',
            $score >= 5 => 'Posture risk is high. Plan engineering and administrative controls promptly.',
            $score >= 3 => 'Investigate posture drivers and evaluate corrective changes.',
            default     => 'Posture is acceptable. Continue periodic monitoring.',
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
