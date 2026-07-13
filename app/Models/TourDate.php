<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\TourDateDisplayStatus;
use App\Enums\TourDateStatus;
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
 * @property int|null $guide_id
 * @property int|null $route_id
 * @property int|null $provider_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int $capacity
 * @property int $booked_count
 * @property string|null $price_override
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
            'status' => TourDateStatus::class,
        ];
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
}
