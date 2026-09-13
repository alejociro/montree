<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HotelRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hotel_id
 * @property int $position
 * @property string $name
 * @property int|null $quantity
 * @property string|null $nightly_rate
 */
final class HotelRoom extends Model
{
    /** @use HasFactory<HotelRoomFactory> */
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'position',
        'name',
        'quantity',
        'nightly_rate',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'nightly_rate' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
