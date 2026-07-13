<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string|null $distance_km
 * @property string|null $duration_hours
 */
final class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'distance_km',
        'duration_hours',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'decimal:2',
            'duration_hours' => 'decimal:1',
        ];
    }

    public function tourDates(): HasMany
    {
        return $this->hasMany(TourDate::class);
    }
}
