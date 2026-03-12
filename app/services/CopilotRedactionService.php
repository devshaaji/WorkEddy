<?php

declare(strict_types=1);

namespace WorkEddy\Services;

final class CopilotRedactionService
{
    /** @var list<string> */
    private const KEYWORDS_REDACT = [
        'email',
        'phone',
        'notes',
        'note',
        'message',
        'body',
        'description',
        'comment',
        'password',
        'secret',
        'token',
        'otp',
    ];

    /** @var list<string> */
    private const EXACT_KEYS_REDACT = [
        'id',
        'user_id',
        'assigned_to_user_id',
        'assigned_to_name',
        'created_by_user_id',
        'created_by_name',
    ];

    /**
     * @param mixed $value
     * @return mixed
     */
    public function redact(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $k => $v) {
                $childKey = is_string($k) ? $k : null;
                $redacted[$k] = $this->redact($v, $childKey);
            }
            return $redacted;
        }

        if ($key !== null && $this->shouldRedactKey($key)) {
            return $this->maskedValueFor($value);
        }

        return $value;
    }

    private function shouldRedactKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::EXACT_KEYS_REDACT, true)) {
            return true;
        }

        if (str_ends_with($normalized, '_id') || str_ends_with($normalized, '_name')) {
            return true;
        }

        if ($normalized === 'name' || str_contains($normalized, 'email')) {
            return true;
        }

        foreach (self::KEYWORDS_REDACT as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function maskedValueFor(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return '[REDACTED]';
        }

        return '[REDACTED_COMPLEX]';
    }
}
