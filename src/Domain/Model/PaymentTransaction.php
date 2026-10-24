<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\ValueObject\Money;

final class PaymentTransaction
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    private string $id;
    private string $orderId;
    private string $gateway;
    private string $transactionReference;
    private Money $amount;
    private string $status;
    /** @var array<string, mixed> */
    private array $rawPayload;
    private string $createdAt;

    /**
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        string $id,
        string $orderId,
        string $gateway,
        string $transactionReference,
        Money $amount,
        string $status = self::STATUS_PENDING,
        array $rawPayload = [],
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->orderId = $orderId;
        $this->gateway = strtolower(trim($gateway));
        $this->transactionReference = trim($transactionReference);
        $this->amount = $amount;
        $this->status = $status;
        $this->rawPayload = $rawPayload;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }

    public function getTransactionReference(): string
    {
        return $this->transactionReference;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function markSucceeded(): void
    {
        $this->status = self::STATUS_SUCCEEDED;
    }

    public function markFailed(): void
    {
        $this->status = self::STATUS_FAILED;
    }

    public function markRefunded(): void
    {
        $this->status = self::STATUS_REFUNDED;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'gateway' => $this->gateway,
            'transaction_reference' => $this->transactionReference,
            'amount' => $this->amount->toFloat(),
            'amount_formatted' => sprintf('%s %.2f', $this->amount->getCurrency(), $this->amount->toFloat()),
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
