<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RateUnit;
use Database\Factories\ProviderRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $provider_id
 * @property int $position
 * @property string $concept
 * @property string|null $amount
 * @property RateUnit $unit
 */
final class ProviderRate extends Model
{
    /** @use HasFactory<ProviderRateFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'position',
        'concept',
        'amount',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'amount' => 'decimal:2',
            'unit' => RateUnit::class,
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
