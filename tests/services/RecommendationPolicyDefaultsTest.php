<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Services\RecommendationPolicyDefaults;

final class RecommendationPolicyDefaultsTest extends TestCase
{
    public function test_defaults_include_required_osha_policy_sections(): void
    {
        $defaults = RecommendationPolicyDefaults::defaults();

        self::assertArrayHasKey('thresholds', $defaults);
        self::assertArrayHasKey('risk_multipliers', $defaults);
        self::assertArrayHasKey('ranking', $defaults);
        self::assertArrayHasKey('feasibility', $defaults);
        self::assertArrayHasKey('interim', $defaults);
        self::assertArrayHasKey('catalog', $defaults);

        self::assertSame(45.0, $defaults['thresholds']['trunk_flexion_high']);
        self::assertTrue($defaults['ranking']['strict_hierarchy']);
        self::assertSame(14, $defaults['interim']['max_days_without_interim']);
        self::assertTrue($defaults['interim']['allow_ppe_interim']);
    }
}
