<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantMembershipStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $tenant_id
 * @property int $user_id
 * @property TenantMembershipStatus $status
 * @property Carbon|null $invited_at
 * @property Carbon|null $joined_at
 * @property Carbon|null $suspended_at
 */
class TenantUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'tenant_user';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'invited_at',
        'joined_at',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantMembershipStatus::class,
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }
}
