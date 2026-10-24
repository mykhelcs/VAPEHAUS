<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\UnderageException;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Domain\ValueObject\Role;

final class User
{
    private string $id;
    private Email $email;
    private string $passwordHash;
    private string $firstName;
    private string $lastName;
    private ?string $phone;
    private Role $role;
    private ?string $birthDate; // Y-m-d format
    private bool $isAgeVerified;
    private string $createdAt;

    public function __construct(
        string $id,
        Email $email,
        string $passwordHash,
        string $firstName,
        string $lastName,
        ?string $phone = null,
        ?Role $role = null,
        ?string $birthDate = null,
        bool $isAgeVerified = false,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->phone = $phone ? trim($phone) : null;
        $this->role = $role ?? Role::customer();
        $this->birthDate = $birthDate;
        $this->isAgeVerified = $isAgeVerified;
        $this->createdAt = $createdAt ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getBirthDate(): ?string
    {
        return $this->birthDate;
    }

    public function isAgeVerified(): bool
    {
        return $this->isAgeVerified;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function calculateAge(): ?int
    {
        if ($this->birthDate === null) {
            return null;
        }

        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $this->birthDate);
        if (!$dob) {
            return null;
        }

        $now = new DateTimeImmutable('today');
        return $now->diff($dob)->y;
    }

    public function assertCanPurchaseVape(int $minAge = 18): void
    {
        $age = $this->calculateAge();
        if ($age === null || $age < $minAge) {
            throw new UnderageException($minAge, $age ?? 0);
        }
    }

    public function markAgeVerified(int $minAge = 18): void
    {
        $this->assertCanPurchaseVape($minAge);
        $this->isAgeVerified = true;
    }

    public function updateProfile(string $firstName, string $lastName, ?string $phone): void
    {
        $this->firstName = trim($firstName);
        $this->lastName = trim($lastName);
        $this->phone = $phone ? trim($phone) : null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email->getValue(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'phone' => $this->phone,
            'role' => $this->role->getValue(),
            'birth_date' => $this->birthDate,
            'is_age_verified' => $this->isAgeVerified,
            'created_at' => $this->createdAt,
        ];
    }
}
