<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Repository;

use PDO;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Domain\ValueObject\Role;

final class PdoUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
        $stmt->execute([':id' => $user->getId()]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE users SET
                email = :email,
                password_hash = :hash,
                first_name = :first,
                last_name = :last,
                phone = :phone,
                role = :role,
                birth_date = :dob,
                is_age_verified = :verified
                WHERE id = :id');
            $stmt->execute([
                ':id' => $user->getId(),
                ':email' => $user->getEmail()->getValue(),
                ':hash' => $user->getPasswordHash(),
                ':first' => $user->getFirstName(),
                ':last' => $user->getLastName(),
                ':phone' => $user->getPhone(),
                ':role' => $user->getRole()->getValue(),
                ':dob' => $user->getBirthDate(),
                ':verified' => $user->isAgeVerified() ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO users (id, email, password_hash, first_name, last_name, phone, role, birth_date, is_age_verified, created_at)
                VALUES (:id, :email, :hash, :first, :last, :phone, :role, :dob, :verified, :created)');
            $stmt->execute([
                ':id' => $user->getId(),
                ':email' => $user->getEmail()->getValue(),
                ':hash' => $user->getPasswordHash(),
                ':first' => $user->getFirstName(),
                ':last' => $user->getLastName(),
                ':phone' => $user->getPhone(),
                ':role' => $user->getRole()->getValue(),
                ':dob' => $user->getBirthDate(),
                ':verified' => $user->isAgeVerified() ? 1 : 0,
                ':created' => $user->getCreatedAt(),
            ]);
        }
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToUser($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ? $this->mapRowToUser($row) : null;
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users ORDER BY created_at DESC LIMIT :lim OFFSET :off');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(fn ($r) => $this->mapRowToUser($r), $rows);
    }

    public function count(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users');
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToUser(array $row): User
    {
        return new User(
            (string) $row['id'],
            new Email((string) $row['email']),
            (string) $row['password_hash'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            $row['phone'] !== null ? (string) $row['phone'] : null,
            new Role((string) $row['role']),
            $row['birth_date'] !== null ? (string) $row['birth_date'] : null,
            (bool) $row['is_age_verified'],
            (string) $row['created_at']
        );
    }
}
