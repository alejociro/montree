<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\RouteKind;
use App\Enums\TourDifficulty;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property RouteKind|null $kind
 * @property TourDifficulty|null $difficulty
 * @property string|null $start_point
 * @property string|null $start_latitude
 * @property string|null $start_longitude
 * @property string|null $end_point
 * @property string|null $end_latitude
 * @property string|null $end_longitude
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $distance_km
 * @property string|null $duration_hours
 * @property int|null $max_altitude_m
 * @property int|null $elevation_gain_m
 * @property int|null $group_capacity
 * @property array<int, string>|null $seasons
 * @property string|null $safety_notes
 * @property array<int, string>|null $required_gear
 * @property string|null $permits
 * @property string|null $emergency_contact
 */
final class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'kind',
        'difficulty',
        'start_point',
        'start_latitude',
        'start_longitude',
        'end_point',
        'end_latitude',
        'end_longitude',
        'city',
        'state',
        'country',
        'distance_km',
        'duration_hours',
        'max_altitude_m',
        'elevation_gain_m',
        'group_capacity',
        'seasons',
        'safety_notes',
        'required_gear',
        'permits',
        'emergency_contact',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'duration_hours' => 'decimal:1',
            'kind' => RouteKind::class,
            'difficulty' => TourDifficulty::class,
            'start_latitude' => 'decimal:7',
            'start_longitude' => 'decimal:7',
            'end_latitude' => 'decimal:7',
            'end_longitude' => 'decimal:7',
            'max_altitude_m' => 'integer',
            'elevation_gain_m' => 'integer',
            'group_capacity' => 'integer',
            'seasons' => 'array',
            'required_gear' => 'array',
        ];
    }

    public function tourDates(): HasMany
    {
        return $this->hasMany(TourDate::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('position');
    }
}
