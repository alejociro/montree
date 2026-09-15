<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\DepartureScope;
use App\Enums\TourDateDisplayStatus;
use App\Enums\TourDateStatus;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Database\Factories\TourDateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tour_id
 * @property int $guide_id
 * @property int|null $route_id
 * @property int|null $provider_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int $capacity
 * @property int $booked_count
 * @property string|null $price_override
 * @property int|null $min_payment_pct
 * @property TourDateStatus $status
 * @property string|null $notes
 */
class TourDate extends Model
{
    /** @use HasFactory<TourDateFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tour_id',
        'guide_id',
        'route_id',
        'provider_id',
        'starts_at',
        'ends_at',
        'capacity',
        'booked_count',
        'price_override',
        'min_payment_pct',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'booked_count' => 'integer',
            'price_override' => 'decimal:2',
            'min_payment_pct' => 'integer',
            'status' => TourDateStatus::class,
        ];
    }

    /**
     * Porcentaje que asegura la plaza en esta salida: el override de la salida
     * manda y, sin él, rige el de la agencia.
     *
     * WHY: el fallback sale del tenant actual —ya cacheado con su
     * configuración— y no de `$this->tenant`, porque en un listado paginado eso
     * sería una consulta por salida.
     */
    public function minPaymentPercentage(): int
    {
        return $this->min_payment_pct
            ?? Tenant::current()?->configuration?->min_partial_payment_pct
            ?? Booking::DEFAULT_MIN_PAYMENT_PCT;
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function guide(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guide_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'tour_date_hotels');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * El fin de una salida no es un dato del cliente: sale de la duración del
     * tour (D9). Un `ends_at` que miente es peor que uno vacío, porque la regla
     * de disponibilidad se lo cree.
     */
    public static function deriveEndsAt(CarbonInterface $startsAt, int $durationHours): CarbonInterface
    {
        return $startsAt->copy()->addHours($durationHours);
    }

    /**
     * Días calendario que la salida le ocupa al guía: `[date(starts_at) …
     * date(ends_at)]`. Un tour de 5 días bloquea los 5 aunque el último termine
     * a las 9 de la mañana (D9).
     */
    public function occupiedDays(): CarbonPeriod
    {
        $end = $this->ends_at ?? $this->starts_at;

        return CarbonPeriod::create(
            $this->starts_at->copy()->startOfDay(),
            '1 day',
            $end->copy()->startOfDay(),
        );
    }

    /**
     * Salidas que ocupan al guía. Solo una `cancelled` libera sus días (D9).
     *
     * WHY: `full` ocupa igual que `open`. Agotada quiere decir que se vendió
     * entera, no que no se vaya a hacer: el guía sale esos días. Lo que decide
     * la ocupación es si la salida ocurre, no si le quedan cupos.
     *
     * @param  Builder<TourDate>  $query
     * @return Builder<TourDate>
     */
    public function scopeOccupying(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TourDateStatus::Open,
            TourDateStatus::Full,
            TourDateStatus::Closed,
        ]);
    }

    /**
     * @param  Builder<TourDate>  $query
     * @return Builder<TourDate>
     */
    public function scopeOpenFuture(Builder $query): Builder
    {
        return $query->where('status', TourDateStatus::Open)->where('starts_at', '>', now());
    }

    /**
     * Presentation-only status derived from the stored status and the schedule
     * window. No column backs this; it is computed on read.
     *
     * Precedence: a cancelled departure always maps to Cancelled. Otherwise,
     * using end = ends_at ?? starts_at: a window already elapsed maps to
     * Finished, a window currently open (only possible when ends_at is set)
     * maps to InProgress, and any other case maps the stored status directly.
     */
    public function displayStatus(): TourDateDisplayStatus
    {
        if ($this->status === TourDateStatus::Cancelled) {
            return TourDateDisplayStatus::Cancelled;
        }

        $now = now();
        $end = $this->ends_at ?? $this->starts_at;

        if ($end->lt($now)) {
            return TourDateDisplayStatus::Finished;
        }

        if ($this->starts_at->lte($now) && $now->lte($end)) {
            return TourDateDisplayStatus::InProgress;
        }

        return match ($this->status) {
            TourDateStatus::Open => TourDateDisplayStatus::Open,
            TourDateStatus::Full => TourDateDisplayStatus::Full,
            TourDateStatus::Closed => TourDateDisplayStatus::Closed,
            TourDateStatus::Cancelled => TourDateDisplayStatus::Cancelled,
        };
    }

    /**
     * Filters departures by their derived display status. Mirrors the
     * derivation in displayStatus() at the SQL level so it can be paginated.
     *
     * @param  Builder<TourDate>  $query
     * @return Builder<TourDate>
     */
    public function scopeWithDisplayStatus(Builder $query, TourDateDisplayStatus $displayStatus): Builder
    {
        $now = now();

        return match ($displayStatus) {
            TourDateDisplayStatus::Cancelled => $query->where('status', TourDateStatus::Cancelled),
            TourDateDisplayStatus::Finished => $query
                ->where('status', '!=', TourDateStatus::Cancelled)
                ->whereRaw('COALESCE(ends_at, starts_at) < ?', [$now]),
            TourDateDisplayStatus::InProgress => $query
                ->where('status', '!=', TourDateStatus::Cancelled)
                ->whereNotNull('ends_at')
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now),
            TourDateDisplayStatus::Open,
            TourDateDisplayStatus::Full,
            TourDateDisplayStatus::Closed => $query
                ->where('status', $displayStatus->value)
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [$now])
                ->whereNot(fn (Builder $inProgress) => $inProgress
                    ->whereNotNull('ends_at')
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now)),
        };
    }

    /**
     * Código legible de la salida: `TD<tour>-<mmdd>`. No hay columna que lo
     * respalde —se deriva del tour y de la fecha— y es lo que el operador dicta
     * por teléfono, así que el buscador tiene que entenderlo.
     */
    public function code(): string
    {
        return sprintf('TD%d-%s', $this->tour_id, $this->starts_at->format('md'));
    }

    /**
     * Bandeja del tablero de salidas.
     *
     * @param  Builder<TourDate>  $query
     * @return Builder<TourDate>
     */
    public function scopeInScope(Builder $query, DepartureScope $scope): Builder
    {
        $now = now();

        return match ($scope) {
            DepartureScope::All => $query,
            DepartureScope::Disabled => $query->where('status', TourDateStatus::Cancelled),
            DepartureScope::Upcoming => $query
                ->where('status', '!=', TourDateStatus::Cancelled)
                ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [$now]),
            DepartureScope::Past => $query
                ->where('status', '!=', TourDateStatus::Cancelled)
                ->whereRaw('COALESCE(ends_at, starts_at) < ?', [$now]),
            DepartureScope::Today => $query
                ->where('status', '!=', TourDateStatus::Cancelled)
                ->whereBetween('starts_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()]),
        };
    }

    /**
     * Buscador del tablero: nombre del tour, nombre del guía y el código
     * derivado `TD<tour>-<mmdd>`. El código no está en ninguna columna, así que
     * se traduce a sus dos partes —el tour y el día— antes de consultar.
     *
     * @param  Builder<TourDate>  $query
     * @return Builder<TourDate>
     */
    public function scopeMatchingSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $scoped) use ($like, $term): void {
            $scoped
                ->whereHas('tour', fn (Builder $tour) => $tour->where('name', 'like', $like))
                ->orWhereHas('guide', fn (Builder $guide) => $guide->where('name', 'like', $like));

            // `whereMonth`/`whereDay` en vez de un formateo de fecha: cada
            // motor tiene el suyo (`DATE_FORMAT` es de MySQL) y la suite corre
            // sobre SQLite.
            if (preg_match('/^TD(\d+)-(\d{2})(\d{2})$/i', $term, $matches) === 1) {
                $scoped->orWhere(fn (Builder $byCode) => $byCode
                    ->where('tour_id', (int) $matches[1])
                    ->whereMonth('starts_at', (int) $matches[2])
                    ->whereDay('starts_at', (int) $matches[3]));
            }
        });
    }
}
