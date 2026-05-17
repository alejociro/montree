<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string $contact_email
 * @property string|null $contact_phone
 * @property TenantStatus $status
 * @property TenantPlan $plan
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $suspended_at
 * @property array<string, mixed>|null $plan_limits
 */
class Tenant extends BaseTenant
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'contact_email',
        'contact_phone',
        'status',
        'plan',
        'trial_ends_at',
        'suspended_at',
        'plan_limits',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'plan' => TenantPlan::class,
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'plan_limits' => 'array',
        ];
    }

    public function configuration(): HasOne
    {
        return $this->hasOne(TenantConfiguration::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot(['status', 'invited_at', 'joined_at', 'suspended_at'])
            ->withTimestamps();
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
