<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Order;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\CouponRedemption;
use Vapehaus\Domain\Model\InventoryAuditLog;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\OrderItem;
use Vapehaus\Domain\Repository\AddressRepositoryInterface;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\CouponRepositoryInterface;
use Vapehaus\Domain\Repository\InventoryAuditRepositoryInterface;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;
use Vapehaus\Infrastructure\Notification\NotificationServiceInterface;

final class PlaceOrderExecution
{
    private OrderRepositoryInterface $orderRepository;
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;
    private UserRepositoryInterface $userRepository;
    private ?AddressRepositoryInterface $addressRepository;
    private ?CouponRepositoryInterface $couponRepository;
    private ?InventoryAuditRepositoryInterface $inventoryAuditRepo;
    private ?NotificationServiceInterface $notificationService;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        UserRepositoryInterface $userRepository,
        ?AddressRepositoryInterface $addressRepository = null,
        ?CouponRepositoryInterface $couponRepository = null,
        ?InventoryAuditRepositoryInterface $inventoryAuditRepo = null,
        ?NotificationServiceInterface $notificationService = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->userRepository = $userRepository;
        $this->addressRepository = $addressRepository;
        $this->couponRepository = $couponRepository;
        $this->inventoryAuditRepo = $inventoryAuditRepo;
        $this->notificationService = $notificationService;
    }

    /**
     * @param array{
     *     shipping_address?: string,
     *     address_id?: string,
     *     payment_method?: string
     * } $data
     * @return array<string, mixed>
     */
    public function execute(string $userId, array $data): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new EntityNotFoundException('User', $userId);
        }

        // Age Verification check before checkout
        $user->assertCanPurchaseVape(18);

        // Resolve shipping address
        $shippingAddress = '';
        if (!empty($data['address_id']) && $this->addressRepository !== null) {
            $addr = $this->addressRepository->findById($data['address_id']);
            if ($addr !== null && $addr->getUserId() === $userId) {
                $shippingAddress = $addr->getFormattedAddress();
            }
        }

        if ($shippingAddress === '' && !empty($data['shipping_address'])) {
            $shippingAddress = trim($data['shipping_address']);
        }

        if ($shippingAddress === '' && $this->addressRepository !== null) {
            $defaultAddr = $this->addressRepository->findDefaultByUserId($userId);
            if ($defaultAddr !== null) {
                $shippingAddress = $defaultAddr->getFormattedAddress();
            }
        }

        if ($shippingAddress === '') {
            throw new ValidationException('A valid shipping address is required.');
        }

        $paymentMethod = trim($data['payment_method'] ?? 'cod');

        $cart = $this->cartRepository->findByUserId($userId);
        if ($cart === null || empty($cart->getItems())) {
            throw new ValidationException('Cannot checkout with an empty cart.');
        }

        $orderId = 'ord_' . bin2hex(random_bytes(8));
        $datePrefix = (new DateTimeImmutable())->format('Ymd');
        $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
        $orderNumber = "VH-{$datePrefix}-{$randomSuffix}";

        $orderItems = [];
        $variantsToUpdate = [];

        // Pre-validate stock for all items
        foreach ($cart->getItems() as $cartItem) {
            $variant = $this->productRepository->findVariantById($cartItem->getVariantId());
            if ($variant === null) {
                throw new EntityNotFoundException('ProductVariant', $cartItem->getVariantId());
            }

            if (!$variant->hasSufficientStock($cartItem->getQuantity())) {
                throw new InsufficientStockException($variant->getSku()->getValue(), $cartItem->getQuantity(), $variant->getStockQuantity());
            }

            $product = $this->productRepository->findById($variant->getProductId());
            $productName = $product !== null ? $product->getName() : 'Vape Device/Juice';

            $orderItemId = 'oit_' . bin2hex(random_bytes(8));
            $orderItem = new OrderItem(
                $orderItemId,
                $orderId,
                $variant->getId(),
                $productName,
                $variant->getSku()->getValue(),
                $variant->getFlavor(),
                $variant->getNicotineStrength()->getValue(),
                $variant->getPrice(),
                $cartItem->getQuantity()
            );

            $orderItems[] = $orderItem;
            $variantsToUpdate[] = [
                'variant' => $variant,
                'quantity' => $cartItem->getQuantity(),
            ];
        }

        // Deduct inventory & record audit log
        foreach ($variantsToUpdate as $item) {
            $variant = $item['variant'];
            $prevStock = $variant->getStockQuantity();
            $variant->decrementStock($item['quantity']);
            $newStock = $variant->getStockQuantity();
            $this->productRepository->saveVariant($variant);

            if ($this->inventoryAuditRepo !== null) {
                $this->inventoryAuditRepo->log(new InventoryAuditLog(
                    'log_' . bin2hex(random_bytes(6)),
                    $variant->getId(),
                    $prevStock,
                    $newStock,
                    -$item['quantity'],
                    "Order #{$orderNumber} checkout",
                    $userId
                ));
            }
        }

        $subtotal = Money::zero();
        foreach ($orderItems as $item) {
            $subtotal = $subtotal->add($item->getTotalPrice());
        }

        // Standard shipping: Free if subtotal >= 1500 PHP, else 50 PHP
        $shippingFee = ($subtotal->toFloat() >= 1500.0) ? Money::zero() : Money::fromFloat(50.0);

        // Check and apply coupon
        $discountAmount = Money::zero();
        $appliedCouponCode = $cart->getAppliedCouponCode();
        if ($appliedCouponCode !== null && $this->couponRepository !== null) {
            $coupon = $this->couponRepository->findByCode($appliedCouponCode);
            if ($coupon !== null && $coupon->isActive() && !$coupon->isExpired()) {
                try {
                    $discountAmount = $coupon->calculateDiscount($subtotal, $shippingFee);
                    $coupon->incrementUsage();
                    $this->couponRepository->save($coupon);

                    $redemptionId = 'red_' . bin2hex(random_bytes(6));
                    $this->couponRepository->saveRedemption(new CouponRedemption(
                        $redemptionId,
                        $coupon->getId(),
                        $userId,
                        $orderId,
                        $discountAmount
                    ));
                } catch (\Throwable) {
                    // Ignore expired or invalid coupon during checkout fallback
                    $appliedCouponCode = null;
                }
            }
        }

        $totalCents = max(0, $subtotal->getAmountInCents() + $shippingFee->getAmountInCents() - $discountAmount->getAmountInCents());
        $total = new Money($totalCents, $subtotal->getCurrency());

        $order = new Order(
            $orderId,
            $orderNumber,
            $userId,
            OrderStatus::pending(),
            $subtotal,
            $shippingFee,
            $total,
            $shippingAddress,
            $paymentMethod,
            'unpaid',
            $discountAmount,
            $appliedCouponCode,
            null,
            null,
            null,
            null,
            null,
            null,
            $orderItems
        );

        $this->orderRepository->save($order);

        // Clear cart
        $cart->clear();
        $this->cartRepository->save($cart);

        // Notify user
        if ($this->notificationService !== null) {
            $this->notificationService->sendOrderConfirmation($order, $user);
        }

        return $order->toArray();
    }
}
