<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\TourStopKind;
use Database\Factories\TourStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tour_id
 * @property int $position
 * @property TourStopKind $kind
 * @property string $code
 * @property string|null $label
 * @property string $name
 * @property string|null $place
 * @property string|null $time_label
 * @property string $latitude
 * @property string $longitude
 * @property int|null $itinerary_step
 */
class TourStop extends Model
{
    /** @use HasFactory<TourStopFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tour_id',
        'position',
        'kind',
        'code',
        'label',
        'name',
        'place',
        'time_label',
        'latitude',
        'longitude',
        'itinerary_step',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TourStopKind::class,
            'position' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'itinerary_step' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
