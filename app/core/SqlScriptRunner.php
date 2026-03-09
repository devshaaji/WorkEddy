<?php

declare(strict_types=1);

namespace WorkEddy\Core;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class SqlScriptRunner
{
    public static function executeFile(Connection $db, string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('SQL file not found: ' . $filePath);
        }

        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new RuntimeException('Could not read SQL file: ' . $filePath);
        }

        return self::executeSql($db, $sql);
    }

    public static function executeSql(Connection $db, string $sql): int
    {
        $executed = 0;
        foreach (self::splitStatements($sql) as $statement) {
            $db->executeStatement($statement);
            $executed++;
        }

        return $executed;
    }

    /**
     * Split SQL scripts into executable statements while ignoring comments and
     * semicolons inside quoted strings.
     *
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);

        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                if (
                    $char === '-'
                    && $next === '-'
                    && ($i + 2 >= $length || ctype_space($sql[$i + 2]))
                ) {
                    $inLineComment = true;
                    $i++;
                    continue;
                }

                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && !$inDoubleQuote && !$inBacktick && !self::isEscaped($sql, $i)) {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick && !self::isEscaped($sql, $i)) {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    private static function isEscaped(string $sql, int $index): bool
    {
        $backslashCount = 0;
        for ($i = $index - 1; $i >= 0 && $sql[$i] === '\\'; $i--) {
            $backslashCount++;
        }

        return $backslashCount % 2 === 1;
    }
}

