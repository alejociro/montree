<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

use App\Enums\AccommodationType;
use App\Enums\CancellationPolicy;
use App\Enums\HotelAmenity;
use App\Enums\MealPlan;
use App\Enums\PaymentTerms;
use App\Enums\ProviderDocumentType;
use App\Enums\ProviderServiceType;
use App\Enums\RateUnit;
use App\Enums\RouteKind;
use App\Enums\RouteSeason;
use App\Enums\TaxRegime;
use App\Enums\TourDifficulty;
use App\Enums\TourStopKind;
use Illuminate\Validation\Rule;

/**
 * Reglas de las tres fichas de logística, en un solo sitio.
 *
 * Solo el nombre es obligatorio. El rediseño marca con asterisco una docena de
 * campos por ficha, pero esa exigencia vive en el formulario —que cuenta cuántos
 * faltan y bloquea «Guardar ficha»— y no en el servidor: si el servidor los
 * exigiera, cada proveedor y cada hotel ya cargados quedarían inedit­ables hasta
 * que alguien les tecleara el NIT, y «Guardar borrador» no podría existir.
 */
final class LogisticsRules
{
    /** Monedas del panel; mismas que en el CRUD de tours. */
    private const CURRENCIES = ['USD', 'COP', 'EUR', 'MXN', 'ARS', 'PEN', 'CLP', 'BRL'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function route(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['nullable', Rule::enum(RouteKind::class)],
            'difficulty' => ['nullable', Rule::enum(TourDifficulty::class)],
            'start_point' => ['nullable', 'string', 'max:500'],
            'start_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'end_point' => ['nullable', 'string', 'max:500'],
            'end_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'end_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'max_altitude_m' => ['nullable', 'integer', 'min:0', 'max:9000'],
            'elevation_gain_m' => ['nullable', 'integer', 'min:0', 'max:9000'],
            'group_capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'seasons' => ['nullable', 'array', 'max:4'],
            'seasons.*' => [Rule::enum(RouteSeason::class)],
            'safety_notes' => ['nullable', 'string', 'max:2000'],
            'required_gear' => ['nullable', 'array', 'max:20'],
            'required_gear.*' => ['string', 'max:80'],
            'permits' => ['nullable', 'string', 'max:500'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'stops' => ['nullable', 'array', 'max:40'],
            'stops.*.name' => ['required', 'string', 'max:160'],
            'stops.*.kind' => ['required', Rule::enum(TourStopKind::class)],
            'stops.*.time_label' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function provider(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['nullable', Rule::enum(ProviderServiceType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'tax_regime' => ['nullable', Rule::enum(TaxRegime::class)],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', Rule::enum(PaymentTerms::class)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_role' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'alternate_contact' => ['nullable', 'string', 'max:255'],
            'service_hours' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'coverage' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(self::CURRENCIES)],
            'rates_valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rates' => ['nullable', 'array', 'max:30'],
            'rates.*.concept' => ['required', 'string', 'max:160'],
            'rates.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'rates.*.unit' => ['required', Rule::enum(RateUnit::class)],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*.kind' => ['required', Rule::enum(ProviderDocumentType::class)],
            'documents.*.number' => ['nullable', 'string', 'max:100'],
            'documents.*.expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function hotel(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'accommodation_type' => ['nullable', Rule::enum(AccommodationType::class)],
            'star_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string', 'max:2000'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'directions' => ['nullable', 'string', 'max:2000'],
            'total_capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(self::CURRENCIES)],
            'rates_valid_until' => ['nullable', 'date'],
            'check_in' => ['nullable', 'string', 'max:30'],
            'check_out' => ['nullable', 'string', 'max:30'],
            'amenities' => ['nullable', 'array', 'max:20'],
            'amenities.*' => [Rule::enum(HotelAmenity::class)],
            'meal_plan' => ['nullable', Rule::enum(MealPlan::class)],
            'diets' => ['nullable', 'string', 'max:255'],
            'restrictions' => ['nullable', 'string', 'max:2000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'cancellation_policy' => ['nullable', Rule::enum(CancellationPolicy::class)],
            'payment_terms' => ['nullable', Rule::enum(PaymentTerms::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rooms' => ['nullable', 'array', 'max:30'],
            'rooms.*.name' => ['required', 'string', 'max:160'],
            'rooms.*.quantity' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'rooms.*.nightly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * En update el nombre sigue sin poder quedar vacío, pero puede no venir:
     * el formulario manda la ficha entera y el resto de superficies parchea
     * campos sueltos.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    public static function forUpdate(array $rules): array
    {
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        return $rules;
    }
}
