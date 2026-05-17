<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property PromotionType $type
 * @property string $value
 * @property string|null $min_amount
 * @property string|null $max_discount
 * @property int|null $max_uses
 * @property int $uses_count
 * @property int|null $max_uses_per_user
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property array<int, int>|null $applicable_tours
 * @property bool $is_active
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_amount',
        'max_discount',
        'max_uses',
        'uses_count',
        'max_uses_per_user',
        'starts_at',
        'ends_at',
        'applicable_tours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'max_uses_per_user' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'applicable_tours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
