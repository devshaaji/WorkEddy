<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WorkEddy\Helpers\Validator;

final class ValidatorTest extends TestCase
{
    // ── requireFields ────────────────────────────────────────────────────

    public function testRequireFieldsPassesWithAllPresent(): void
    {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        Validator::requireFields($data, ['name', 'email']);
        $this->assertTrue(true); // no exception = pass
    }

    public function testRequireFieldsThrowsOnMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: email');
        Validator::requireFields(['name' => 'John'], ['name', 'email']);
    }

    public function testRequireFieldsThrowsOnEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::requireFields(['name' => ''], ['name']);
    }

    // ── positiveNumber ───────────────────────────────────────────────────

    public function testPositiveNumberReturnsFloat(): void
    {
        $this->assertSame(5.0, Validator::positiveNumber(5, 'weight'));
        $this->assertSame(0.0, Validator::positiveNumber(0, 'weight'));
        $this->assertSame(3.5, Validator::positiveNumber('3.5', 'weight'));
    }

    public function testPositiveNumberThrowsOnNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::positiveNumber(-1, 'weight');
    }

    public function testPositiveNumberThrowsOnNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::positiveNumber('abc', 'weight');
    }

    // ── email ────────────────────────────────────────────────────────────

    public function testEmailValidatesCorrectly(): void
    {
        $this->assertSame('user@example.com', Validator::email('user@example.com'));
        $this->assertSame('user@example.com', Validator::email('  user@example.com  '));
    }

    public function testEmailThrowsOnInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::email('not-an-email');
    }

    // ── password ─────────────────────────────────────────────────────────

    public function testPasswordAccepts8Chars(): void
    {
        Validator::password('12345678');
        $this->assertTrue(true);
    }

    public function testPasswordRejectsShort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::password('short');
    }

    // ── inSet ────────────────────────────────────────────────────────────

    public function testInSetReturnsValueWhenValid(): void
    {
        $this->assertSame('admin', Validator::inSet('admin', ['admin', 'worker'], 'role'));
    }

    public function testInSetThrowsOnInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::inSet('hacker', ['admin', 'worker'], 'role');
    }
}
