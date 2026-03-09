<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Support;

final class ErgonomicDebugTracer
{
    /** @var array<int, array<int, list<int>>> */
    private const RULA_TABLE_A = [
        1 => [1 => [1, 2, 2, 2, 2, 3, 3, 3], 2 => [2, 2, 2, 2, 3, 3, 3, 3], 3 => [2, 3, 2, 3, 3, 3, 4, 4]],
        2 => [1 => [2, 2, 2, 3, 3, 3, 4, 4], 2 => [2, 2, 2, 3, 3, 3, 4, 4], 3 => [2, 3, 3, 3, 3, 4, 4, 5]],
        3 => [1 => [2, 3, 3, 3, 4, 4, 5, 5], 2 => [2, 3, 3, 3, 4, 4, 5, 5], 3 => [2, 3, 3, 4, 4, 4, 5, 5]],
        4 => [1 => [3, 4, 4, 4, 4, 4, 5, 5], 2 => [3, 4, 4, 4, 4, 4, 5, 5], 3 => [3, 4, 4, 5, 5, 5, 6, 6]],
        5 => [1 => [5, 5, 5, 5, 5, 6, 6, 7], 2 => [5, 6, 6, 6, 6, 7, 7, 7], 3 => [6, 6, 6, 7, 7, 7, 7, 8]],
        6 => [1 => [7, 7, 7, 7, 7, 8, 8, 9], 2 => [7, 8, 8, 8, 8, 9, 9, 9], 3 => [9, 9, 9, 9, 9, 9, 9, 9]],
    ];

