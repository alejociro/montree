<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionType;
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
 * @property CommissionType $commission_type
 * @property string $applied_value
 * @property string $amount
 * @property string $currency
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
        'commission_type',
        'applied_value',
        'amount',
        'currency',
        'charged_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_type' => CommissionType::class,
            'base_amount' => 'decimal:2',
            'applied_value' => 'decimal:2',
            'amount' => 'decimal:2',
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
