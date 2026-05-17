<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantConfigurationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $primary_color
 * @property string $secondary_color
 * @property string|null $logo_path
 * @property string|null $favicon_path
 * @property string|null $hero_image_path
 * @property string $currency
 * @property string $timezone
 * @property string $locale
 * @property string|null $tagline
 * @property string|null $description
 * @property array<string, string>|null $social_links
 * @property array<string, string>|null $contact_info
 * @property string|null $custom_css
 * @property bool $reviews_require_moderation
 * @property bool $require_traveler_details
 * @property int $booking_advance_hours
 * @property int $booking_expiration_minutes
 * @property int $min_partial_payment_pct
 */
class TenantConfiguration extends Model
{
    /** @use HasFactory<TenantConfigurationFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'primary_color',
        'secondary_color',
        'logo_path',
        'favicon_path',
        'hero_image_path',
        'currency',
        'timezone',
        'locale',
        'tagline',
        'description',
        'social_links',
        'contact_info',
        'custom_css',
        'reviews_require_moderation',
        'require_traveler_details',
        'booking_advance_hours',
        'booking_expiration_minutes',
        'min_partial_payment_pct',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'contact_info' => 'array',
            'reviews_require_moderation' => 'boolean',
            'require_traveler_details' => 'boolean',
            'booking_advance_hours' => 'integer',
            'booking_expiration_minutes' => 'integer',
            'min_partial_payment_pct' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
