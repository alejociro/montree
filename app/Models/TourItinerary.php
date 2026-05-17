<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\TourItineraryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tour_id
 * @property int $step_number
 * @property string $title
 * @property string $description
 * @property string|null $duration_label
 */
class TourItinerary extends Model
{
    /** @use HasFactory<TourItineraryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tour_id',
        'step_number',
        'title',
        'description',
        'duration_label',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
