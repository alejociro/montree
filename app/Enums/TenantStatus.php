<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending',
            self::Suspended => 'Suspended',
        };
    }
}
