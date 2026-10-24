<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\InvalidOrderStateException;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;

final class Order
{
    private string $id;
    private string $orderNumber;
    private string $userId;
    private OrderStatus $status;
    private Money $subtotal;
    private Money $discountAmount;
    private ?string $couponCode;
    private Money $shippingFee;
    private Money $total;
    private string $shippingAddress;
    private string $paymentMethod;
    private string $paymentStatus;
    private ?string $trackingNumber;
    private ?string $courierName;
    private ?string $trackingUrl;
    private ?string $shippedAt;
    private ?string $deliveredAt;
    private string $createdAt;
    /** @var array<int, OrderItem> */
    private array $items;

    /**
     * @param array<int, OrderItem> $items
     */
    public function __construct(
        string $id,
        string $orderNumber,
        string $userId,
        OrderStatus $status,
        Money $subtotal,
        Money $shippingFee,
        Money $total,
        string $shippingAddress,
        string $paymentMethod = 'cod',
        string $paymentStatus = 'unpaid',
        ?Money $discountAmount = null,
        ?string $couponCode = null,
        ?string $trackingNumber = null,
        ?string $courierName = null,
        ?string $trackingUrl = null,
        ?string $shippedAt = null,
        ?string $deliveredAt = null,
        ?string $createdAt = null,
        array $items = []
    ) {
        $this->id = $id;
        $this->orderNumber = $orderNumber;
        $this->userId = $userId;
        $this->status = $status;
        $this->subtotal = $subtotal;
        $this->shippingFee = $shippingFee;
        $this->total = $total;
        $this->shippingAddress = trim($shippingAddress);
        $this->paymentMethod = trim($paymentMethod);
        $this->paymentStatus = trim($paymentStatus);
        $this->discountAmount = $discountAmount ?? Money::zero($subtotal->getCurrency());
        $this->couponCode = $couponCode ? strtoupper(trim($couponCode)) : null;
        $this->trackingNumber = $trackingNumber;
        $this->courierName = $courierName;
        $this->trackingUrl = $trackingUrl;
        $this->shippedAt = $shippedAt;
        $this->deliveredAt = $deliveredAt;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->items = $items;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    public function getDiscountAmount(): Money
    {
        return $this->discountAmount;
    }

    public function getCouponCode(): ?string
    {
        return $this->couponCode;
    }

    public function getShippingFee(): Money
    {
        return $this->shippingFee;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getShippingAddress(): string
    {
        return $this->shippingAddress;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getCourierName(): ?string
    {
        return $this->courierName;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function getShippedAt(): ?string
    {
        return $this->shippedAt;
    }

    public function getDeliveredAt(): ?string
    {
        return $this->deliveredAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @return array<int, OrderItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function canCancel(): bool
    {
        return $this->status->canCancel();
    }

    public function transitionTo(string $targetStatus): void
    {
        if (!$this->status->canTransitionTo($targetStatus)) {
            throw new InvalidOrderStateException($this->status->getValue(), $targetStatus);
        }
        $this->status = new OrderStatus($targetStatus);

        if ($targetStatus === OrderStatus::PAID) {
            $this->paymentStatus = 'paid';
        } elseif ($targetStatus === OrderStatus::SHIPPED) {
            $this->shippedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        } elseif ($targetStatus === OrderStatus::DELIVERED) {
            $this->deliveredAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        }
    }

    public function setTracking(string $trackingNumber, string $courierName, ?string $trackingUrl = null): void
    {
        $this->trackingNumber = trim($trackingNumber);
        $this->courierName = trim($courierName);
        $this->trackingUrl = $trackingUrl ? trim($trackingUrl) : null;
        $this->transitionTo(OrderStatus::SHIPPED);
    }

    public function markAsPaid(): void
    {
        $this->transitionTo(OrderStatus::PAID);
    }

    public function markAsRefunded(): void
    {
        $this->paymentStatus = 'refunded';
    }

    public function cancel(): void
    {
        $this->transitionTo(OrderStatus::CANCELLED);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->orderNumber,
            'user_id' => $this->userId,
            'status' => $this->status->getValue(),
            'subtotal' => $this->subtotal->toFloat(),
            'discount_amount' => $this->discountAmount->toFloat(),
            'coupon_code' => $this->couponCode,
            'shipping_fee' => $this->shippingFee->toFloat(),
            'total' => $this->total->toFloat(),
            'total_formatted' => sprintf('%s %.2f', $this->total->getCurrency(), $this->total->toFloat()),
            'shipping_address' => $this->shippingAddress,
            'payment_method' => $this->paymentMethod,
            'payment_status' => $this->paymentStatus,
            'tracking_number' => $this->trackingNumber,
            'courier_name' => $this->courierName,
            'tracking_url' => $this->trackingUrl,
            'shipped_at' => $this->shippedAt,
            'delivered_at' => $this->deliveredAt,
            'created_at' => $this->createdAt,
            'items' => array_map(fn (OrderItem $item) => $item->toArray(), $this->items),
        ];
    }
}
