<?php

declare(strict_types=1);

namespace WorkEddy\Services;

final class ControlRecommendationService
{
    private const ENGINE_VERSION = 'ctrl_rec_v2_0_osha';

    /** @var list<string> */
    private const HIERARCHY_ORDER = ['elimination', 'substitution', 'engineering', 'administrative', 'ppe'];

    private const DEFAULT_POLICY = [
        'thresholds' => [
            'trunk_flexion_high' => 45.0,
            'trunk_flexion_moderate' => 25.0,
            'upper_arm_elevation_high' => 60.0,
            'repetition_high' => 20,
            'lifting_load' => 12.0,
        ],
        'risk_multipliers' => [
            'high' => 1.15,
            'moderate' => 1.0,
            'low' => 0.9,
        ],
        'ranking' => [
            'cost_penalty_factor' => 1.1,
            'impact_penalty_factor' => 0.8,
            'reduction_factor' => 1.0,
            'strict_hierarchy' => true,
            'cost_weight' => ['low' => 1, 'medium' => 3, 'high' => 6],
            'impact_weight' => ['low' => 1, 'medium' => 3, 'high' => 5],
            'hierarchy_bonus' => [
                'elimination' => 7,
                'substitution' => 5,
                'engineering' => 4,
                'administrative' => 2,
                'ppe' => 0,
            ],
        ],
        'feasibility' => [
            'minimum_total_score' => 60.0,
            'minimum_policy_compliance' => 55.0,
            'weights' => [
                'hazard_fit' => 0.20,
                'injury_likelihood_alignment' => 0.15,
                'policy_compliance' => 0.15,
                'worker_burden' => 0.10,
                'industry_recognition' => 0.10,
                'reliability_durability' => 0.10,
                'availability' => 0.10,
                'cost_effectiveness' => 0.10,
            ],
        ],
        'interim' => [
            'max_days_without_interim' => 14,
            'allow_ppe_interim' => true,
        ],
        'catalog' => [],
    ];

    public function version(): string
    {
        return self::ENGINE_VERSION;
    }

