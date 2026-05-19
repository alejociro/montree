<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property PaymentGateway $gateway
 * @property string|null $gateway_payment_id
 * @property string|null $gateway_charge_id
 * @property string $amount
 * @property string $currency
 * @property PaymentType $type
 * @property PaymentStatus $status
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $gateway_response
 * @property string $refunded_amount
 * @property string|null $refund_reason
 * @property Carbon|null $processed_at
 * @property Carbon|null $refunded_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'gateway',
        'gateway_payment_id',
        'gateway_charge_id',
        'amount',
        'currency',
        'type',
        'status',
        'failure_reason',
        'gateway_response',
        'refunded_amount',
        'refund_reason',
        'processed_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'type' => PaymentType::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'gateway_response' => 'array',
            'processed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
