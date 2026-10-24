<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\UserAddress;
use Vapehaus\Domain\Repository\AddressRepositoryInterface;

final class PdoAddressRepository implements AddressRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(UserAddress $address): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE id = :id');
        $stmt->execute([':id' => $address->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE user_addresses SET
                label = :lbl,
                recipient_name = :name,
                phone = :phone,
                address_line1 = :a1,
                address_line2 = :a2,
                city = :city,
                province = :prov,
                postal_code = :zip,
                is_default = :def
                WHERE id = :id');
            $stmt->execute([
                ':id' => $address->getId(),
                ':lbl' => $address->getLabel(),
                ':name' => $address->getRecipientName(),
                ':phone' => $address->getPhone(),
                ':a1' => $address->getAddressLine1(),
                ':a2' => $address->getAddressLine2(),
                ':city' => $address->getCity(),
                ':prov' => $address->getProvince(),
                ':zip' => $address->getPostalCode(),
                ':def' => $address->isDefault() ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO user_addresses (id, user_id, label, recipient_name, phone, address_line1, address_line2, city, province, postal_code, is_default, created_at)
                VALUES (:id, :uid, :lbl, :name, :phone, :a1, :a2, :city, :prov, :zip, :def, :created)');
            $stmt->execute([
                ':id' => $address->getId(),
                ':uid' => $address->getUserId(),
                ':lbl' => $address->getLabel(),
                ':name' => $address->getRecipientName(),
                ':phone' => $address->getPhone(),
                ':a1' => $address->getAddressLine1(),
                ':a2' => $address->getAddressLine2(),
                ':city' => $address->getCity(),
                ':prov' => $address->getProvince(),
                ':zip' => $address->getPostalCode(),
                ':def' => $address->isDefault() ? 1 : 0,
                ':created' => $address->getCreatedAt(),
            ]);
        }
    }

    public function findById(string $id): ?UserAddress
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_addresses WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToAddress($row) : null;
    }

    public function findByUserId(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_addresses WHERE user_id = :uid ORDER BY is_default DESC, created_at DESC');
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll();
        return array_map(fn ($r) => $this->mapRowToAddress($r), $rows);
    }

    public function findDefaultByUserId(string $userId): ?UserAddress
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_addresses WHERE user_id = :uid AND is_default = 1 LIMIT 1');
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToAddress($row) : null;
    }

    public function clearDefault(string $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_addresses WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToAddress(array $row): UserAddress
    {
        return new UserAddress(
            (string) $row['id'],
            (string) $row['user_id'],
            (string) $row['label'],
            (string) $row['recipient_name'],
            (string) $row['phone'],
            (string) $row['address_line1'],
            $row['address_line2'] !== null ? (string) $row['address_line2'] : null,
            (string) $row['city'],
            (string) $row['province'],
            (string) $row['postal_code'],
            (bool) $row['is_default'],
            (string) $row['created_at']
        );
    }
}
