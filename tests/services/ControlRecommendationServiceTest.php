<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Services\ControlRecommendationService;

final class ControlRecommendationServiceTest extends TestCase
{
    public function testRecommendReturnsRankedControlsWithVersion(): void
    {
        $svc = new ControlRecommendationService();

        $rows = $svc->recommend('reba', [
            'trunk_angle' => 50,
            'upper_arm_angle' => 65,
            'repetition_count' => 22,
            'load_weight' => 14,
        ], [
            'normalized_score' => 60,
            'risk_category' => 'high',
        ]);

        $this->assertNotEmpty($rows);
        $this->assertLessThanOrEqual(5, count($rows));
        $this->assertSame(1, $rows[0]['rank_order']);
        $this->assertArrayHasKey('recommendation_engine_version', $rows[0]);
    }

    public function testPolicyThresholdOverrideChangesDriverActivation(): void
    {
        $svc = new ControlRecommendationService();

        $defaultRows = $svc->recommend('reba', [
            'trunk_angle' => 30,
            'upper_arm_angle' => 10,
            'repetition_count' => 0,
            'load_weight' => 0,
        ], [
            'normalized_score' => 30,
            'risk_category' => 'moderate',
        ]);

        $strictRows = $svc->recommend('reba', [
            'trunk_angle' => 30,
            'upper_arm_angle' => 10,
            'repetition_count' => 0,
            'load_weight' => 0,
        ], [
            'normalized_score' => 30,
            'risk_category' => 'moderate',
        ], [
            'thresholds' => [
                'trunk_flexion_moderate' => 35,
                'trunk_flexion_high' => 60,
            ],
        ]);

        $defaultCodes = array_column($defaultRows, 'control_code');
        $strictCodes = array_column($strictRows, 'control_code');

        $this->assertContains('ADMIN_WORK_HEIGHT_SETUP', $defaultCodes);
        $this->assertNotContains('ADMIN_WORK_HEIGHT_SETUP', $strictCodes);
    }

    public function testPolicyCatalogSupportsCustomControls(): void
    {
        $svc = new ControlRecommendationService();

        $rows = $svc->recommend('reba', [
            'trunk_angle' => 10,
            'upper_arm_angle' => 10,
            'repetition_count' => 0,
            'load_weight' => 0,
        ], [
            'normalized_score' => 25,
            'risk_category' => 'low',
        ], [
            'catalog' => [
                'general_risk' => [
                    [
                        'hierarchy_level' => 'administrative',
                        'control_code' => 'ADMIN_TEAM_STRETCH',
                        'title' => 'Team warm-up stretch protocol',
                        'expected_risk_reduction_pct' => 9.0,
                        'implementation_cost' => 'low',
                        'time_to_deploy_days' => 1,
                        'throughput_impact' => 'low',
                        'rationale' => 'Reduces stiffness before repetitive work',
                        'evidence' => ['driver' => 'general_risk'],
                    ],
                ],
            ],
        ]);

        $codes = array_column($rows, 'control_code');
        $this->assertContains('ADMIN_TEAM_STRETCH', $codes);
    }

    public function testOrgWeightingCanPrioritizeLowerCostControls(): void
    {
        $svc = new ControlRecommendationService();

        $customCatalog = [
            'catalog' => [
                'general_risk' => [
                    [
                        'hierarchy_level' => 'administrative',
                        'control_code' => 'CUSTOM_HIGH_REDUCTION_HIGH_COST',
                        'title' => 'High reduction expensive control',
                        'expected_risk_reduction_pct' => 45.0,
                        'implementation_cost' => 'high',
                        'time_to_deploy_days' => 10,
                        'throughput_impact' => 'medium',
                        'rationale' => 'Test control A',
                        'evidence' => ['driver' => 'general_risk'],
                    ],
                    [
                        'hierarchy_level' => 'administrative',
                        'control_code' => 'CUSTOM_LOWER_REDUCTION_LOW_COST',
                        'title' => 'Lower reduction cheaper control',
                        'expected_risk_reduction_pct' => 16.0,
                        'implementation_cost' => 'low',
                        'time_to_deploy_days' => 2,
                        'throughput_impact' => 'low',
                        'rationale' => 'Test control B',
                        'evidence' => ['driver' => 'general_risk'],
                    ],
                ],
            ],
        ];

        $defaultRows = $svc->recommend('reba', [
            'trunk_angle' => 10,
            'upper_arm_angle' => 10,
            'repetition_count' => 0,
            'load_weight' => 0,
        ], [
            'normalized_score' => 50,
            'risk_category' => 'moderate',
        ], $customCatalog);

        $costSensitiveRows = $svc->recommend('reba', [
            'trunk_angle' => 10,
            'upper_arm_angle' => 10,
            'repetition_count' => 0,
            'load_weight' => 0,
        ], [
            'normalized_score' => 50,
            'risk_category' => 'moderate',
        ], $customCatalog + [
            'ranking' => [
                'cost_penalty_factor' => 8.0,
                'impact_penalty_factor' => 2.0,
                'reduction_factor' => 0.8,
            ],
        ]);

        $topDefault = $defaultRows[0]['control_code'];
        $topCostSensitive = $costSensitiveRows[0]['control_code'];

        $this->assertNotSame($topDefault, $topCostSensitive);
        $this->assertSame('CUSTOM_LOWER_REDUCTION_LOW_COST', $topCostSensitive);
    }

    public function testHighRiskSelectionPrioritizesHighestFeasibleHierarchyWithInterimSupport(): void
    {
        $svc = new ControlRecommendationService();

        $rows = $svc->recommend('reba', [
            'trunk_angle' => 52,
            'upper_arm_angle' => 20,
            'repetition_count' => 8,
            'load_weight' => 6,
        ], [
            'normalized_score' => 72,
            'risk_category' => 'high',
        ]);

        $this->assertNotEmpty($rows);
        $this->assertSame('elimination', $rows[0]['hierarchy_level']);

        $interimRows = array_values(array_filter(
            $rows,
            static fn (array $r): bool => ($r['control_type'] ?? 'permanent') === 'interim'
        ));
        $this->assertNotEmpty($interimRows);
        $this->assertNotEmpty(array_filter(
            $interimRows,
            static fn (array $r): bool => !empty($r['interim_for_control_code'])
        ));
    }

    public function testRecommendationsIncludeOshaFeasibilityMetadata(): void
    {
        $svc = new ControlRecommendationService();

        $rows = $svc->recommend('reba', [
            'trunk_angle' => 38,
            'upper_arm_angle' => 15,
            'repetition_count' => 5,
            'load_weight' => 4,
        ], [
            'normalized_score' => 45,
            'risk_category' => 'moderate',
        ]);

        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertArrayHasKey('feasibility_score', $first);
        $this->assertArrayHasKey('feasibility_status', $first);
        $this->assertArrayHasKey('evidence', $first);
        $this->assertArrayHasKey('osha_feasibility', $first['evidence']);
    }
}
