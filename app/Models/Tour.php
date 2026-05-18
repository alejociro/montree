<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\TourDifficulty;
use App\Enums\TourStatus;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string $description
 * @property int $duration_hours
 * @property TourDifficulty $difficulty
 * @property string $base_price
 * @property string $currency
 * @property int $default_capacity
 * @property string|null $meeting_point
 * @property string|null $meeting_latitude
 * @property string|null $meeting_longitude
 * @property array<int, string>|null $includes
 * @property array<int, string>|null $excludes
 * @property array<int, string>|null $requirements
 * @property TourStatus $status
 * @property string $rating_average
 * @property int $rating_count
 */
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'duration_hours',
        'difficulty',
        'base_price',
        'currency',
        'default_capacity',
        'meeting_point',
        'meeting_latitude',
        'meeting_longitude',
        'includes',
        'excludes',
        'requirements',
        'status',
        'rating_average',
        'rating_count',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => TourDifficulty::class,
            'status' => TourStatus::class,
            'includes' => 'array',
            'excludes' => 'array',
            'requirements' => 'array',
            'base_price' => 'decimal:2',
            'meeting_latitude' => 'decimal:7',
            'meeting_longitude' => 'decimal:7',
            'rating_average' => 'decimal:2',
            'duration_hours' => 'integer',
            'default_capacity' => 'integer',
            'rating_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(TourImage::class)->orderBy('display_order');
    }

    public function itineraries(): HasMany
    {
        return $this->hasMany(TourItinerary::class)->orderBy('step_number');
    }

    public function dates(): HasMany
    {
        return $this->hasMany(TourDate::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function coverImage(): HasOne
    {
        return $this->hasOne(TourImage::class)->where('is_cover', true);
    }
}
