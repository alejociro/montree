<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\BookingStatus;
use App\Enums\PaymentType;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property int $adults_count
 * @property int $minors_count
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
 * @property string $due_amount
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
        'adults_count',
        'minors_count',
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
            'adults_count' => 'integer',
            'minors_count' => 'integer',
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

    /**
     * Saldo de la reserva. El dinero vive en `bookings` y `payments`; esto no es
     * una columna (D5).
     *
     * @return Attribute<string, never>
     */
    protected function dueAmount(): Attribute
    {
        return Attribute::get(fn (): string => $this->money(
            (float) $this->total_amount - (float) $this->paid_amount,
        ));
    }

    /**
     * Parte proporcional de un pasajero sobre el dinero de la reserva (D5): se
     * calcula, no se guarda. El estado se decide por el saldo de **la reserva**,
     * así que dos pasajeros de la misma reserva nunca aparecen uno «Pagado» y
     * otro «Con saldo»: pagó la reserva, no la persona.
     *
     * @return array{share_amount: string, paid_amount: string, due_amount: string, currency: string, status: string}
     */
    public function passengerShare(): array
    {
        $travelers = max(1, (int) $this->travelers_count);
        $share = round(((float) $this->total_amount) / $travelers, 2);
        $paid = round(((float) $this->paid_amount) / $travelers, 2);

        return [
            'share_amount' => $this->money($share),
            'paid_amount' => $this->money($paid),
            'due_amount' => $this->money(max(0, $share - $paid)),
            'currency' => (string) $this->currency,
            'status' => ((float) $this->total_amount) - ((float) $this->paid_amount) > 0 ? 'due' : 'paid',
        ];
    }

    private function money(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
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
