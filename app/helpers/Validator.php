<?php

declare(strict_types=1);

namespace WorkEddy\Helpers;

use InvalidArgumentException;

final class Validator
{
    /**
     * Assert that all listed field names are present and non-empty in $data.
     *
     * @param string[] $fields
     */
    public static function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    /**
     * Assert the value is a positive number.
     */
    public static function positiveNumber(mixed $value, string $field): float
    {
        $n = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($n === false || $n < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative number");
        }
        return (float) $n;
    }

    /**
     * Assert the value is a valid email address.
     */
    public static function email(string $value): string
    {
        $clean = filter_var(trim($value), FILTER_VALIDATE_EMAIL);
        if ($clean === false) {
            throw new InvalidArgumentException('Invalid email address');
        }
        return $clean;
    }

    /**
     * Assert password meets minimum security requirements.
     */
    public static function password(string $value): void
    {
        if (strlen($value) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }
    }

    /**
     * Assert the value belongs to an allowed set.
     *
     * @param string[] $allowed
     */
    public static function inSet(mixed $value, array $allowed, string $field): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("{$field} must be one of: " . implode(', ', $allowed));
        }
        return (string) $value;
    }
}