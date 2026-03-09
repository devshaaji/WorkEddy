<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WorkEddy\Services\Ergonomics\RulaService;

final class RulaBoundaryThresholdTest extends TestCase
{
    private RulaService $service;

    protected function setUp(): void
    {
        $this->service = new RulaService();
    }

    public function testUpperArmBoundaryThresholds(): void
    {
        $this->assertSame(1, $this->call('upperArmScore', [19.0]));
        $this->assertSame(1, $this->call('upperArmScore', [20.0]));
        $this->assertSame(2, $this->call('upperArmScore', [21.0]));
    }

    public function testLowerArmBoundaryThresholds(): void
    {
        $this->assertSame(2, $this->call('lowerArmScore', [59.0]));
        $this->assertSame(1, $this->call('lowerArmScore', [60.0]));
        $this->assertSame(1, $this->call('lowerArmScore', [100.0]));
        $this->assertSame(2, $this->call('lowerArmScore', [101.0]));
    }

    public function testWristBoundaryThresholds(): void
    {
        $this->assertSame(1, $this->call('wristScore', [0.0]));
        $this->assertSame(2, $this->call('wristScore', [5.0]));
        $this->assertSame(2, $this->call('wristScore', [15.0]));
        $this->assertSame(3, $this->call('wristScore', [16.0]));
    }

    public function testNeckAndTrunkBoundaryThresholds(): void
    {
        $this->assertSame(1, $this->call('neckScore', [10.0]));
        $this->assertSame(2, $this->call('neckScore', [11.0]));
        $this->assertSame(2, $this->call('neckScore', [20.0]));
        $this->assertSame(3, $this->call('neckScore', [21.0]));

        $this->assertSame(1, $this->call('trunkScore', [0.0]));
        $this->assertSame(2, $this->call('trunkScore', [20.0]));
        $this->assertSame(3, $this->call('trunkScore', [21.0]));
        $this->assertSame(3, $this->call('trunkScore', [60.0]));
        $this->assertSame(4, $this->call('trunkScore', [61.0]));
    }

    public function testForceBoundaryThresholds(): void
    {
        $this->assertSame(0, $this->call('forceScore', [2.0]));
        $this->assertSame(1, $this->call('forceScore', [3.0]));
        $this->assertSame(2, $this->call('forceScore', [10.0, ['static_posture' => true]]));
        $this->assertSame(2, $this->call('forceScore', [11.0]));
    }

    private function call(string $method, array $args): int
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);

        return (int) $ref->invokeArgs($this->service, $args);
    }
}