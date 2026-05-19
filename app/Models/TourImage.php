<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\TourImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tour_id
 * @property string $path
 * @property string|null $alt_text
 * @property int $display_order
 * @property bool $is_cover
 */
class TourImage extends Model
{
    /** @use HasFactory<TourImageFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'tour_id',
        'path',
        'alt_text',
        'display_order',
        'is_cover',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_cover' => 'boolean',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}
