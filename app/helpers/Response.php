<?php

declare(strict_types=1);

namespace WorkEddy\Helpers;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function created(array $data): never
    {
        self::json($data, 201);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(['error' => $message], $status);
    }
}