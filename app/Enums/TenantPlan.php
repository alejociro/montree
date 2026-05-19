<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantPlan: string
{
    case Basic = 'basic';
    case Professional = 'professional';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic',
            self::Professional => 'Professional',
            self::Enterprise => 'Enterprise',
        };
    }

    /**
     * @return array<string, int|bool>
     */
    public function limits(): array
    {
        return match ($this) {
            self::Basic => [
                'max_tours' => 10,
                'max_staff' => 3,
                'allows_custom_css' => false,
            ],
            self::Professional => [
                'max_tours' => 50,
                'max_staff' => 10,
                'allows_custom_css' => false,
            ],
            self::Enterprise => [
                'max_tours' => 500,
                'max_staff' => 100,
                'allows_custom_css' => true,
            ],
        };
    }
}
