<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\AccommodationType;
use App\Enums\CancellationPolicy;
use App\Enums\MealPlan;
use App\Enums\PaymentTerms;
use Carbon\CarbonImmutable;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property AccommodationType|null $accommodation_type
 * @property int|null $star_rating
 * @property string|null $description
 * @property string|null $legal_name
 * @property string|null $tax_id
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $directions
 * @property int|null $total_capacity
 * @property string|null $currency
 * @property CarbonImmutable|null $rates_valid_until
 * @property string|null $check_in
 * @property string|null $check_out
 * @property array<int, string>|null $amenities
 * @property MealPlan|null $meal_plan
 * @property string|null $diets
 * @property string|null $restrictions
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $emergency_contact
 * @property CancellationPolicy|null $cancellation_policy
 * @property PaymentTerms|null $payment_terms
 * @property string|null $notes
 */
final class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'accommodation_type',
        'star_rating',
        'description',
        'legal_name',
        'tax_id',
        'address',
        'latitude',
        'longitude',
        'city',
        'state',
        'country',
        'directions',
        'total_capacity',
        'currency',
        'rates_valid_until',
        'check_in',
        'check_out',
        'amenities',
        'meal_plan',
        'diets',
        'restrictions',
        'contact_name',
        'contact_phone',
        'contact_email',
        'emergency_contact',
        'cancellation_policy',
        'payment_terms',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'accommodation_type' => AccommodationType::class,
            'star_rating' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'total_capacity' => 'integer',
            'rates_valid_until' => 'immutable_date',
            'amenities' => 'array',
            'meal_plan' => MealPlan::class,
            'cancellation_policy' => CancellationPolicy::class,
            'payment_terms' => PaymentTerms::class,
        ];
    }

    public function tourDates(): BelongsToMany
    {
        return $this->belongsToMany(TourDate::class, 'tour_date_hotels');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HotelRoom::class)->orderBy('position');
    }
}
