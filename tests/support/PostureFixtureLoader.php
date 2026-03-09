<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Support;

use RuntimeException;

final class PostureFixtureLoader
{
    /**
     * @return list<array{path: string, data: array<string, mixed>}>
     */
    public static function loadManualCases(string $baseDir): array
    {
        $files = array_merge(
            glob(rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . 'rula_case_*.json') ?: [],
            glob(rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . 'reba_case_*.json') ?: []
        );

        sort($files, SORT_NATURAL);

        $cases = [];
        foreach ($files as $file) {
            $cases[] = [
                'path' => $file,
                'data' => self::decodeFixture($file),
            ];
        }

        return $cases;
    }

    /**
     * @return list<array{path: string, data: array<string, mixed>}>
     */
    public static function loadPoseCases(string $baseDir): array
    {
        $files = glob(rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . 'pose' . DIRECTORY_SEPARATOR . '*.json') ?: [];
        sort($files, SORT_NATURAL);

        $cases = [];
        foreach ($files as $file) {
            $cases[] = [
                'path' => $file,
                'data' => self::decodeFixture($file),
            ];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeFixture(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read fixture: ' . $path);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON fixture: ' . $path);
        }

        foreach (['name', 'model'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new RuntimeException("Fixture missing required key '{$required}': {$path}");
            }
        }

        return $data;
    }
}
