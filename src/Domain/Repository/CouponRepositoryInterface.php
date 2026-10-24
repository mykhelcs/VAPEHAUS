<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Repository;

use Vapehaus\Domain\Model\Coupon;
use Vapehaus\Domain\Model\CouponRedemption;

interface CouponRepositoryInterface
{
    public function save(Coupon $coupon): void;

    public function findById(string $id): ?Coupon;

    public function findByCode(string $code): ?Coupon;

    /**
     * @return array<int, Coupon>
     */
    public function findAll(): array;

    public function saveRedemption(CouponRedemption $redemption): void;

    public function countUserRedemptions(string $couponId, string $userId): int;
}
