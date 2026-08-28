<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TourStopKind;
use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int $position
 * @property string $name
 * @property TourStopKind $kind
 * @property string|null $time_label
 */
final class RouteStop extends Model
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory;

    protected $fillable = [
        'route_id',
        'position',
        'name',
        'kind',
        'time_label',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'kind' => TourStopKind::class,
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}
