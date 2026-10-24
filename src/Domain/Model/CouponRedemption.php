<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\ValueObject\Money;

final class CouponRedemption
{
    private string $id;
    private string $couponId;
    private string $userId;
    private string $orderId;
    private Money $discountAmount;
    private string $redeemedAt;

    public function __construct(
        string $id,
        string $couponId,
        string $userId,
        string $orderId,
        Money $discountAmount,
        ?string $redeemedAt = null
    ) {
        $this->id = $id;
        $this->couponId = $couponId;
        $this->userId = $userId;
        $this->orderId = $orderId;
        $this->discountAmount = $discountAmount;
        $this->redeemedAt = $redeemedAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCouponId(): string
    {
        return $this->couponId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getDiscountAmount(): Money
    {
        return $this->discountAmount;
    }

    public function getRedeemedAt(): string
    {
        return $this->redeemedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'coupon_id' => $this->couponId,
            'user_id' => $this->userId,
            'order_id' => $this->orderId,
            'discount_amount' => $this->discountAmount->toFloat(),
            'redeemed_at' => $this->redeemedAt,
        ];
    }
}
