<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Operator = 'operator';
    case Guide = 'guide';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Operator => 'Operator',
            self::Guide => 'Guide',
            self::Customer => 'Customer',
        };
    }

    public function isGlobal(): bool
    {
        return $this === self::SuperAdmin;
    }
}
