<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

final class RiskScoringService
{
    public function scoreManual(array $input): array
    {
        $weight = (float) $input['weight'];
        $frequency = (float) $input['frequency'];
        $duration = (float) $input['duration'];
        $trunkAngle = (float) $input['trunk_angle_estimate'];
        $twisting = (bool) $input['twisting'];
        $overhead = (bool) $input['overhead'];
        $repetition = (float) $input['repetition'];

        $score = 0.0;
        $score += $weight * 1.1;
        $score += $frequency * 1.3;
        $score += $duration * 0.6;
        $score += $trunkAngle * 0.5;
        $score += $repetition * 1.2;
        $score += $twisting ? 8.0 : 0.0;
        $score += $overhead ? 10.0 : 0.0;

        $normalized = min(100.0, max(0.0, round($score, 2)));

        return [
            'raw_score' => round($score, 2),
            'normalized_score' => $normalized,
            'risk_category' => $this->category($normalized),
        ];
    }

    public function category(float $normalizedScore): string
    {
        if ($normalizedScore >= 70.0) {
            return 'high';
        }

        if ($normalizedScore >= 40.0) {
            return 'moderate';
        }

        return 'low';
    }
}
