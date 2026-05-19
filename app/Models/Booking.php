<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\BookingStatus;
use App\Enums\PaymentType;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $booking_number
 * @property int $tenant_id
 * @property int $user_id
 * @property int $tour_id
 * @property int $tour_date_id
 * @property int|null $promotion_id
 * @property int $travelers_count
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $total_amount
 * @property string $paid_amount
 * @property string $currency
 * @property BookingStatus $status
 * @property PaymentType $payment_type
 * @property string|null $special_requests
 * @property array<string, mixed>|null $contact_snapshot
 * @property Carbon|null $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $completed_at
 * @property string|null $cancellation_reason
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_number',
        'tenant_id',
        'user_id',
        'tour_id',
        'tour_date_id',
        'promotion_id',
        'travelers_count',
        'subtotal',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'currency',
        'status',
        'payment_type',
        'special_requests',
        'contact_snapshot',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
        'cancellation_reason',
    ];

    public function getRouteKeyName(): string
    {
        return 'booking_number';
    }

    protected function casts(): array
    {
        return [
            'travelers_count' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => BookingStatus::class,
            'payment_type' => PaymentType::class,
            'contact_snapshot' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (Booking $booking): void {
            if (empty($booking->booking_number)) {
                $booking->booking_number = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function tourDate(): BelongsTo
    {
        return $this->belongsTo(TourDate::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(BookingTraveler::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
