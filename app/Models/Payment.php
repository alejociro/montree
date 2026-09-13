<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TransactionSearchField;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $booking_id
 * @property PaymentGateway $gateway
 * @property string|null $request_id
 * @property string|null $internal_reference
 * @property string|null $reference
 * @property string|null $gateway_status
 * @property string|null $process_url
 * @property Carbon|null $session_expires_at
 * @property string|null $authorization
 * @property string|null $receipt
 * @property string|null $franchise
 * @property string|null $payment_method
 * @property string|null $payment_method_name
 * @property string|null $issuer_name
 * @property array<string, mixed>|null $processor_fields
 * @property string $amount
 * @property string $currency
 * @property PaymentType $type
 * @property PaymentStatus $status
 * @property string|null $status_message
 * @property array<string, mixed>|null $gateway_response
 * @property Carbon|null $processed_at
 * @property-read string|null $last_digits
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'gateway',
        'request_id',
        'internal_reference',
        'reference',
        'gateway_status',
        'process_url',
        'session_expires_at',
        'authorization',
        'receipt',
        'franchise',
        'payment_method',
        'payment_method_name',
        'issuer_name',
        'processor_fields',
        'amount',
        'currency',
        'type',
        'status',
        'status_message',
        'gateway_response',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'type' => PaymentType::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'processor_fields' => 'array',
            'processed_at' => 'datetime',
            'session_expires_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Estados que ya no cambian solos: no vale la pena volver a consultarlos
     * en la pasarela. Es la lista que usan el retorno al comercio y
     * `payment:check` para decidir si hay algo que resolver.
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [
            PaymentStatus::Completed,
            PaymentStatus::Failed,
            PaymentStatus::Refunded,
        ], true);
    }

    /**
     * Se puede volver a preguntarle a la pasarela: es una sesión de PlacetoPay
     * con `requestId`. Un pago manual no tiene a quién consultarle.
     */
    public function isQueryable(): bool
    {
        return $this->gateway === PaymentGateway::PlaceToPay && $this->request_id !== null;
    }

    /**
     * Los cuatro últimos dígitos que reportó el autorizador. `processorFields`
     * llega como lista de pares `keyword/value`, pero también se ha visto como
     * mapa, así que se aceptan las dos formas.
     */
    protected function lastDigits(): Attribute
    {
        return Attribute::get(function (): ?string {
            $fields = $this->processor_fields ?? [];

            if (isset($fields['lastDigits'])) {
                return (string) $fields['lastDigits'];
            }

            foreach ($fields as $field) {
                if (is_array($field) && ($field['keyword'] ?? null) === 'lastDigits') {
                    return (string) $field['value'];
                }
            }

            return null;
        });
    }

    /**
     * Búsqueda del panel contra UN campo elegido, no contra todas las columnas.
     *
     * Los identificadores se comparan por igualdad para que la consulta use los
     * índices; el nombre del pagador es el único `LIKE`, y va sobre la reserva.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeSearchBy(Builder $query, ?TransactionSearchField $field, ?string $value): Builder
    {
        $term = trim((string) $value);

        if ($field === null || $term === '') {
            return $query;
        }

        return match ($field) {
            TransactionSearchField::BookingNumber => $query->whereHas(
                'booking',
                fn (Builder $booking) => $booking->where('booking_number', $term),
            ),
            TransactionSearchField::Payer => $query->whereHas(
                'booking',
                fn (Builder $booking) => $booking->where(
                    fn (Builder $who) => $who
                        ->where('contact_snapshot->name', 'like', self::contains($term))
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', self::contains($term))),
                ),
            ),
            default => $query->where($field->value, $term),
        };
    }

    private static function contains(string $term): string
    {
        return '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
    }

    /**
     * Rango por fecha del autorizador, cayendo a la de creación cuando el pago
     * todavía no se resolvió: si no, un pago colgado desaparece del filtro.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeSettledBetween(Builder $query, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        $settled = 'COALESCE(processed_at, created_at)';

        return $query
            ->when($from !== null, fn (Builder $scoped) => $scoped->whereRaw($settled.' >= ?', [$from->toDateTimeString()]))
            ->when($to !== null, fn (Builder $scoped) => $scoped->whereRaw($settled.' <= ?', [$to->toDateTimeString()]));
    }

    /**
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeForTourDate(Builder $query, ?int $tourDateId): Builder
    {
        if ($tourDateId === null) {
            return $query;
        }

        return $query->whereHas('booking', fn (Builder $booking) => $booking->where('tour_date_id', $tourDateId));
    }

    /**
     * Sesiones de PlacetoPay que todavía pueden cambiar de estado.
     *
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('gateway', PaymentGateway::PlaceToPay)
            ->whereNotNull('request_id')
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Processing]);
    }
}
