<?php

declare(strict_types=1);

namespace WorkEddy\Services\Ergonomics;

use RuntimeException;

/**
 * REBA – Rapid Entire Body Assessment
 *
 * Scores whole-body posture risk on a 1-15 scale.
 * Action levels:
 *   1     → Negligible (low)
 *   2-3   → Low – may need change
 *   4-7   → Medium – investigate (moderate)
 *   8-10  → High – investigate and change (high)
 *   11-15 → Very High – immediate (high)
 */
final class RebaService implements ErgonomicAssessmentInterface
{
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
        // ── Group A: Trunk + Neck + Legs ─────────────────────────────
        $trunk = $this->trunkScore((float) $m['trunk_angle']);
        $neck  = $this->neckScore((float) $m['neck_angle']);
        $legs  = (int) ($m['leg_score'] ?? 1);

        $groupA = min(9, $trunk + $neck + $legs);

        // Force / load modifier
        $load = $this->loadScore((float) ($m['load_weight'] ?? 0));
        $scoreA = $groupA + $load;

        // ── Group B: Upper Arm + Lower Arm + Wrist ───────────────────
        $upperArm = $this->upperArmScore((float) $m['upper_arm_angle']);
        $lowerArm = $this->lowerArmScore((float) $m['lower_arm_angle']);
        $wrist    = $this->wristScore((float) $m['wrist_angle']);

        $groupB = min(9, $upperArm + $lowerArm + $wrist);

        // Coupling modifier
        $coupling = $this->couplingScore($m['coupling'] ?? 'good');
        $scoreB   = $groupB + $coupling;

        // ── Table C: combine ─────────────────────────────────────────
        $tableC = min(12, (int) round(($scoreA + $scoreB) / 2));

        // Activity modifier
        $activity = 0;
        if (!empty($m['static_posture']))  $activity++;
        if (!empty($m['repetitive']))      $activity++;
        if (!empty($m['rapid_change']))    $activity++;

        $grand = min(15, max(1, $tableC + $activity));

        $riskLevel  = $this->getRiskLevel((float) $grand);
        $normalized = min(100.0, round($grand / 15 * 100, 2));

        return [
            'score'            => $grand,
            'risk_level'       => $riskLevel,
            'recommendation'   => $this->recommendation($grand),
            'raw_score'        => (float) $grand,
            'normalized_score' => $normalized,
            'risk_category'    => $this->getRiskCategory($normalized),
        ];
    }

    public function getRiskLevel(float $score): string
    {
        if ($score >= 11) return 'Very High – Immediate action required';
        if ($score >= 8)  return 'High – Investigate and implement change';
        if ($score >= 4)  return 'Medium – Further investigation needed';
        if ($score >= 2)  return 'Low – Change may be needed';
        return 'Negligible – No action required';
    }

    // ── Sub-score lookups ────────────────────────────────────────────────

    private function trunkScore(float $angle): int
    {
        if ($angle == 0)   return 1;
        if ($angle <= 20)  return 2;
        if ($angle <= 60)  return 3;
        return 4;
    }

    private function neckScore(float $angle): int
    {
        if ($angle <= 20)  return 1;
        return 2;
    }

    private function upperArmScore(float $angle): int
    {
        if ($angle <= 20)  return 1;
        if ($angle <= 45)  return 2;
        if ($angle <= 90)  return 3;
        return 4;
    }

    private function lowerArmScore(float $angle): int
    {
        if ($angle >= 60 && $angle <= 100) return 1;
        return 2;
    }

    private function wristScore(float $angle): int
    {
        $abs = abs($angle);
        if ($abs <= 15) return 1;
        return 2;
    }

    private function loadScore(float $kg): int
    {
        if ($kg <= 5)  return 0;
        if ($kg <= 10) return 1;
        return 2;
    }

    private function couplingScore(string $coupling): int
    {
        return match ($coupling) {
            'good'         => 0,
            'fair'         => 1,
            'poor'         => 2,
            'unacceptable' => 3,
            default        => 1,
        };
    }

    private function getRiskCategory(float $normalized): string
    {
        if ($normalized >= 70) return 'high';
        if ($normalized >= 40) return 'moderate';
        return 'low';
    }

    private function recommendation(int $score): string
    {
        return match (true) {
            $score >= 11 => 'Immediate action required. Task poses very high ergonomic risk.',
            $score >= 8  => 'High risk detected. Investigate and implement changes promptly.',
            $score >= 4  => 'Medium risk. Further investigation and monitoring recommended.',
            $score >= 2  => 'Low risk. Some improvements may be beneficial.',
            default      => 'Negligible risk. Continue periodic assessment.',
        };
    }
}