    /** @var array<int, array<int, array<int, int>>> */
    private const RULA_TABLE_B = [
        1 => [1 => [1 => 1, 2 => 3], 2 => [1 => 2, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 5, 2 => 5], 5 => [1 => 6, 2 => 6], 6 => [1 => 7, 2 => 7]],
        2 => [1 => [1 => 2, 2 => 3], 2 => [1 => 2, 2 => 3], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5], 5 => [1 => 6, 2 => 7], 6 => [1 => 7, 2 => 7]],
        3 => [1 => [1 => 3, 2 => 3], 2 => [1 => 3, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 6], 5 => [1 => 6, 2 => 7], 6 => [1 => 7, 2 => 7]],
        4 => [1 => [1 => 5, 2 => 5], 2 => [1 => 5, 2 => 6], 3 => [1 => 6, 2 => 7], 4 => [1 => 7, 2 => 7], 5 => [1 => 7, 2 => 7], 6 => [1 => 8, 2 => 8]],
        5 => [1 => [1 => 7, 2 => 7], 2 => [1 => 7, 2 => 7], 3 => [1 => 7, 2 => 8], 4 => [1 => 8, 2 => 8], 5 => [1 => 8, 2 => 8], 6 => [1 => 8, 2 => 8]],
        6 => [1 => [1 => 8, 2 => 8], 2 => [1 => 8, 2 => 8], 3 => [1 => 8, 2 => 8], 4 => [1 => 8, 2 => 9], 5 => [1 => 9, 2 => 9], 6 => [1 => 9, 2 => 9]],
    ];

    /** @var array<int, array<int, int>> */
    private const RULA_TABLE_C = [
        1 => [1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 5],
        2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 5],
        3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 6],
        4 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 6],
        5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 7],
        6 => [1 => 4, 2 => 4, 3 => 5, 4 => 6, 5 => 6, 6 => 7, 7 => 7],
        7 => [1 => 5, 2 => 5, 3 => 6, 4 => 6, 5 => 7, 6 => 7, 7 => 7],
        8 => [1 => 5, 2 => 5, 3 => 6, 4 => 7, 5 => 7, 6 => 7, 7 => 7],
    ];

    /** @var array<int, array<int, array<int, int>>> */
    private const REBA_TABLE_A = [
        1 => [1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4], 2 => [1 => 1, 2 => 2, 3 => 3, 4 => 4], 3 => [1 => 3, 2 => 3, 3 => 5, 4 => 6]],
        2 => [1 => [1 => 2, 2 => 3, 3 => 4, 4 => 5], 2 => [1 => 3, 2 => 4, 3 => 5, 4 => 6], 3 => [1 => 4, 2 => 5, 3 => 6, 4 => 7]],
        3 => [1 => [1 => 3, 2 => 4, 3 => 5, 4 => 6], 2 => [1 => 4, 2 => 5, 3 => 6, 4 => 7], 3 => [1 => 5, 2 => 6, 3 => 7, 4 => 8]],
        4 => [1 => [1 => 4, 2 => 5, 3 => 6, 4 => 7], 2 => [1 => 5, 2 => 6, 3 => 7, 4 => 8], 3 => [1 => 6, 2 => 7, 3 => 8, 4 => 9]],
        5 => [1 => [1 => 6, 2 => 7, 3 => 8, 4 => 8], 2 => [1 => 7, 2 => 8, 3 => 9, 4 => 9], 3 => [1 => 8, 2 => 9, 3 => 9, 4 => 9]],
    ];

    /** @var array<int, array<int, array<int, int>>> */
    private const REBA_TABLE_B = [
        1 => [1 => [1 => 1, 2 => 2, 3 => 2], 2 => [1 => 1, 2 => 2, 3 => 3]],
        2 => [1 => [1 => 1, 2 => 2, 3 => 3], 2 => [1 => 2, 2 => 3, 3 => 4]],
        3 => [1 => [1 => 3, 2 => 4, 3 => 5], 2 => [1 => 4, 2 => 5, 3 => 5]],
        4 => [1 => [1 => 4, 2 => 5, 3 => 5], 2 => [1 => 5, 2 => 6, 3 => 7]],
        5 => [1 => [1 => 6, 2 => 7, 3 => 8], 2 => [1 => 7, 2 => 8, 3 => 8]],
        6 => [1 => [1 => 7, 2 => 8, 3 => 8], 2 => [1 => 8, 2 => 9, 3 => 9]],
    ];

    /** @var array<int, array<int, int>> */
    private const REBA_TABLE_C = [
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

    /**
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $result
     * @return list<string>
     */
    public static function trace(string $model, array $inputs, array $result): array
    {
        return match (strtolower($model)) {
            'rula' => self::traceRula($inputs, $result),
            'reba' => self::traceReba($inputs, $result),
            'niosh' => self::traceNiosh($inputs, $result),
            default => ['No debug trace available for model: ' . $model],
        };
    }

    /**
     * @param array<string, mixed> $m
     * @param array<string, mixed> $result
     * @return list<string>
     */
    private static function traceRula(array $m, array $result): array
    {
        $upper = self::rulaUpperArmAdjusted((float) ($m['upper_arm_angle'] ?? 0), $m);
        $lower = self::rulaLowerArmAdjusted((float) ($m['lower_arm_angle'] ?? 0), $m);
        $wrist = self::rulaWristAdjusted((float) ($m['wrist_angle'] ?? 0), $m);
        $twist = self::rulaWristTwist($m['wrist_twist'] ?? null);

        $groupA = self::rulaLookupA($upper, $lower, $wrist, $twist);
        $muscleUse = (!empty($m['static_posture']) || !empty($m['repetitive'])) ? 1 : 0;
        $forceLoad = self::rulaForce((float) ($m['load_weight'] ?? 0), $m);
        $scoreA = max(1, min(8, $groupA + $muscleUse + $forceLoad));

        $neck = self::rulaNeckAdjusted((float) ($m['neck_angle'] ?? 0), $m);
        $trunk = self::rulaTrunkAdjusted((float) ($m['trunk_angle'] ?? 0), $m);
        $legs = self::rulaLegs($m);
        $groupB = self::rulaLookupB($neck, $trunk, $legs);
        $scoreB = max(1, min(7, $groupB + $muscleUse + $forceLoad));

        $grand = self::rulaLookupC($scoreA, $scoreB);

        return [
            'Upper/Lower/Wrist/Twist: ' . $upper . '/' . $lower . '/' . $wrist . '/' . $twist,
            'Group A (Table A): ' . $groupA,
            'Neck/Trunk/Legs: ' . $neck . '/' . $trunk . '/' . $legs,
            'Group B (Table B): ' . $groupB,
            'Muscle use adjustment: +' . $muscleUse,
            'Force/load adjustment: +' . $forceLoad,
            'Score A adjusted: ' . $scoreA,
            'Score B adjusted: ' . $scoreB,
            'Final RULA score (Table C): ' . $grand,
            'Action level: ' . (string) ($result['action_level_code'] ?? 'n/a') . ' | ' . (string) ($result['action_level_label'] ?? 'n/a'),
            'Reported score: ' . (string) ($result['score'] ?? 'n/a'),
        ];
    }

    /**
     * @param array<string, mixed> $m
     * @param array<string, mixed> $result
     * @return list<string>
     */
    private static function traceReba(array $m, array $result): array
    {
        $trunk = self::rebaTrunkAdjusted((float) ($m['trunk_angle'] ?? 0), $m);
        $neck = self::rebaNeckAdjusted((float) ($m['neck_angle'] ?? 0), $m);
        $legs = self::rebaLegs($m);
        $groupA = self::rebaLookupA($trunk, $neck, $legs);

        $load = self::rebaLoad((float) ($m['load_weight'] ?? 0), $m);
        $scoreA = max(1, min(12, $groupA + $load));

        $upperArm = self::rebaUpperArmAdjusted((float) ($m['upper_arm_angle'] ?? 0), $m);
        $lowerArm = self::rebaLowerArm((float) ($m['lower_arm_angle'] ?? 0));
        $wrist = self::rebaWristAdjusted((float) ($m['wrist_angle'] ?? 0), $m);
        $groupB = self::rebaLookupB($upperArm, $lowerArm, $wrist);

        $coupling = self::rebaCoupling((string) ($m['coupling'] ?? 'fair'));
        $scoreB = max(1, min(12, $groupB + $coupling));
        $tableC = self::rebaLookupC($scoreA, $scoreB);

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

        $grand = min(15, max(1, $tableC + $activity));

        return [
            'Trunk/Neck/Legs: ' . $trunk . '/' . $neck . '/' . $legs,
            'Group A (Table A): ' . $groupA,
            'Upper/Lower/Wrist: ' . $upperArm . '/' . $lowerArm . '/' . $wrist,
            'Group B (Table B): ' . $groupB,
            'Load adjustment: +' . $load,
            'Coupling adjustment: +' . $coupling,
            'Activity adjustment: +' . $activity,
            'Score A adjusted: ' . $scoreA,
            'Score B adjusted: ' . $scoreB,
            'Table C score: ' . $tableC,
            'Final REBA score: ' . $grand,
            'Action level: ' . (string) ($result['action_level_code'] ?? 'n/a') . ' | ' . (string) ($result['action_level_label'] ?? 'n/a'),
            'Reported score: ' . (string) ($result['score'] ?? 'n/a'),
        ];
    }

    /**
     * @param array<string, mixed> $m
     * @param array<string, mixed> $result
     * @return list<string>
     */
    private static function traceNiosh(array $m, array $result): array
    {
        $h = max(1.0, (float) ($m['horizontal_distance'] ?? 1));
        $v = (float) ($m['vertical_start'] ?? 75);
        $d = max(1.0, (float) ($m['vertical_travel'] ?? 1));
        $a = (float) ($m['twist_angle'] ?? 0);
        $f = (float) ($m['frequency'] ?? 0);
        $coupling = (string) ($m['coupling'] ?? 'fair');

        $hm = min(1.0, 25.0 / $h);
        $vm = max(0.0, min(1.0, 1.0 - 0.003 * abs($v - 75.0)));
        $dm = min(1.0, 0.82 + 4.5 / $d);
        $am = max(0.0, min(1.0, 1.0 - 0.0032 * $a));
        $fm = self::nioshFrequency($f);
        $cm = self::nioshCoupling($coupling, $v);

        $rwl = 23.0 * $hm * $vm * $dm * $am * $fm * $cm;

        return [
            'HM=' . round($hm, 4) . ', VM=' . round($vm, 4) . ', DM=' . round($dm, 4),
            'AM=' . round($am, 4) . ', FM=' . round($fm, 4) . ', CM=' . round($cm, 4),
            'Calculated RWL: ' . round($rwl, 2),
            'Reported score (LI): ' . (string) ($result['score'] ?? 'n/a'),
        ];
    }

    private static function rulaUpperArmAdjusted(float $angle, array $m): int
    {
        $base = self::rulaUpperArm($angle);
        if (!empty($m['shoulder_raised']) || !empty($m['raised_shoulder'])) {
            $base++;
        }
        if (!empty($m['upper_arm_abducted']) || !empty($m['arm_abducted'])) {
            $base++;
        }
        if (!empty($m['arm_supported']) || !empty($m['leaning'])) {
            $base--;
        }

        return max(1, min(6, $base));
    }

    private static function rulaUpperArm(float $angle): int
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

    private static function rulaLowerArmAdjusted(float $angle, array $m): int
    {
        $base = self::rulaLowerArm($angle);
        if (!empty($m['lower_arm_out_of_plane']) || !empty($m['across_midline']) || !empty($m['out_to_side'])) {
            $base++;
        }

        return max(1, min(3, $base));
    }

    private static function rulaLowerArm(float $angle): int
    {
        return ($angle >= 60 && $angle <= 100) ? 1 : 2;
    }

    private static function rulaWristAdjusted(float $angle, array $m): int
    {
        $base = self::rulaWrist($angle);
        if (!empty($m['wrist_bent_from_midline']) || !empty($m['wrist_deviation'])) {
            $base++;
        }

        return max(1, min(4, $base));
    }

    private static function rulaWrist(float $angle): int
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

    private static function rulaWristTwist(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(1, min(2, (int) $value));
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['end', 'end_range', 'twisted', '2'], true) ? 2 : 1;
        }

        return !empty($value) ? 2 : 1;
    }

    private static function rulaNeckAdjusted(float $angle, array $m): int
    {
        $base = self::rulaNeck($angle);
        if (!empty($m['neck_twisted']) || !empty($m['neck_rotation'])) {
            $base++;
        }
        if (!empty($m['neck_side_bent']) || !empty($m['neck_sidebend'])) {
            $base++;
        }

        return max(1, min(6, $base));
    }

    private static function rulaNeck(float $angle): int
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

    private static function rulaTrunkAdjusted(float $angle, array $m): int
    {
        $base = self::rulaTrunk($angle);
        if (!empty($m['trunk_twisted']) || !empty($m['trunk_rotation'])) {
            $base++;
        }
        if (!empty($m['trunk_side_bent']) || !empty($m['trunk_sidebend'])) {
            $base++;
        }

        return max(1, min(6, $base));
    }

    private static function rulaTrunk(float $angle): int
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

    private static function rulaLegs(array $m): int
    {
        if (isset($m['leg_score']) && is_numeric($m['leg_score'])) {
            return max(1, min(2, (int) $m['leg_score']));
        }

        $legs = strtolower(trim((string) ($m['legs'] ?? 'supported')));
        return in_array($legs, ['supported', 'bilateral', 'both'], true) ? 1 : 2;
    }

    private static function rulaForce(float $kg, array $m): int
    {
        $staticOrRepeated = !empty($m['static_posture']) || !empty($m['repetitive']);
        $shockLoad = !empty($m['shock_load']) || !empty($m['sudden_force']) || !empty($m['rapid_change']);

        if ($kg <= 2) {
            return 0;
        }

        if ($kg <= 10) {
            return $staticOrRepeated ? 2 : 1;
        }

        return $shockLoad ? 3 : 2;
    }

    private static function rulaLookupA(int $upper, int $lower, int $wrist, int $twist): int
    {
        $upper = max(1, min(6, $upper));
        $lower = max(1, min(3, $lower));
        $wrist = max(1, min(4, $wrist));
        $twist = max(1, min(2, $twist));
        $column = (($wrist - 1) * 2) + $twist;

        return self::RULA_TABLE_A[$upper][$lower][$column - 1];
    }

    private static function rulaLookupB(int $neck, int $trunk, int $legs): int
    {
        $neck = max(1, min(6, $neck));
        $trunk = max(1, min(6, $trunk));
        $legs = max(1, min(2, $legs));

        return self::RULA_TABLE_B[$neck][$trunk][$legs];
    }

    private static function rulaLookupC(int $scoreA, int $scoreB): int
    {
        $scoreA = max(1, min(8, $scoreA));
        $scoreB = max(1, min(7, $scoreB));

        return self::RULA_TABLE_C[$scoreA][$scoreB];
    }

    private static function rebaTrunkAdjusted(float $angle, array $m): int
    {
        $base = self::rebaTrunk($angle);
        if (!empty($m['trunk_twisted']) || !empty($m['trunk_rotation'])) {
            $base++;
        }
        if (!empty($m['trunk_side_bent']) || !empty($m['trunk_sidebend'])) {
            $base++;
        }

        return max(1, min(5, $base));
    }

    private static function rebaTrunk(float $angle): int
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

    private static function rebaNeckAdjusted(float $angle, array $m): int
    {
        $base = self::rebaNeck($angle);
        if (!empty($m['neck_twisted']) || !empty($m['neck_rotation'])) {
            $base++;
        }
        if (!empty($m['neck_side_bent']) || !empty($m['neck_sidebend'])) {
            $base++;
        }

        return max(1, min(3, $base));
    }

    private static function rebaNeck(float $angle): int
    {
        if ($angle < 0) {
            return 2;
        }

        return $angle <= 20 ? 1 : 2;
    }

    private static function rebaUpperArmAdjusted(float $angle, array $m): int
    {
        $base = self::rebaUpperArm($angle);
        if (!empty($m['shoulder_raised']) || !empty($m['raised_shoulder'])) {
            $base++;
        }
        if (!empty($m['upper_arm_abducted']) || !empty($m['arm_abducted'])) {
            $base++;
        }
        if (!empty($m['arm_supported']) || !empty($m['leaning'])) {
            $base--;
        }

        return max(1, min(6, $base));
    }

    private static function rebaUpperArm(float $angle): int
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

    private static function rebaLowerArm(float $angle): int
    {
        return ($angle >= 60 && $angle <= 100) ? 1 : 2;
    }

    private static function rebaWristAdjusted(float $angle, array $m): int
    {
        $base = abs($angle) <= 15 ? 1 : 2;
        if (!empty($m['wrist_twist']) || !empty($m['wrist_bent_from_midline']) || !empty($m['wrist_deviation'])) {
            $base++;
        }

        return max(1, min(3, $base));
    }

    private static function rebaLegs(array $m): int
    {
        if (isset($m['leg_score']) && is_numeric($m['leg_score'])) {
            return max(1, min(4, (int) $m['leg_score']));
        }

        $score = 1;
        $legs = strtolower(trim((string) ($m['legs'] ?? 'supported')));
        if (!in_array($legs, ['supported', 'bilateral', 'both'], true)) {
            $score = 2;
        }

        if (isset($m['knee_angle']) && is_numeric($m['knee_angle'])) {
            $knee = abs((float) $m['knee_angle']);
            if ($knee > 60) {
                $score += 2;
            } elseif ($knee > 30) {
                $score += 1;
            }
        }

        return max(1, min(4, $score));
    }

    private static function rebaLoad(float $kg, array $m): int
    {
        $score = 0;
        if ($kg > 10) {
            $score = 2;
        } elseif ($kg >= 5) {
            $score = 1;
        }

        if (!empty($m['shock_load']) || !empty($m['sudden_force'])) {
            $score++;
        }

        return max(0, min(3, $score));
    }

    private static function rebaCoupling(string $coupling): int
    {
        return match (strtolower(trim($coupling))) {
            'good' => 0,
            'fair' => 1,
            'poor' => 2,
            'unacceptable' => 3,
            default => 1,
        };
    }

    private static function rebaLookupA(int $trunk, int $neck, int $legs): int
    {
        $trunk = max(1, min(5, $trunk));
        $neck = max(1, min(3, $neck));
        $legs = max(1, min(4, $legs));

        return self::REBA_TABLE_A[$trunk][$neck][$legs];
    }

    private static function rebaLookupB(int $upper, int $lower, int $wrist): int
    {
        $upper = max(1, min(6, $upper));
        $lower = max(1, min(2, $lower));
        $wrist = max(1, min(3, $wrist));

        return self::REBA_TABLE_B[$upper][$lower][$wrist];
    }

    private static function rebaLookupC(int $scoreA, int $scoreB): int
    {
        $scoreA = max(1, min(12, $scoreA));
        $scoreB = max(1, min(12, $scoreB));

        return self::REBA_TABLE_C[$scoreA][$scoreB];
    }

    private static function nioshFrequency(float $freq): float
    {
        if ($freq <= 0.2) return 1.0;
        if ($freq <= 1) return 0.94;
        if ($freq <= 4) return 0.84;
        if ($freq <= 6) return 0.75;
        if ($freq <= 9) return 0.52;
        if ($freq <= 12) return 0.37;
        return 0.18;
    }

    private static function nioshCoupling(string $coupling, float $verticalStart): float
    {
        $below75 = $verticalStart < 75.0;

        return match ($coupling) {
            'good' => 1.0,
            'fair' => $below75 ? 0.95 : 1.0,
            'poor' => 0.90,
            default => 0.95,
        };
    }
}