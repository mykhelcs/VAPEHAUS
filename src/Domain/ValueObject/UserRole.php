<?php

declare(strict_types=1);

namespace Vapehaus\Domain\ValueObject;

/**
 * UserRole Value Object - Alias for Role to maintain strict backward compatibility.
 */
final class UserRole
{
    public const CUSTOMER = 'customer';
    public const ADMIN = 'admin';

    private Role $role;

    public function __construct(string $value)
    {
        $this->role = new Role($value);
    }

    public static function customer(): Role
    {
        return Role::customer();
    }

    public static function admin(): Role
    {
        return Role::admin();
    }

    public function getValue(): string
    {
        return $this->role->getValue();
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isCustomer(): bool
    {
        return $this->role->isCustomer();
    }

    public function __toString(): string
    {
        return (string) $this->role;
    }
}
