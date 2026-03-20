<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Api;

use FastRoute\Dispatcher;
use PHPUnit\Framework\TestCase;
use WorkEddy\Core\Container;

final class CopilotAndControlActionRouteTest extends TestCase
{
    public function testControlActionRoutesAreRegistered(): void
    {
        $routesFactory = require __DIR__ . '/../../routes/api.php';
        $container = new Container();
        $dispatcher = \FastRoute\simpleDispatcher($routesFactory($container));

        $this->assertSame(Dispatcher::FOUND, $dispatcher->dispatch('GET', '/control-actions')[0]);
        $this->assertSame(Dispatcher::FOUND, $dispatcher->dispatch('POST', '/control-actions/from-control')[0]);
        $this->assertSame(Dispatcher::FOUND, $dispatcher->dispatch('PUT', '/control-actions/55')[0]);
        $this->assertSame(Dispatcher::FOUND, $dispatcher->dispatch('POST', '/control-actions/55/verify')[0]);
    }

    public function testCopilotRouteIsRegistered(): void
    {
        $routesFactory = require __DIR__ . '/../../routes/api.php';
        $container = new Container();
        $dispatcher = \FastRoute\simpleDispatcher($routesFactory($container));

        foreach (['supervisor', 'safety-manager', 'engineer', 'auditor'] as $persona) {
            $routeInfo = $dispatcher->dispatch('POST', '/copilot/' . $persona);
            $this->assertSame(Dispatcher::FOUND, $routeInfo[0]);
            $this->assertIsCallable($routeInfo[1]);
        }
    }
}
