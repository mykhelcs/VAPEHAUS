<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\Coupon;
use Vapehaus\Domain\Model\CouponRedemption;
use Vapehaus\Domain\Repository\CouponRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;

final class PdoCouponRepository implements CouponRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Coupon $coupon): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM coupons WHERE id = :id');
        $stmt->execute([':id' => $coupon->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE coupons SET
                code = :code,
                type = :type,
                value = :val,
                min_spend = :min,
                max_discount = :max,
                usage_limit = :limit,
                times_used = :used,
                expires_at = :exp,
                is_active = :act
                WHERE id = :id');
            $stmt->execute([
                ':id' => $coupon->getId(),
                ':code' => $coupon->getCode(),
                ':type' => $coupon->getType(),
                ':val' => $coupon->getValue(),
                ':min' => $coupon->getMinSpend()->toFloat(),
                ':max' => $coupon->getMaxDiscount()?->toFloat(),
                ':limit' => $coupon->getUsageLimit(),
                ':used' => $coupon->getTimesUsed(),
                ':exp' => $coupon->getExpiresAt(),
                ':act' => $coupon->isActive() ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO coupons (id, code, type, value, min_spend, max_discount, usage_limit, times_used, expires_at, is_active, created_at)
                VALUES (:id, :code, :type, :val, :min, :max, :limit, :used, :exp, :act, :created)');
            $stmt->execute([
                ':id' => $coupon->getId(),
                ':code' => $coupon->getCode(),
                ':type' => $coupon->getType(),
                ':val' => $coupon->getValue(),
                ':min' => $coupon->getMinSpend()->toFloat(),
                ':max' => $coupon->getMaxDiscount()?->toFloat(),
                ':limit' => $coupon->getUsageLimit(),
                ':used' => $coupon->getTimesUsed(),
                ':exp' => $coupon->getExpiresAt(),
                ':act' => $coupon->isActive() ? 1 : 0,
                ':created' => $coupon->toArray()['created_at'],
            ]);
        }
    }

    public function findById(string $id): ?Coupon
    {
        $stmt = $this->pdo->prepare('SELECT * FROM coupons WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToCoupon($row) : null;
    }

    public function findByCode(string $code): ?Coupon
    {
        $stmt = $this->pdo->prepare('SELECT * FROM coupons WHERE code = :code');
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToCoupon($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM coupons ORDER BY created_at DESC');
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToCoupon($r), $rows);
    }

    public function saveRedemption(CouponRedemption $redemption): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO coupon_redemptions (id, coupon_id, user_id, order_id, discount_amount, redeemed_at)
            VALUES (:id, :cid, :uid, :oid, :disc, :date)');
        $stmt->execute([
            ':id' => $redemption->getId(),
            ':cid' => $redemption->getCouponId(),
            ':uid' => $redemption->getUserId(),
            ':oid' => $redemption->getOrderId(),
            ':disc' => $redemption->getDiscountAmount()->toFloat(),
            ':date' => $redemption->getRedeemedAt(),
        ]);
    }

    public function countUserRedemptions(string $couponId, string $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = :cid AND user_id = :uid');
        $stmt->execute([':cid' => $couponId, ':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToCoupon(array $row): Coupon
    {
        return new Coupon(
            (string) $row['id'],
            (string) $row['code'],
            (string) $row['type'],
            (float) $row['value'],
            Money::fromFloat((float) $row['min_spend']),
            $row['max_discount'] !== null ? Money::fromFloat((float) $row['max_discount']) : null,
            (int) $row['usage_limit'],
            (int) $row['times_used'],
            $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            (bool) $row['is_active'],
            (string) $row['created_at']
        );
    }
}
