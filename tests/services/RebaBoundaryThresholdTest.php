<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WorkEddy\Services\Ergonomics\RebaService;

final class RebaBoundaryThresholdTest extends TestCase
{
    private RebaService $service;

    protected function setUp(): void
    {
        $this->service = new RebaService();
    }

    public function testTrunkBoundaryThresholds(): void
    {
        $this->assertSame(1, $this->call('trunkScore', [0.0]));
        $this->assertSame(2, $this->call('trunkScore', [20.0]));
        $this->assertSame(3, $this->call('trunkScore', [21.0]));
        $this->assertSame(3, $this->call('trunkScore', [60.0]));
        $this->assertSame(4, $this->call('trunkScore', [61.0]));
    }

    public function testNeckBoundaryThresholds(): void
    {
        $this->assertSame(1, $this->call('neckScore', [20.0]));
        $this->assertSame(2, $this->call('neckScore', [21.0]));
    }

    public function testUpperArmBoundaryThresholds(): void
    {
        $this->assertSame(1, $this->call('upperArmScore', [20.0]));
        $this->assertSame(2, $this->call('upperArmScore', [21.0]));
        $this->assertSame(2, $this->call('upperArmScore', [45.0]));
        $this->assertSame(3, $this->call('upperArmScore', [46.0]));
        $this->assertSame(3, $this->call('upperArmScore', [90.0]));
        $this->assertSame(4, $this->call('upperArmScore', [91.0]));
    }

    public function testLowerArmAndWristBoundaryThresholds(): void
    {
        $this->assertSame(2, $this->call('lowerArmScore', [59.0]));
        $this->assertSame(1, $this->call('lowerArmScore', [60.0]));
        $this->assertSame(1, $this->call('lowerArmScore', [100.0]));
        $this->assertSame(2, $this->call('lowerArmScore', [101.0]));

        $this->assertSame(1, $this->call('wristScore', [15.0]));
        $this->assertSame(2, $this->call('wristScore', [16.0]));
    }

    public function testLoadAndCouplingThresholds(): void
    {
        $this->assertSame(0, $this->call('loadScore', [4.99]));
        $this->assertSame(1, $this->call('loadScore', [5.0]));
        $this->assertSame(1, $this->call('loadScore', [10.0]));
        $this->assertSame(2, $this->call('loadScore', [11.0]));

        $this->assertSame(0, $this->call('couplingScore', ['good']));
        $this->assertSame(1, $this->call('couplingScore', ['fair']));
        $this->assertSame(2, $this->call('couplingScore', ['poor']));
        $this->assertSame(3, $this->call('couplingScore', ['unacceptable']));
    }

    private function call(string $method, array $args): int
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);

        return (int) $ref->invokeArgs($this->service, $args);
    }
}