<?php

declare(strict_types=1);

namespace WorkEddy\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonoLogger;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class Logger
{
    public static function make(string $channel = 'workeddy'): LoggerInterface
    {
        if (class_exists(MonoLogger::class)) {
            $log = new MonoLogger($channel);
            $log->pushHandler(new StreamHandler('php://stdout', Level::Info));
            $log->pushHandler(new StreamHandler(
                dirname(__DIR__, 2) . '/storage/logs/app.log',
                Level::Warning
            ));
            return $log;
        }

        // Fallback if Monolog is not yet installed
        return new class extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                error_log(sprintf('[%s] %s %s', $level, (string) $message, json_encode($context)));
            }
        };
    }
}
