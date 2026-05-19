<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantMembershipStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Invited => 'Invited',
            self::Suspended => 'Suspended',
        };
    }
}
