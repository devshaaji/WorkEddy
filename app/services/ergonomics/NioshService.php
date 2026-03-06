<?php

declare(strict_types=1);

namespace WorkEddy\Services\Ergonomics;

use RuntimeException;

/**
 * NIOSH Revised Lifting Equation (1991).
 *
 * Calculates the Recommended Weight Limit (RWL) and Lifting Index (LI).
 * LI > 1.0 means risk exceeds the NIOSH guideline.
 *
 * NIOSH is manual-input only — video cannot provide the required variables.
 */
final class NioshService implements ErgonomicAssessmentInterface
{
    /** Load constant — the maximum acceptable weight under ideal conditions (kg). */
    private const LC = 23.0;

    public function modelName(): string { return 'niosh'; }

    public function supportedInputTypes(): array { return ['manual']; }

    public function validate(array $m): void
    {
        $required = ['load_weight', 'horizontal_distance', 'vertical_start', 'vertical_travel', 'twist_angle', 'frequency'];
        foreach ($required as $f) {
            if (!isset($m[$f]) && !is_numeric($m[$f] ?? null)) {
                throw new RuntimeException("NIOSH requires field: {$f}");
            }
        }
    }

    public function calculateScore(array $m): array
    {
        $H = max(1.0, (float) $m['horizontal_distance']);  // cm
        $V = (float) $m['vertical_start'];                 // cm from floor
        $D = max(1.0, (float) $m['vertical_travel']);       // cm
        $A = (float) $m['twist_angle'];                    // degrees
        $F = (float) $m['frequency'];                      // lifts / minute
        $coupling = $m['coupling'] ?? 'fair';
        $load     = (float) $m['load_weight'];             // kg

        // ── Multiplier factors ──────────────────────────────────────
        $HM = min(1.0, 25.0 / $H);
        $VM = 1.0 - 0.003 * abs($V - 75.0);
        $VM = max(0.0, min(1.0, $VM));
        $DM = 0.82 + 4.5 / $D;
        $DM = min(1.0, $DM);
        $AM = 1.0 - 0.0032 * $A;
        $AM = max(0.0, min(1.0, $AM));
        $FM = $this->frequencyMultiplier($F);
        $CM = $this->couplingMultiplier($coupling, $V);

        // ── Recommended Weight Limit ────────────────────────────────
        $rwl = self::LC * $HM * $VM * $DM * $AM * $FM * $CM;
        $rwl = max(0.01, round($rwl, 2));

        // ── Lifting Index ───────────────────────────────────────────
        $li = round($load / $rwl, 2);

        $riskLevel  = $this->getRiskLevel($li);
        $normalized = min(100.0, max(0.0, round($li / 3 * 100, 2))); // LI 3.0 = 100%

        return [
            'score'            => $li,
            'risk_level'       => $riskLevel,
            'recommendation'   => $this->recommendation($li, $rwl),
            'raw_score'        => $li,
            'normalized_score' => $normalized,
            'risk_category'    => $this->getRiskCategory($normalized),
            'rwl'              => $rwl,
            'lifting_index'    => $li,
        ];
    }

    public function getRiskLevel(float $li): string
    {
        if ($li >= 3.0) return 'High – Significant risk of injury';
        if ($li >= 1.0) return 'Moderate – Exceeds recommended limit';
        return 'Low – Within acceptable range';
    }

    // ── NIOSH multiplier tables ─────────────────────────────────────────

    private function frequencyMultiplier(float $freq): float
    {
        if ($freq <= 0.2) return 1.0;
        if ($freq <= 1)   return 0.94;
        if ($freq <= 4)   return 0.84;
        if ($freq <= 6)   return 0.75;
        if ($freq <= 9)   return 0.52;
        if ($freq <= 12)  return 0.37;
        return 0.18;
    }

    private function couplingMultiplier(string $coupling, float $verticalStart): float
    {
        $below75 = $verticalStart < 75.0;
        return match ($coupling) {
            'good' => 1.0,
            'fair' => $below75 ? 0.95 : 1.0,
            'poor' => $below75 ? 0.90 : 0.90,
            default => 0.95,
        };
    }

    private function getRiskCategory(float $normalized): string
    {
        if ($normalized >= 70) return 'high';
        if ($normalized >= 40) return 'moderate';
        return 'low';
    }

    private function recommendation(float $li, float $rwl): string
    {
        $rwlStr = number_format($rwl, 1);
        return match (true) {
            $li >= 3.0 => "High risk lifting (LI={$li}). Reduce load below {$rwlStr} kg or redesign the task.",
            $li >= 1.0 => "Lifting index exceeds 1.0 (LI={$li}). Consider reducing load, improving coupling, or adjusting task layout. RWL={$rwlStr} kg.",
            default    => "Lifting is within NIOSH guidelines (LI={$li}). RWL={$rwlStr} kg.",
        };
    }
}
