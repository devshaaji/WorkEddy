<?php

declare(strict_types=1);

namespace WorkEddy\Services\Ergonomics;

use RuntimeException;

/**
 * RULA – Rapid Upper Limb Assessment
 *
 * Scores upper-body posture risk on a 1-7 scale.
 * Action levels:
 *   1-2 → Acceptable (low)
 *   3-4 → Investigate  (moderate)
 *   5-6 → Soon change  (moderate-high)
 *     7 → Immediate    (high)
 */
final class RulaService implements ErgonomicAssessmentInterface
{
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
        // ── Arm & Wrist (Group A) ────────────────────────────────────
        $upperArm = $this->upperArmScore((float) $m['upper_arm_angle']);
        $lowerArm = $this->lowerArmScore((float) $m['lower_arm_angle']);
        $wrist    = $this->wristScore((float) $m['wrist_angle']);
        $wristTwist = isset($m['wrist_twist']) && $m['wrist_twist'] ? 2 : 1;

        // Simplified Table A lookup (upper_arm, lower_arm, wrist, wrist_twist)
        $groupA = min(8, $upperArm + $lowerArm + $wrist + $wristTwist - 3);

        // Muscle use & force (optional modifiers)
        $muscleUse = (!empty($m['static_posture']) || !empty($m['repetitive'])) ? 1 : 0;
        $forceLoad = $this->forceScore((float) ($m['load_weight'] ?? 0));
        $scoreA    = $groupA + $muscleUse + $forceLoad;

        // ── Neck, Trunk, Legs (Group B) ──────────────────────────────
        $neck  = $this->neckScore((float) $m['neck_angle']);
        $trunk = $this->trunkScore((float) $m['trunk_angle']);
        $legs  = (int) ($m['leg_score'] ?? 1);   // 1 = bilateral support, 2 = unilateral

        $groupB = min(7, $neck + $trunk + $legs - 1);
        $scoreB = $groupB + $muscleUse + $forceLoad;

        // ── Grand Score (simplified Table C) ─────────────────────────
        $grand = min(7, (int) round(($scoreA + $scoreB) / 2));
        $grand = max(1, $grand);

        $riskLevel = $this->getRiskLevel((float) $grand);
        $normalized = min(100.0, round($grand / 7 * 100, 2));

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
        if ($score >= 7) return 'Very High – Immediate change required';
        if ($score >= 5) return 'High – Investigate and change soon';
        if ($score >= 3) return 'Moderate – Investigate further';
        return 'Low – Acceptable posture';
    }

    // ── Angle → sub-score lookups ────────────────────────────────────────

    private function upperArmScore(float $angle): int
    {
        if ($angle <= 20) return 1;
        if ($angle <= 45) return 2;
        if ($angle <= 90) return 3;
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
        if ($abs <= 5) return 1;
        if ($abs <= 15) return 2;
        return 3;
    }

    private function neckScore(float $angle): int
    {
        if ($angle <= 10) return 1;
        if ($angle <= 20) return 2;
        if ($angle > 20)  return 3;
        return 4; // extension
    }

    private function trunkScore(float $angle): int
    {
        if ($angle == 0) return 1;
        if ($angle <= 20) return 2;
        if ($angle <= 60) return 3;
        return 4;
    }

    private function forceScore(float $kg): int
    {
        if ($kg <= 2) return 0;
        if ($kg <= 10) return 1;
        return 2;
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
            $score >= 7 => 'Immediate posture change required. Redesign workstation urgently.',
            $score >= 5 => 'Investigate posture and change soon. Consider workstation adjustments.',
            $score >= 3 => 'Investigate further. Monitor worker and review task setup.',
            default     => 'Posture is acceptable. Continue periodic monitoring.',
        };
    }
}
