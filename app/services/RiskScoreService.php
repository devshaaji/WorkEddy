<?php

declare(strict_types=1);

namespace WorkEddy\Services;

/**
 * @deprecated Use \WorkEddy\Services\Ergonomics\AssessmentEngine instead.
 *
 * This legacy service is kept for backward compatibility with older code
 * paths. All new scan creation flows use the AssessmentEngine which
 * supports RULA, REBA, and NIOSH models.
 */
final class RiskScoreService
{
    /**
     * Calculate risk score from manual ergonomic inputs.
     *
     * @deprecated Use AssessmentEngine::assess('reba', $metrics) instead.
     */
    public function scoreManual(array $input): array
    {
        $weight     = (float) $input['weight'];
        $frequency  = (float) $input['frequency'];
        $duration   = (float) $input['duration'];
        $trunkAngle = (float) $input['trunk_angle_estimate'];
        $twisting   = (bool)  $input['twisting'];
        $overhead   = (bool)  $input['overhead'];
        $repetition = (float) $input['repetition'];

        $score  = 0.0;
        $score += $weight     * 1.1;
        $score += $frequency  * 1.3;
        $score += $duration   * 0.6;
        $score += $trunkAngle * 0.5;
        $score += $repetition * 1.2;
        $score += $twisting   ? 8.0  : 0.0;
        $score += $overhead   ? 10.0 : 0.0;

        $normalized = min(100.0, max(0.0, round($score, 2)));

        return [
            'raw_score'        => round($score, 2),
            'normalized_score' => $normalized,
            'risk_category'    => $this->category($normalized),
        ];
    }

    /**
     * Calculate risk score from video-derived pose metrics.
     *
     * @deprecated Use AssessmentEngine::assess('reba', $metrics) instead.
     */
    public function scoreVideo(float $maxTrunkAngle, float $avgTrunkAngle, float $shoulderElevationDuration, int $repetitionCount): array
    {
        $score = 0.0;

        if ($maxTrunkAngle > 60)       $score += 30;
        elseif ($maxTrunkAngle > 45)   $score += 20;
        elseif ($maxTrunkAngle > 20)   $score += 10;

        if ($shoulderElevationDuration > 0.30)   $score += 20;
        elseif ($shoulderElevationDuration > 0.15) $score += 10;

        if ($repetitionCount >= 25)    $score += 15;
        elseif ($repetitionCount >= 10) $score += 8;

        // Sustained awkward posture penalty (parity with Python ergonomic_rules.py)
        if ($avgTrunkAngle > 30.0) {
            $score += 5;
        }

        $normalized = min(100.0, max(0.0, round($score, 2)));

        return [
            'raw_score'        => round($score, 2),
            'normalized_score' => $normalized,
            'risk_category'    => $this->category($normalized),
        ];
    }

    public function category(float $score): string
    {
        if ($score >= 70.0) return 'high';
        if ($score >= 40.0) return 'moderate';
        return 'low';
    }
}