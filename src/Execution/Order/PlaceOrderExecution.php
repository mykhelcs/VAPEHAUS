<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Order;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\InsufficientStockException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Cart;
use Vapehaus\Domain\Model\CartItem;
use Vapehaus\Domain\Model\CouponRedemption;
use Vapehaus\Domain\Model\InventoryAuditLog;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\OrderItem;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\Repository\AddressRepositoryInterface;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\CouponRepositoryInterface;
use Vapehaus\Domain\Repository\InventoryAuditRepositoryInterface;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;
use Vapehaus\Domain\ValueObject\UserRole;
use Vapehaus\Infrastructure\Environment\Env;
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
     *     shippingAddress?: string,
     *     address_id?: string,
     *     payment_method?: string,
     *     paymentMethod?: string,
     *     session_token?: string,
     *     sessionToken?: string,
     *     is_age_attested?: bool,
     *     isAgeAttested?: bool,
     *     items?: array<int, array{variant_id?: string, variantId?: string, quantity?: int, qty?: int}>,
     *     delivery_notes?: string,
     *     deliveryNotes?: string,
     *     shipping_method?: string,
     *     shippingMethod?: string,
     *     coupon_code?: string,
     *     couponCode?: string,
     *     customer_name?: string,
     *     customerName?: string,
     *     customer_email?: string,
     *     customerEmail?: string,
     *     customer_phone?: string,
     *     customerPhone?: string
     * } $data
     * @return array<string, mixed>
     */
    public function execute(?string $userId, array $data): array
    {
        // 1. Mandatory 18+ Age Attestation (RA 11900)
        $isAgeAttested = (bool) ($data['is_age_attested'] ?? $data['isAgeAttested'] ?? false);
        if (!$isAgeAttested) {
            throw new ValidationException('You must certify that you are at least 18 years of age.');
        }

        // 2. Resolve Authenticated User (Enforce pure registered accounts, no guest checkout)
        $user = null;
        if (!empty($userId) && !str_starts_with($userId, 'usr_guest_')) {
            $user = $this->userRepository->findById($userId);
        }
        if ($user === null) {
            $email = $data['customer_email'] ?? $data['customerEmail'] ?? null;
            if ($email) {
                $user = $this->userRepository->findByEmail(strtolower(trim((string) $email)));
            }
        }

        if ($user === null) {
            throw new ValidationException('You must be signed in with an active account to checkout. Guest checkout is disabled.');
        }

        // 3. Enforce Statutory Age Invariant & Mandatory 18+ KYC ID Verification
        $user->assertCanPurchaseVape(18);

        if (!$user->isAgeVerified()) {
            throw new ValidationException('Age verification with a valid government ID is required before placing an order (Republic Act No. 11900).');
        }

        $userId = $user->getId();

        // 3. Resolve shipping address
        $shippingAddress = '';
        if (!empty($data['address_id']) && $this->addressRepository !== null) {
            $addr = $this->addressRepository->findById($data['address_id']);
            if ($addr !== null) {
                if ($addr->getUserId() === $userId || str_starts_with($userId, 'usr_guest_') || $userId === 'usr_customer_default') {
                    $shippingAddress = $addr->getFormattedAddress();
                }
            }
        }

        if ($shippingAddress === '') {
            $shippingAddress = trim((string) ($data['shipping_address'] ?? $data['shippingAddress'] ?? ''));
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

        // Append landmark & delivery notes if provided
        $deliveryNotes = trim((string) ($data['delivery_notes'] ?? $data['deliveryNotes'] ?? ''));
        if ($deliveryNotes !== '' && !str_contains($shippingAddress, $deliveryNotes)) {
            $shippingAddress .= " [Notes: {$deliveryNotes}]";
        }

        $paymentMethod = trim((string) ($data['payment_method'] ?? $data['paymentMethod'] ?? 'cod'));

        // 4. Resolve Cart
        // Support user cart, session cart, and payload-provided cart items
        $sessionToken = trim((string) ($data['session_token'] ?? $data['sessionToken'] ?? ''));

        $cartByUser = ($userId !== '' && !str_starts_with($userId, 'usr_guest_'))
            ? $this->cartRepository->findByUserId($userId)
            : null;

        $cartBySession = null;
        if ($sessionToken !== '') {
            $cartBySession = $this->cartRepository->findBySessionToken($sessionToken);
        }
        if ($cartBySession === null && ($cartByUser === null || empty($cartByUser->getItems()))) {
            $cartBySession = $this->cartRepository->findBySessionToken('guest_session');
        }

        // Prefer cart with items
        $cart = null;
        if ($cartByUser !== null && !empty($cartByUser->getItems())) {
            $cart = $cartByUser;
        } elseif ($cartBySession !== null && !empty($cartBySession->getItems())) {
            $cart = $cartBySession;
        } else {
            $cart = $cartByUser ?? $cartBySession;
        }

        // If database cart is empty or missing, reconstitute cart from $data['items']
        if (($cart === null || empty($cart->getItems())) && !empty($data['items']) && is_array($data['items'])) {
            $syntheticCartId = 'crt_' . bin2hex(random_bytes(8));
            $syntheticItems = [];
            foreach ($data['items'] as $rawItem) {
                if (!is_array($rawItem)) {
                    continue;
                }
                $vId = trim((string) ($rawItem['variant_id'] ?? $rawItem['variantId'] ?? ''));
                $qty = max(1, (int) ($rawItem['quantity'] ?? $rawItem['qty'] ?? 1));
                if ($vId !== '') {
                    $syntheticItems[] = new CartItem(
                        'cit_' . bin2hex(random_bytes(6)),
                        $syntheticCartId,
                        $vId,
                        $qty
                    );
                }
            }

            if (!empty($syntheticItems)) {
                $couponCode = trim((string) ($data['coupon_code'] ?? $data['couponCode'] ?? $data['applied_coupon'] ?? ''));
                $cart = new Cart(
                    $syntheticCartId,
                    $userId !== '' ? $userId : null,
                    $sessionToken !== '' ? $sessionToken : null,
                    $syntheticItems,
                    $couponCode !== '' ? $couponCode : null
                );
            }
        }

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
            $variantId = $cartItem->getVariantId();
            $variant = $this->productRepository->findVariantById($variantId);
            if ($variant === null) {
                // Fallback: Check if variantId is actually a SKU
                $variant = $this->productRepository->findVariantBySku($variantId);
            }

            if ($variant === null) {
                throw new ValidationException(
                    "Item '{$variantId}' is no longer available in the active catalog. Please update your cart."
                );
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

        // 5. Dynamic Shipping Calculation (Philippines E-Commerce Standard)
        $shippingMethod = strtolower(trim((string) ($data['shipping_method'] ?? $data['shippingMethod'] ?? 'standard')));
        $standardFee = (float) (Env::get('SHIPPING_STANDARD_FEE') ?? 150.0);
        $expressFee = (float) (Env::get('SHIPPING_EXPRESS_FEE') ?? 250.0);
        $freeThreshold = (float) (Env::get('SHIPPING_FREE_THRESHOLD') ?? 1500.0);

        if ($shippingMethod === 'express') {
            $shippingFee = Money::fromFloat($expressFee);
            $courierName = 'Express Same-Day Dispatch';
        } else {
            $shippingFee = ($subtotal->toFloat() >= $freeThreshold) ? Money::zero() : Money::fromFloat($standardFee);
            $courierName = 'Standard Courier';
        }

        // 6. Check and apply coupon
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
            $courierName,
            null,
            null,
            null,
            null,
            $orderItems
        );

        $this->orderRepository->save($order);

        // 7. Clear cart
        $cart->clear();
        try {
            $this->cartRepository->save($cart);
        } catch (\Throwable) {
            // Synthetic cart or detached guest cart may not be in DB; ignore failure
        }

        // 8. Notify user
        if ($this->notificationService !== null) {
            $this->notificationService->sendOrderConfirmation($order, $user);
        }

        return $order->toArray();
    }
}
