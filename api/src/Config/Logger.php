<?php

declare(strict_types=1);

namespace WorkEddy\Api\Config;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonoLogger;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class Logger
{
    public static function make(): LoggerInterface
    {
        if (class_exists(MonoLogger::class)) {
            $logger = new MonoLogger('workeddy-api');
            $logger->pushHandler(new StreamHandler('php://stdout', Level::Info));
            return $logger;
        }

        return new class extends AbstractLogger {
            public function log($level, $message, array $context = []): void
            {
                error_log(sprintf('[%s] %s %s', $level, (string) $message, json_encode($context)));
            }
        };
    }
}
