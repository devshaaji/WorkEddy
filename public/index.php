<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use FastRoute\Dispatcher;
use WorkEddy\Core\Container;
use WorkEddy\Core\Logger;
use WorkEddy\Helpers\Response;

// ─── Security headers (universal) ─────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (getenv('APP_ENV') === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$logger = Logger::make();

// ─── Parse request ────────────────────────────────────────────────────────────
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isApi  = str_starts_with($path, '/api/v1');

function requestBody(): array
{
    if (!empty($_POST) || !empty($_FILES)) {
        return is_array($_POST) ? $_POST : [];
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

try {
    // ─── Build one dispatcher with grouped routes ─────────────────────────
    $container  = new Container();
    $apiRoutes  = require __DIR__ . '/../routes/api.php';   // fn(Container): Closure
    $webRoutes  = require __DIR__ . '/../routes/web.php';   // Closure(RouteCollector): void

    $dispatcher = FastRoute\simpleDispatcher(
        function (FastRoute\RouteCollector $r) use ($container, $apiRoutes, $webRoutes): void {
            // Web routes — root level (no prefix)
            $webRoutes($r);

            // API routes — grouped under /api/v1
            $r->addGroup('/api/v1', $apiRoutes($container));
        }
    );

    // ─── Dispatch ─────────────────────────────────────────────────────────
    $route = $dispatcher->dispatch($method, $path);

    if ($isApi) {
        // ── API response ──────────────────────────────────────────────────
        header('Content-Type: application/json; charset=utf-8');
        $container->rateLimiter()->handle($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        match ($route[0]) {
            Dispatcher::NOT_FOUND          => Response::error('Not found', 404),
            Dispatcher::METHOD_NOT_ALLOWED => Response::error('Method not allowed', 405),
            default                        => null,
        };

        [, $handler, $vars] = $route;
        $handler($vars, requestBody());
    } else {
        // ── Web (HTML) response ───────────────────────────────────────────
        header('Content-Type: text/html; charset=utf-8');

        if ($route[0] === Dispatcher::NOT_FOUND) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            exit;
        }
        if ($route[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            http_response_code(405);
            require __DIR__ . '/../views/errors/405.php';
            exit;
        }

        [, $handler, $vars] = $route;
        $handler($vars);
    }

} catch (\Throwable $e) {
    $logger->error('request_failed', [
        'path'   => $path,
        'method' => $method,
        'error'  => $e->getMessage(),
    ]);

    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        Response::error($e->getMessage());
    } else {
        http_response_code(500);
        require __DIR__ . '/../views/errors/500.php';
    }
}
