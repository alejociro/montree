<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Builders\TenantBuilder;
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
 * @property Carbon|null $suspended_at
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
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Clave con la que `BelongsToTenant` registra su global scope, y la única
     * que `withoutGlobalScope()` reconoce.
     *
     * WHY: vive acá y no en el trait porque PHP no deja leer una constante de
     * trait por el nombre del trait, y el filtro se quedaría puesto en silencio
     * si alguien pasara la clase.
     */
    public const SCOPE = 'tenant';

    public function newEloquentBuilder($query): TenantBuilder
    {
        return new TenantBuilder($query);
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

    public function platformCharges(): HasMany
    {
        return $this->hasMany(PlatformCharge::class);
    }

    /** Esquema de comisión PROPIO de la agencia, si eligió tener uno. */
    public function commissionSchedule(): HasOne
    {
        return $this->hasOne(CommissionSchedule::class);
    }

    /** Una agencia suspendida o pendiente no recibe visitas: su panel no abre. */
    public function canBeEntered(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
