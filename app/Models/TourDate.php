<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\TourDateStatus;
use Database\Factories\TourDateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tour_id
 * @property int|null $guide_id
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
