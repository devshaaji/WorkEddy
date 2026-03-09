<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Api;

use FastRoute\Dispatcher;
use PHPUnit\Framework\TestCase;
use WorkEddy\Core\Container;

final class BillingApiIntegrationTest extends TestCase
{
    public function testBillingRoutesAreRegistered(): void
    {
        $routesFactory = require __DIR__ . '/../../routes/api.php';
        $container = new Container();
        $dispatcher = \FastRoute\simpleDispatcher($routesFactory($container));

        $usage = $dispatcher->dispatch('GET', '/billing/usage');
        $plans = $dispatcher->dispatch('GET', '/billing/plans');
        $invoices = $dispatcher->dispatch('GET', '/billing/invoices');
        $charge = $dispatcher->dispatch('POST', '/billing/invoices/15/charge');

        $this->assertSame(Dispatcher::FOUND, $usage[0]);
        $this->assertSame(Dispatcher::FOUND, $plans[0]);
        $this->assertSame(Dispatcher::FOUND, $invoices[0]);
        $this->assertSame(Dispatcher::FOUND, $charge[0]);
    }
}
