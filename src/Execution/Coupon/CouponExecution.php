<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Coupon;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Coupon;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\CouponRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;

final class CouponExecution
{
    private CouponRepositoryInterface $couponRepo;
    private CartRepositoryInterface $cartRepo;
    private ProductRepositoryInterface $productRepo;

    public function __construct(
        CouponRepositoryInterface $couponRepo,
        CartRepositoryInterface $cartRepo,
        ProductRepositoryInterface $productRepo
    ) {
        $this->couponRepo = $couponRepo;
        $this->cartRepo = $cartRepo;
        $this->productRepo = $productRepo;
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(?string $userId, ?string $sessionToken, string $code): array
    {
        $code = strtoupper(trim($code));
        $coupon = $this->couponRepo->findByCode($code);
        if ($coupon === null) {
            throw new ValidationException("Coupon code '{$code}' not found.");
        }

        $cart = null;
        if ($userId !== null) {
            $cart = $this->cartRepo->findByUserId($userId);
        } elseif ($sessionToken !== null) {
            $cart = $this->cartRepo->findBySessionToken($sessionToken);
        }

        if ($cart === null || empty($cart->getItems())) {
            throw new ValidationException('Cannot apply coupon to an empty cart.');
        }

        // Attach variants to compute subtotal
        foreach ($cart->getItems() as $item) {
            $v = $this->productRepo->findVariantById($item->getVariantId());
            if ($v !== null) {
                $item->attachVariant($v);
            }
        }

        $subtotal = $cart->calculateSubtotal();
        $coupon->assertCanBeApplied($subtotal);

        $cart->applyCoupon($coupon->getCode());
        $this->cartRepo->save($cart);

        $shippingFee = ($subtotal->toFloat() >= 1500.0) ? Money::zero() : Money::fromFloat(50.0);
        $discount = $coupon->calculateDiscount($subtotal, $shippingFee);
        $totalCents = max(0, $subtotal->getAmountInCents() + $shippingFee->getAmountInCents() - $discount->getAmountInCents());
        $total = new Money($totalCents, $subtotal->getCurrency());

        $cartData = $cart->toArray();
        $cartData['applied_coupon'] = $coupon->toArray();
        $cartData['discount_amount'] = $discount->toFloat();
        $cartData['shipping_fee'] = $shippingFee->toFloat();
        $cartData['estimated_total'] = $total->toFloat();

        return $cartData;
    }

    /**
     * @return array<string, mixed>
     */
    public function remove(?string $userId, ?string $sessionToken): array
    {
        $cart = null;
        if ($userId !== null) {
            $cart = $this->cartRepo->findByUserId($userId);
        } elseif ($sessionToken !== null) {
            $cart = $this->cartRepo->findBySessionToken($sessionToken);
        }

        if ($cart !== null) {
            $cart->removeCoupon();
            $this->cartRepo->save($cart);
            return $cart->toArray();
        }

        return ['item_count' => 0, 'subtotal' => 0.0];
    }

    /**
     * @param array{
     *     code: string,
     *     type: string,
     *     value: float,
     *     min_spend?: float,
     *     max_discount?: ?float,
     *     usage_limit?: int,
     *     expires_at?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function adminCreate(array $data): array
    {
        $code = strtoupper(trim($data['code'] ?? ''));
        $existing = $this->couponRepo->findByCode($code);
        if ($existing !== null) {
            throw new ValidationException("Coupon code '{$code}' already exists.");
        }

        $id = 'cpn_' . bin2hex(random_bytes(6));
        $coupon = new Coupon(
            $id,
            $code,
            $data['type'] ?? 'percentage',
            (float) ($data['value'] ?? 0),
            Money::fromFloat((float) ($data['min_spend'] ?? 0)),
            isset($data['max_discount']) && $data['max_discount'] !== null ? Money::fromFloat((float) $data['max_discount']) : null,
            (int) ($data['usage_limit'] ?? 0),
            0,
            $data['expires_at'] ?? null,
            true
        );

        $this->couponRepo->save($coupon);
        return $coupon->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adminList(): array
    {
        $coupons = $this->couponRepo->findAll();
        return array_map(fn (Coupon $c) => $c->toArray(), $coupons);
    }

    /**
     * @return array<string, mixed>
     */
    public function adminToggle(string $id): array
    {
        $coupon = $this->couponRepo->findById($id);
        if ($coupon === null) {
            throw new EntityNotFoundException('Coupon', $id);
        }

        $coupon->toggleActive();
        $this->couponRepo->save($coupon);
        return $coupon->toArray();
    }
}
