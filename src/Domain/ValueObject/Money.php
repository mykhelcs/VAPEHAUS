<?php

declare(strict_types=1);

namespace Vapehaus\Domain\ValueObject;

use Vapehaus\Domain\Exception\ValidationException;

final class Money
{
    private int $amountInCents;
    private string $currency;

    public function __construct(int $amountInCents, string $currency = 'PHP')
    {
        if ($amountInCents < 0) {
            throw new ValidationException("Money amount cannot be negative.");
        }
        $this->amountInCents = $amountInCents;
        $this->currency = strtoupper(trim($currency));
    }

    public static function fromFloat(float $amount, string $currency = 'PHP'): self
    {
        $cents = (int) round($amount * 100);
        return new self($cents, $currency);
    }

    public static function zero(string $currency = 'PHP'): self
    {
        return new self(0, $currency);
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function toFloat(): float
    {
        return $this->amountInCents / 100;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new ValidationException("Multiplier cannot be negative.");
        }
        return new self($this->amountInCents * $multiplier, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amountInCents === $other->amountInCents && $this->currency === $other->currency;
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new ValidationException("Currency mismatch: {$this->currency} vs {$other->currency}");
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toFloat(),
            'cents' => $this->amountInCents,
            'currency' => $this->currency,
            'formatted' => sprintf('%s %.2f', $this->currency, $this->toFloat()),
        ];
    }
}
