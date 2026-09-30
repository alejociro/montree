<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Builders\PlatformChargeBuilder;
use Database\Factories\PlatformChargeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cargo que la plataforma le hace a una agencia por una reserva confirmada.
 *
 * WHY: NO usa `BelongsToTenant`. Lo consulta el super admin desde el host de
 * plataforma, donde no hay tenant actual; con el global scope el ledger saldría
 * vacío y las gráficas también.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property int|null $payment_id
 * @property string $base_amount
 * @property string $applied_rate
 * @property string $amount
 * @property string $currency
 * @property string|null $tier_from
 * @property string|null $tier_to
 * @property string|null $max_charge
 * @property bool $was_capped
 * @property string|null $schedule_scope
 * @property Carbon $charged_at
 */
class PlatformCharge extends Model
{
    /** @use HasFactory<PlatformChargeFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'payment_id',
        'base_amount',
        'applied_rate',
        'amount',
        'currency',
        'tier_from',
        'tier_to',
        'max_charge',
        'was_capped',
        'schedule_scope',
        'charged_at',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'applied_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'tier_from' => 'decimal:2',
            'tier_to' => 'decimal:2',
            'max_charge' => 'decimal:2',
            'was_capped' => 'boolean',
            'charged_at' => 'datetime',
        ];
    }

    public function newEloquentBuilder($query): PlatformChargeBuilder
    {
        return new PlatformChargeBuilder($query);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
