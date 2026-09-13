<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProviderDocumentType;
use Carbon\CarbonImmutable;
use Database\Factories\ProviderDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $provider_id
 * @property int $position
 * @property ProviderDocumentType $kind
 * @property string|null $number
 * @property CarbonImmutable|null $expires_at
 */
final class ProviderDocument extends Model
{
    /** @use HasFactory<ProviderDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'position',
        'kind',
        'number',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'kind' => ProviderDocumentType::class,
            'expires_at' => 'immutable_date',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
