<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Sales = 'sales';
    case Operator = 'operator';
    case Guide = 'guide';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Administrador'),
            self::Sales => __('Vendedor'),
            self::Operator => __('Operador'),
            self::Guide => __('Guía'),
            self::Customer => __('Viajero'),
        };
    }

    public function isGlobal(): bool
    {
        return $this === self::SuperAdmin;
    }
}
