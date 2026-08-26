<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PaymentTerms;
use App\Enums\ProviderServiceType;
use App\Enums\TaxRegime;
use Carbon\CarbonImmutable;
use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property ProviderServiceType|null $service_type
 * @property string|null $description
 * @property string|null $legal_name
 * @property string|null $tax_id
 * @property TaxRegime|null $tax_regime
 * @property string|null $billing_email
 * @property string|null $bank_account
 * @property PaymentTerms|null $payment_terms
 * @property string|null $contact_name
 * @property string|null $contact_role
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $alternate_contact
 * @property string|null $service_hours
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $city
 * @property string|null $state
 * @property string|null $coverage
 * @property string|null $currency
 * @property CarbonImmutable|null $rates_valid_until
 * @property string|null $notes
 */
final class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'service_type',
        'description',
        'legal_name',
        'tax_id',
        'tax_regime',
        'billing_email',
        'bank_account',
        'payment_terms',
        'contact_name',
        'contact_role',
        'contact_phone',
        'contact_email',
        'alternate_contact',
        'service_hours',
        'address',
        'latitude',
        'longitude',
        'city',
        'state',
        'coverage',
        'currency',
        'rates_valid_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ProviderServiceType::class,
            'tax_regime' => TaxRegime::class,
            'payment_terms' => PaymentTerms::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rates_valid_until' => 'immutable_date',
        ];
    }

    public function tourDates(): HasMany
    {
        return $this->hasMany(TourDate::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ProviderRate::class)->orderBy('position');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProviderDocument::class)->orderBy('position');
    }
}
