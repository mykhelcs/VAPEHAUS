<?php

declare(strict_types=1);

namespace Vapehaus\Tests;

use Exception;
use Throwable;

abstract class TestCase
{
    private int $assertionCount = 0;
    private ?string $expectedException = null;

    public function getAssertionCount(): int
    {
        return $this->assertionCount;
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function runTest(string $method): void
    {
        $this->setUp();
        $this->expectedException = null;
        $thrown = null;

        try {
            $this->$method();
        } catch (Throwable $e) {
            $thrown = $e;
        } finally {
            $this->tearDown();
        }

        if ($this->expectedException !== null) {
            if ($thrown === null) {
                throw new Exception("Failed asserting that exception {$this->expectedException} was thrown.");
            }
            if (!($thrown instanceof $this->expectedException)) {
                throw new Exception("Failed asserting that thrown " . get_class($thrown) . " matches {$this->expectedException}. Message: " . $thrown->getMessage());
            }
        } elseif ($thrown !== null) {
            throw $thrown;
        }
    }

    protected function expectException(string $exceptionClass): void
    {
        $this->expectedException = $exceptionClass;
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertionCount++;
        if (!$condition) {
            throw new Exception($message !== '' ? $message : 'Failed asserting that condition is true.');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertionCount++;
        if ($condition) {
            throw new Exception($message !== '' ? $message : 'Failed asserting that condition is false.');
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected !== $actual) {
            $expectedStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
            $actualStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
            throw new Exception($message !== '' ? $message : "Failed asserting that {$actualStr} matches expected {$expectedStr}.");
        }
    }

    protected function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($expected === $actual) {
            throw new Exception($message !== '' ? $message : 'Failed asserting that actual does not equal expected.');
        }
    }

    protected function assertCount(int $expected, array $actual, string $message = ''): void
    {
        $this->assertionCount++;
        $actualCount = count($actual);
        if ($actualCount !== $expected) {
            throw new Exception($message !== '' ? $message : "Failed asserting count. Expected: {$expected}, Got: {$actualCount}.");
        }
    }

    protected function assertNull(mixed $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($actual !== null) {
            throw new Exception($message !== '' ? $message : 'Failed asserting that value is null.');
        }
    }

    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        $this->assertionCount++;
        if ($actual === null) {
            throw new Exception($message !== '' ? $message : 'Failed asserting that value is not null.');
        }
    }
}