    /**
     * @param array<string,mixed> $metrics
     * @param array<string,mixed> $score
     * @param array<string,mixed> $policy
     * @return list<array<string,mixed>>
     */
    public function recommend(string $model, array $metrics, array $score, array $policy = []): array
    {
        $policy = $this->mergedPolicy($policy);
        $normalized = (float) ($score['normalized_score'] ?? 0.0);
        $riskCategory = (string) ($score['risk_category'] ?? 'low');

        $candidates = [];
        $drivers = $this->detectDrivers($metrics, $model, $policy);

        foreach ($drivers as $driver) {
            $candidates = array_merge($candidates, $this->catalogForDriver($driver, $metrics, $normalized, $policy));
        }

        // Keep a fast-deploy administrative baseline while permanent controls are being rolled out.
        $candidates[] = [
            'hierarchy_level' => 'administrative',
            'control_code' => 'ADMIN_MICRO_BREAK_STANDARD',
            'title' => 'Enforce task-level micro-break and rotation standard',
            'expected_risk_reduction_pct' => max(6.0, min(18.0, round($normalized * 0.12, 2))),
            'implementation_cost' => 'low',
            'time_to_deploy_days' => 3,
            'throughput_impact' => 'low',
            'control_type' => 'interim',
            'interim_for_control_code' => null,
            'rationale' => 'Short recovery windows and rotation reduce cumulative exposure for repetitive work.',
            'evidence' => [
                'driver' => 'baseline_cumulative_exposure',
                'model' => $model,
                'normalized_score' => $normalized,
            ],
            'recommendation_engine_version' => $this->version(),
        ];

        $ranked = $this->rankAndDedupe($candidates, $riskCategory, $policy);
        $selected = $this->selectControls($ranked, $policy);

        $result = [];
        $rank = 1;
        foreach (array_slice($selected, 0, 5) as $row) {
            $row['rank_order'] = $rank++;
            $row['recommendation_engine_version'] ??= $this->version();
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $metrics
     * @return list<string>
     */
    private function detectDrivers(array $metrics, string $model, array $policy): array
    {
        $thresholds = $policy['thresholds'] ?? [];

        $drivers = [];

        $trunk = (float) ($metrics['trunk_angle'] ?? 0.0);
        if ($trunk >= (float) ($thresholds['trunk_flexion_high'] ?? 45.0)) {
            $drivers[] = 'trunk_flexion_high';
        } elseif ($trunk >= (float) ($thresholds['trunk_flexion_moderate'] ?? 25.0)) {
            $drivers[] = 'trunk_flexion_moderate';
        }

        $upperArm = (float) ($metrics['upper_arm_angle'] ?? 0.0);
        if ($upperArm >= (float) ($thresholds['upper_arm_elevation_high'] ?? 60.0)) {
            $drivers[] = 'upper_arm_elevation_high';
        }

        $repetition = (int) ($metrics['repetition_count'] ?? 0);
        if ($repetition >= (int) ($thresholds['repetition_high'] ?? 20)) {
            $drivers[] = 'repetition_high';
        }

        $loadWeight = (float) ($metrics['load_weight'] ?? 0.0);
        if ($loadWeight >= (float) ($thresholds['lifting_load'] ?? 12.0) || $model === 'niosh') {
            $drivers[] = 'lifting_load';
        }

        if ($drivers === []) {
            $drivers[] = 'general_risk';
        }

        return array_values(array_unique($drivers));
    }
    /**
     * @param array<string,mixed> $metrics
     * @return list<array<string,mixed>>
     */
    private function catalogForDriver(string $driver, array $metrics, float $normalized, array $policy): array
    {
        $base = match ($driver) {
            'trunk_flexion_high', 'trunk_flexion_moderate' => [
                [
                    'hierarchy_level' => 'elimination',
                    'control_code' => 'ELIM_REMOVE_MANUAL_LOW_LIFT',
                    'title' => 'Eliminate manual low-height lift step via conveyor/auto-feed redesign',
                    'expected_risk_reduction_pct' => 42.0,
                    'implementation_cost' => 'high',
                    'time_to_deploy_days' => 45,
                    'throughput_impact' => 'medium',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Removing the manual lift hazard at source provides the strongest long-term protection.',
                    'evidence' => [
                        'driver' => $driver,
                        'trunk_angle' => (float) ($metrics['trunk_angle'] ?? 0.0),
                        'score_basis' => $normalized,
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'substitution',
                    'control_code' => 'SUB_TRUNK_REPACK_LIGHTER_UNITS',
                    'title' => 'Substitute large units with lighter split loads at source',
                    'expected_risk_reduction_pct' => 24.0,
                    'implementation_cost' => 'medium',
                    'time_to_deploy_days' => 12,
                    'throughput_impact' => 'medium',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Reducing weight and lift geometry decreases trunk flexion demand per cycle.',
                    'evidence' => [
                        'driver' => $driver,
                        'trunk_angle' => (float) ($metrics['trunk_angle'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'engineering',
                    'control_code' => 'ENG_LIFT_ASSIST',
                    'title' => 'Deploy lift-assist or height-adjustable handling station',
                    'expected_risk_reduction_pct' => 28.0,
                    'implementation_cost' => 'high',
                    'time_to_deploy_days' => 21,
                    'throughput_impact' => 'low',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Reducing trunk flexion at source directly lowers spinal loading exposure.',
                    'evidence' => [
                        'driver' => $driver,
                        'trunk_angle' => (float) ($metrics['trunk_angle'] ?? 0.0),
                        'score_basis' => $normalized,
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'administrative',
                    'control_code' => 'ADMIN_WORK_HEIGHT_SETUP',
                    'title' => 'Standardize workstation setup check at shift start',
                    'expected_risk_reduction_pct' => 14.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 2,
                    'throughput_impact' => 'low',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_REMOVE_MANUAL_LOW_LIFT',
                    'rationale' => 'Daily setup checks reduce sustained awkward posture from misaligned work heights.',
                    'evidence' => [
                        'driver' => $driver,
                        'trunk_angle' => (float) ($metrics['trunk_angle'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'ppe',
                    'control_code' => 'PPE_TRUNK_SUPPORT_AND_GRIP',
                    'title' => 'Issue grip-support gloves and reinforce PPE use as interim support',
                    'expected_risk_reduction_pct' => 6.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 1,
                    'throughput_impact' => 'low',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_REMOVE_MANUAL_LOW_LIFT',
                    'rationale' => 'PPE can reduce residual exposure while higher-order controls are implemented.',
                    'evidence' => [
                        'driver' => $driver,
                        'trunk_angle' => (float) ($metrics['trunk_angle'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
            ],
            'upper_arm_elevation_high' => [
                [
                    'hierarchy_level' => 'elimination',
                    'control_code' => 'ELIM_REMOVE_OVERHEAD_PICKS',
                    'title' => 'Eliminate overhead picks by relocating inventory flow to shoulder-safe zones',
                    'expected_risk_reduction_pct' => 36.0,
                    'implementation_cost' => 'high',
                    'time_to_deploy_days' => 30,
                    'throughput_impact' => 'medium',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Removing overhead reach exposure is the highest-order way to reduce shoulder risk.',
                    'evidence' => [
                        'driver' => $driver,
                        'upper_arm_angle' => (float) ($metrics['upper_arm_angle'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'engineering',
                    'control_code' => 'ENG_REACH_REDESIGN',
                    'title' => 'Redesign reach zone to keep picks in shoulder-safe envelope',
                    'expected_risk_reduction_pct' => 22.0,
                    'implementation_cost' => 'medium',
                    'time_to_deploy_days' => 10,
                    'throughput_impact' => 'medium',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Repositioning high-frequency items reduces elevated shoulder postures.',
                    'evidence' => [
                        'driver' => $driver,
                        'upper_arm_angle' => (float) ($metrics['upper_arm_angle'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'administrative',
                    'control_code' => 'ADMIN_OVERHEAD_EXPOSURE_LIMIT',
                    'title' => 'Set exposure limits and rotate workers out of overhead tasks each hour',
                    'expected_risk_reduction_pct' => 12.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 2,
                    'throughput_impact' => 'medium',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_REMOVE_OVERHEAD_PICKS',
                    'rationale' => 'Administrative limits reduce cumulative shoulder loading until permanent redesign is live.',
                    'evidence' => [
                        'driver' => $driver,
                        'upper_arm_angle' => (float) ($metrics['upper_arm_angle'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'ppe',
                    'control_code' => 'PPE_SHOULDER_SUPPORT_PROGRAM',
                    'title' => 'Use shoulder-support PPE and strict fit/use checks as temporary support',
                    'expected_risk_reduction_pct' => 5.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 1,
                    'throughput_impact' => 'low',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_REMOVE_OVERHEAD_PICKS',
                    'rationale' => 'PPE can help with residual stress but should not replace higher-level controls.',
                    'evidence' => [
                        'driver' => $driver,
                        'upper_arm_angle' => (float) ($metrics['upper_arm_angle'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
            ],
            'repetition_high' => [
                [
                    'hierarchy_level' => 'elimination',
                    'control_code' => 'ELIM_AUTOMATE_REPETITIVE_STEP',
                    'title' => 'Eliminate repetitive manual cycle with partial automation',
                    'expected_risk_reduction_pct' => 35.0,
                    'implementation_cost' => 'high',
                    'time_to_deploy_days' => 35,
                    'throughput_impact' => 'medium',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Removing repetitive manual exposure at source gives durable risk reduction.',
                    'evidence' => [
                        'driver' => $driver,
                        'repetition_count' => (int) ($metrics['repetition_count'] ?? 0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'substitution',
                    'control_code' => 'SUB_LOW_FORCE_TOOLING',
                    'title' => 'Substitute high-force tools with low-force ergonomic tooling',
                    'expected_risk_reduction_pct' => 20.0,
                    'implementation_cost' => 'medium',
                    'time_to_deploy_days' => 8,
                    'throughput_impact' => 'low',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Substituting tool design lowers force and repetition burden without waiting for full automation.',
                    'evidence' => [
                        'driver' => $driver,
                        'repetition_count' => (int) ($metrics['repetition_count'] ?? 0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'engineering',
                    'control_code' => 'ENG_SEMI_AUTO_FEED',
                    'title' => 'Install semi-automated feed/assist to reduce manual cycle repetition',
                    'expected_risk_reduction_pct' => 24.0,
                    'implementation_cost' => 'high',
                    'time_to_deploy_days' => 20,
                    'throughput_impact' => 'low',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Engineering assists cut repetitive manual cycles while preserving throughput.',
                    'evidence' => [
                        'driver' => $driver,
                        'repetition_count' => (int) ($metrics['repetition_count'] ?? 0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'administrative',
                    'control_code' => 'ADMIN_JOB_ROTATION',
                    'title' => 'Implement evidence-based rotation cadence for repetitive tasks',
                    'expected_risk_reduction_pct' => 18.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 5,
                    'throughput_impact' => 'medium',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_AUTOMATE_REPETITIVE_STEP',
                    'rationale' => 'Rotation disperses cumulative load and reduces repetitive strain concentration.',
                    'evidence' => [
                        'driver' => $driver,
                        'repetition_count' => (int) ($metrics['repetition_count'] ?? 0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'ppe',
                    'control_code' => 'PPE_ANTI_VIBRATION_GLOVES',
                    'title' => 'Deploy anti-vibration and anti-fatigue gloves with supervised fit checks',
                    'expected_risk_reduction_pct' => 7.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 1,
                    'throughput_impact' => 'low',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_AUTOMATE_REPETITIVE_STEP',
                    'rationale' => 'PPE can lower residual strain while higher-level controls are delivered.',
                    'evidence' => [
                        'driver' => $driver,
                        'repetition_count' => (int) ($metrics['repetition_count'] ?? 0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
            ],
            'lifting_load' => [
                [
                    'hierarchy_level' => 'elimination',
                    'control_code' => 'ELIM_REMOVE_MANUAL_HEAVY_LIFTS',
                    'title' => 'Eliminate manual heavy-lift step using automated transfer and positioning',
                    'expected_risk_reduction_pct' => 44.0,
                    'implementation_cost' => 'high',
                    'time_to_deploy_days' => 40,
                    'throughput_impact' => 'medium',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Hazard elimination removes high-force lifting exposure from the workflow.',
                    'evidence' => [
                        'driver' => $driver,
                        'load_weight' => (float) ($metrics['load_weight'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'substitution',
                    'control_code' => 'SUB_PACK_SIZE_REDUCTION',
                    'title' => 'Reduce unit load per lift (repack or split loads)',
                    'expected_risk_reduction_pct' => 20.0,
                    'implementation_cost' => 'medium',
                    'time_to_deploy_days' => 14,
                    'throughput_impact' => 'medium',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Lowering load weight reduces required lifting index and peak exertion.',
                    'evidence' => [
                        'driver' => $driver,
                        'load_weight' => (float) ($metrics['load_weight'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'engineering',
                    'control_code' => 'ENG_HANDLING_AID',
                    'title' => 'Introduce handling aid for high-load lifts',
                    'expected_risk_reduction_pct' => 26.0,
                    'implementation_cost' => 'high',
                    'time_to_deploy_days' => 18,
                    'throughput_impact' => 'low',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Mechanical assistance lowers spinal and shoulder loading for heavy lifts.',
                    'evidence' => [
                        'driver' => $driver,
                        'load_weight' => (float) ($metrics['load_weight'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'administrative',
                    'control_code' => 'ADMIN_TEAM_LIFT_PROTOCOL',
                    'title' => 'Apply team-lift triggers, lift permits, and pre-lift briefing checks',
                    'expected_risk_reduction_pct' => 13.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 2,
                    'throughput_impact' => 'medium',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_REMOVE_MANUAL_HEAVY_LIFTS',
                    'rationale' => 'Structured procedures reduce peak loading until permanent redesign is complete.',
                    'evidence' => [
                        'driver' => $driver,
                        'load_weight' => (float) ($metrics['load_weight'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'ppe',
                    'control_code' => 'PPE_GRIP_AND_FOOTWEAR_STANDARD',
                    'title' => 'Use grip-focused PPE and footwear controls as temporary support',
                    'expected_risk_reduction_pct' => 6.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 1,
                    'throughput_impact' => 'low',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ELIM_REMOVE_MANUAL_HEAVY_LIFTS',
                    'rationale' => 'PPE reduces residual handling risk while higher-level controls are deployed.',
                    'evidence' => [
                        'driver' => $driver,
                        'load_weight' => (float) ($metrics['load_weight'] ?? 0.0),
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
            ],
            default => [
                [
                    'hierarchy_level' => 'engineering',
                    'control_code' => 'ENG_WORKFLOW_BALANCING',
                    'title' => 'Engineer workflow balancing to remove unnecessary high-strain motions',
                    'expected_risk_reduction_pct' => 16.0,
                    'implementation_cost' => 'medium',
                    'time_to_deploy_days' => 12,
                    'throughput_impact' => 'low',
                    'control_type' => 'permanent',
                    'interim_for_control_code' => null,
                    'rationale' => 'Workflow balancing removes repeat high-strain movement patterns.',
                    'evidence' => [
                        'driver' => 'general_risk',
                        'score_basis' => $normalized,
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'administrative',
                    'control_code' => 'ADMIN_TARGETED_COACHING',
                    'title' => 'Run targeted coaching for high-risk task execution',
                    'expected_risk_reduction_pct' => 10.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 2,
                    'throughput_impact' => 'low',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ENG_WORKFLOW_BALANCING',
                    'rationale' => 'Immediate coaching improves movement quality while engineering changes are planned.',
                    'evidence' => [
                        'driver' => 'general_risk',
                        'score_basis' => $normalized,
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
                [
                    'hierarchy_level' => 'ppe',
                    'control_code' => 'PPE_BASELINE_PROGRAM',
                    'title' => 'Use baseline PPE controls and usage checks as temporary support',
                    'expected_risk_reduction_pct' => 4.0,
                    'implementation_cost' => 'low',
                    'time_to_deploy_days' => 1,
                    'throughput_impact' => 'low',
                    'control_type' => 'interim',
                    'interim_for_control_code' => 'ENG_WORKFLOW_BALANCING',
                    'rationale' => 'PPE provides only supplemental protection and must accompany higher controls.',
                    'evidence' => [
                        'driver' => 'general_risk',
                        'score_basis' => $normalized,
                    ],
                    'recommendation_engine_version' => $this->version(),
                ],
            ],
        };

        $catalog = $policy['catalog'] ?? [];
        $custom = $catalog[$driver] ?? [];
        if (!is_array($custom) || $custom === []) {
            return $base;
        }

        foreach ($custom as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $row['recommendation_engine_version'] = (string) ($row['recommendation_engine_version'] ?? $this->version());
            $row['control_type'] = in_array((string) ($row['control_type'] ?? ''), ['permanent', 'interim'], true)
                ? (string) $row['control_type']
                : 'permanent';
            $row['interim_for_control_code'] = isset($row['interim_for_control_code']) && is_string($row['interim_for_control_code'])
                ? trim($row['interim_for_control_code'])
                : null;
            $row['evidence'] = is_array($row['evidence'] ?? null) ? $row['evidence'] : ['driver' => $driver, 'custom' => true];
        }
        unset($row);

        return array_merge($base, array_values(array_filter($custom, 'is_array')));
    }
    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function rankAndDedupe(array $rows, string $riskCategory, array $policy): array
    {
        $ranking = $policy['ranking'] ?? [];
        $costWeight = is_array($ranking['cost_weight'] ?? null) ? $ranking['cost_weight'] : ['low' => 1, 'medium' => 3, 'high' => 6];
        $impactWeight = is_array($ranking['impact_weight'] ?? null) ? $ranking['impact_weight'] : ['low' => 1, 'medium' => 3, 'high' => 5];
        $hierarchyBonus = is_array($ranking['hierarchy_bonus'] ?? null) ? $ranking['hierarchy_bonus'] : ['elimination' => 7, 'substitution' => 5, 'engineering' => 4, 'administrative' => 2, 'ppe' => 0];
        $costPenaltyFactor = (float) ($ranking['cost_penalty_factor'] ?? 1.1);
        $impactPenaltyFactor = (float) ($ranking['impact_penalty_factor'] ?? 0.8);
        $reductionFactor = (float) ($ranking['reduction_factor'] ?? 1.0);

        $riskMultipliers = $policy['risk_multipliers'] ?? [];
        $riskMultiplier = (float) ($riskMultipliers[$riskCategory] ?? $riskMultipliers['moderate'] ?? 1.0);

        $seen = [];
        $ranked = [];

        foreach ($rows as $row) {
            $code = (string) ($row['control_code'] ?? '');
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $row['control_type'] = in_array((string) ($row['control_type'] ?? ''), ['permanent', 'interim'], true)
                ? (string) $row['control_type']
                : 'permanent';
            $row['interim_for_control_code'] = isset($row['interim_for_control_code']) && is_string($row['interim_for_control_code'])
                ? trim($row['interim_for_control_code'])
                : null;
            $row['evidence'] = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];

            $reduction = (float) ($row['expected_risk_reduction_pct'] ?? 0.0);
            $cost = $costWeight[(string) ($row['implementation_cost'] ?? 'medium')] ?? 3;
            $impact = $impactWeight[(string) ($row['throughput_impact'] ?? 'medium')] ?? 3;
            $hierarchy = $hierarchyBonus[(string) ($row['hierarchy_level'] ?? 'administrative')] ?? 0;

            $feasibility = $this->buildFeasibilityAssessment($row, $riskCategory, $policy);
            $row['feasibility_score'] = $feasibility['total'];
            $row['feasibility_status'] = $feasibility['status'];
            $row['evidence']['osha_feasibility'] = $feasibility;
            $row['evidence']['pros'] = $feasibility['pros'];
            $row['evidence']['cons'] = $feasibility['cons'];

            $feasibilityBonus = ((float) $feasibility['total']) * 0.08;
            $interimPenalty = $row['control_type'] === 'interim' ? 2.0 : 0.0;
            $infeasiblePenalty = ($feasibility['status'] ?? 'conditional') === 'not_feasible' ? 100.0 : 0.0;

            $score = ($reduction * $riskMultiplier * $reductionFactor)
                + $hierarchy
                + $feasibilityBonus
                - ($cost * $costPenaltyFactor)
                - ($impact * $impactPenaltyFactor)
                - $interimPenalty
                - $infeasiblePenalty;

            $row['_rank_score'] = round($score, 4);
            $ranked[] = $row;
        }

        usort($ranked, static fn (array $a, array $b): int => (($b['_rank_score'] ?? 0) <=> ($a['_rank_score'] ?? 0)));

        return $ranked;
    }

    /**
     * @param list<array<string,mixed>> $ranked
     * @return list<array<string,mixed>>
     */
    private function selectControls(array $ranked, array $policy): array
    {
        if ($ranked === []) {
            return [];
        }

        $selected = [];
        $seen = [];
        $strictHierarchy = (bool) (($policy['ranking']['strict_hierarchy'] ?? true) === true);

        $feasible = array_values(array_filter(
            $ranked,
            static fn (array $r): bool => in_array((string) ($r['feasibility_status'] ?? 'conditional'), ['feasible', 'conditional'], true)
        ));
        $strictFeasible = array_values(array_filter(
            $ranked,
            static fn (array $r): bool => (string) ($r['feasibility_status'] ?? '') === 'feasible'
        ));

        if ($strictHierarchy) {
            foreach (self::HIERARCHY_ORDER as $level) {
                $levelRows = array_values(array_filter(
                    $strictFeasible,
                    static fn (array $r): bool => (string) ($r['hierarchy_level'] ?? '') === $level
                ));
                if ($levelRows === []) {
                    continue;
                }

                usort($levelRows, static function (array $a, array $b): int {
                    $aInterim = (string) ($a['control_type'] ?? 'permanent') === 'interim' ? 1 : 0;
                    $bInterim = (string) ($b['control_type'] ?? 'permanent') === 'interim' ? 1 : 0;
                    if ($aInterim !== $bInterim) {
                        return $aInterim <=> $bInterim;
                    }
                    return (($b['_rank_score'] ?? 0) <=> ($a['_rank_score'] ?? 0));
                });

                foreach (array_slice($levelRows, 0, 2) as $row) {
                    $this->addSelection($selected, $seen, $row);
                }
                break;
            }
        }

        if ($selected === []) {
            foreach (array_slice($feasible, 0, 2) as $row) {
                $this->addSelection($selected, $seen, $row);
            }
        }

        if ($this->containsHierarchy($selected, ['elimination', 'substitution', 'engineering'])) {
            $admin = $this->firstMatching(
                $feasible,
                static fn (array $r): bool => (string) ($r['hierarchy_level'] ?? '') === 'administrative'
                    && (string) ($r['control_type'] ?? 'permanent') === 'interim'
            );
            if ($admin !== null) {
                $this->addSelection($selected, $seen, $admin);
            }
        }

        $interimCfg = is_array($policy['interim'] ?? null) ? $policy['interim'] : [];
        $maxDaysWithoutInterim = max(1, (int) ($interimCfg['max_days_without_interim'] ?? 14));
        $allowPpeInterim = (bool) (($interimCfg['allow_ppe_interim'] ?? true) === true);

        foreach ($selected as $row) {
            if ((string) ($row['control_type'] ?? 'permanent') !== 'permanent') {
                continue;
            }

            $deployDays = (int) ($row['time_to_deploy_days'] ?? 0);
            if ($deployDays <= $maxDaysWithoutInterim) {
                continue;
            }

            $permanentCode = (string) ($row['control_code'] ?? '');
            $driver = (string) (($row['evidence']['driver'] ?? '') ?: '');

            $interim = $this->firstMatching(
                $feasible,
                static function (array $candidate) use ($permanentCode, $driver, $allowPpeInterim): bool {
                    if ((string) ($candidate['control_type'] ?? 'permanent') !== 'interim') {
                        return false;
                    }
                    if (!$allowPpeInterim && (string) ($candidate['hierarchy_level'] ?? '') === 'ppe') {
                        return false;
                    }

                    $forCode = (string) ($candidate['interim_for_control_code'] ?? '');
                    if ($forCode !== '' && $forCode === $permanentCode) {
                        return true;
                    }

                    $candidateDriver = (string) (($candidate['evidence']['driver'] ?? '') ?: '');
                    return $driver !== '' && $candidateDriver === $driver;
                }
            );

            if ($interim !== null) {
                $this->addSelection($selected, $seen, $interim);
            }
        }

        foreach ($feasible as $row) {
            if (count($selected) >= 5) {
                break;
            }
            $this->addSelection($selected, $seen, $row);
        }

        if ($selected === []) {
            $selected = array_slice($ranked, 0, 5);
        }

        usort($selected, static fn (array $a, array $b): int => (($b['_rank_score'] ?? 0) <=> ($a['_rank_score'] ?? 0)));
        $selected = array_slice($selected, 0, 5);

        foreach ($selected as &$row) {
            unset($row['_rank_score']);
        }
        unset($row);

        return $selected;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function buildFeasibilityAssessment(array $row, string $riskCategory, array $policy): array
    {
        $feasibilityPolicy = is_array($policy['feasibility'] ?? null) ? $policy['feasibility'] : [];
        $weights = is_array($feasibilityPolicy['weights'] ?? null)
            ? $feasibilityPolicy['weights']
            : self::DEFAULT_POLICY['feasibility']['weights'];
        $minTotal = (float) ($feasibilityPolicy['minimum_total_score'] ?? 60.0);
        $minPolicy = (float) ($feasibilityPolicy['minimum_policy_compliance'] ?? 55.0);

        $criteria = $this->criteriaScores($row, $riskCategory);
        $total = 0.0;
        foreach ($criteria as $key => $value) {
            $weight = (float) ($weights[$key] ?? 0.0);
            $total += ($value * $weight);
        }
        $total = round(max(0.0, min(100.0, $total)), 2);

        $policyCompliance = (float) ($criteria['policy_compliance'] ?? 0.0);
        $status = 'conditional';
        if ($total >= $minTotal && $policyCompliance >= $minPolicy) {
            $status = 'feasible';
        } elseif ($total < ($minTotal * 0.75) || $policyCompliance < ($minPolicy * 0.70)) {
            $status = 'not_feasible';
        }

        [$pros, $cons] = $this->prosAndCons($row);

        return [
            'total' => $total,
            'status' => $status,
            'criteria' => $criteria,
            'minimum_total_score' => round($minTotal, 2),
            'minimum_policy_compliance' => round($minPolicy, 2),
            'notes' => [
                'hierarchy_preference' => 'Controls are prioritized from elimination down to PPE when feasible.',
                'interim_rule' => 'Lower-level interim controls are allowed when permanent controls require longer deployment.',
            ],
            'pros' => $pros,
            'cons' => $cons,
        ];
    }
    /**
     * @param array<string,mixed> $row
     * @return array<string,float>
     */
    private function criteriaScores(array $row, string $riskCategory): array
    {
        $reduction = (float) ($row['expected_risk_reduction_pct'] ?? 0.0);
        $level = (string) ($row['hierarchy_level'] ?? 'administrative');
        $cost = (string) ($row['implementation_cost'] ?? 'medium');
        $impact = (string) ($row['throughput_impact'] ?? 'medium');
        $days = (int) ($row['time_to_deploy_days'] ?? 0);
        $controlType = (string) ($row['control_type'] ?? 'permanent');

        $hierarchyBase = [
            'elimination' => 28.0,
            'substitution' => 22.0,
            'engineering' => 18.0,
            'administrative' => 12.0,
            'ppe' => 8.0,
        ];
        $policyBase = [
            'elimination' => 98.0,
            'substitution' => 92.0,
            'engineering' => 87.0,
            'administrative' => 76.0,
            'ppe' => 62.0,
        ];
        $durabilityBase = [
            'elimination' => 97.0,
            'substitution' => 92.0,
            'engineering' => 88.0,
            'administrative' => 68.0,
            'ppe' => 56.0,
        ];
        $industryBase = [
            'elimination' => 90.0,
            'substitution' => 86.0,
            'engineering' => 83.0,
            'administrative' => 78.0,
            'ppe' => 72.0,
        ];
        $costScore = ['low' => 90.0, 'medium' => 70.0, 'high' => 52.0];
        $impactScore = ['low' => 90.0, 'medium' => 68.0, 'high' => 44.0];
        $riskNeed = ['high' => 20.0, 'moderate' => 12.0, 'low' => 7.0];

        $hazardFit = max(0.0, min(100.0, ($reduction * 2.2) + ($hierarchyBase[$level] ?? 10.0)));

        $need = $riskNeed[$riskCategory] ?? 12.0;
        $injuryAlignment = max(0.0, min(100.0, 100.0 - (abs($reduction - $need) * 3.2)));

        $policyCompliance = (float) ($policyBase[$level] ?? 70.0);
        if ($level === 'ppe' && $controlType !== 'interim') {
            $policyCompliance -= 18.0;
        }
        if ($controlType === 'interim' && in_array($level, ['administrative', 'ppe'], true)) {
            $policyCompliance += 5.0;
        }
        $policyCompliance = max(0.0, min(100.0, $policyCompliance));

        $workerBurden = (($impactScore[$impact] ?? 60.0) * 0.7) + (($costScore[$cost] ?? 60.0) * 0.3);
        if ($level === 'administrative') {
            $workerBurden -= 8.0;
        }
        if ($level === 'ppe') {
            $workerBurden -= 12.0;
        }
        $workerBurden = max(0.0, min(100.0, $workerBurden));

        $industryRecognition = max(0.0, min(100.0, (float) ($industryBase[$level] ?? 75.0)));

        $reliabilityDurability = max(0.0, min(100.0, (float) ($durabilityBase[$level] ?? 65.0)));
        if ($controlType === 'interim') {
            $reliabilityDurability = max(0.0, $reliabilityDurability - 12.0);
        }

        $availabilityByDays = max(10.0, 100.0 - min(70.0, $days * 1.8));
        $availability = max(0.0, min(100.0, (($availabilityByDays * 0.6) + (($costScore[$cost] ?? 60.0) * 0.4))));

        $costWeight = ['low' => 1.0, 'medium' => 2.2, 'high' => 3.6];
        $impactWeight = ['low' => 1.0, 'medium' => 1.8, 'high' => 2.8];
        $effRatio = $reduction / (($costWeight[$cost] ?? 2.2) + ($impactWeight[$impact] ?? 1.8));
        $costEffectiveness = max(0.0, min(100.0, round($effRatio * 14.0, 2)));

        return [
            'hazard_fit' => round($hazardFit, 2),
            'injury_likelihood_alignment' => round($injuryAlignment, 2),
            'policy_compliance' => round($policyCompliance, 2),
            'worker_burden' => round($workerBurden, 2),
            'industry_recognition' => round($industryRecognition, 2),
            'reliability_durability' => round($reliabilityDurability, 2),
            'availability' => round($availability, 2),
            'cost_effectiveness' => round($costEffectiveness, 2),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{0:list<string>,1:list<string>}
     */
    private function prosAndCons(array $row): array
    {
        $pros = [];
        $cons = [];

        $hierarchy = (string) ($row['hierarchy_level'] ?? 'administrative');
        $days = (int) ($row['time_to_deploy_days'] ?? 0);
        $cost = (string) ($row['implementation_cost'] ?? 'medium');
        $impact = (string) ($row['throughput_impact'] ?? 'medium');
        $reduction = (float) ($row['expected_risk_reduction_pct'] ?? 0.0);

        if (in_array($hierarchy, ['elimination', 'substitution', 'engineering'], true)) {
            $pros[] = 'Higher-order control in OSHA hierarchy';
        }
        if ($reduction >= 20.0) {
            $pros[] = 'High expected risk reduction';
        }
        if ($days <= 7) {
            $pros[] = 'Fast deployment';
        }
        if ($impact === 'low') {
            $pros[] = 'Low throughput disruption';
        }
        if ($cost === 'low') {
            $pros[] = 'Low implementation cost';
        }

        if ($days > 21) {
            $cons[] = 'Long implementation lead time';
        }
        if ($cost === 'high') {
            $cons[] = 'High implementation cost';
        }
        if ($impact === 'high') {
            $cons[] = 'High throughput impact';
        }
        if ($hierarchy === 'administrative') {
            $cons[] = 'Relies on sustained procedural compliance';
        }
        if ($hierarchy === 'ppe') {
            $cons[] = 'Lowest-order OSHA control; use only with higher-level controls';
        }

        return [array_values(array_unique($pros)), array_values(array_unique($cons))];
    }

    /**
     * @param list<array<string,mixed>> $selected
     * @param array<string,bool> $seen
     * @param array<string,mixed> $row
     */
    private function addSelection(array &$selected, array &$seen, array $row): void
    {
        $code = (string) ($row['control_code'] ?? '');
        if ($code === '' || isset($seen[$code])) {
            return;
        }
        $seen[$code] = true;
        $selected[] = $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $levels
     */
    private function containsHierarchy(array $rows, array $levels): bool
    {
        foreach ($rows as $row) {
            if (in_array((string) ($row['hierarchy_level'] ?? ''), $levels, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param callable(array<string,mixed>):bool $predicate
     * @return array<string,mixed>|null
     */
    private function firstMatching(array $rows, callable $predicate): ?array
    {
        foreach ($rows as $row) {
            if ($predicate($row)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $policy
     * @return array<string,mixed>
     */
    private function mergedPolicy(array $policy): array
    {
        $merged = self::DEFAULT_POLICY;

        foreach (['thresholds', 'risk_multipliers', 'ranking', 'feasibility', 'interim', 'catalog'] as $key) {
            if (!isset($policy[$key]) || !is_array($policy[$key])) {
                continue;
            }

            if ($key === 'catalog') {
                $merged[$key] = $policy[$key];
                continue;
            }

            $merged[$key] = array_replace($merged[$key], $policy[$key]);
        }

        return $merged;
    }
}
