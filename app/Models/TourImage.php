<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\TourImageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $tour_id
 * @property string $path
 * @property string|null $alt_text
 * @property int $display_order
 * @property bool $is_cover
 * @property-read string $url
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

    /**
     * WHY: seeded/demo images store absolute external URLs in `path`, while
     * tenant uploads store relative paths on the public disk.
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => str_starts_with($this->path, 'http')
            ? $this->path
            : Storage::disk('public')->url($this->path));
    }
}
