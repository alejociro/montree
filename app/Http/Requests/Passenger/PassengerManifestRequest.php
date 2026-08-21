<?php

declare(strict_types=1);

namespace App\Http\Requests\Passenger;

use App\Data\PassengerManifestFilters;
use App\Enums\BookingStatus;
use App\Models\BookingTraveler;
use App\Models\Tour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query params de la planilla, en sus cuatro entradas (index y export de cada
 * zona).
 *
 * WHY: el permiso de entrada lo pone la ruta (`can:bookings.view` en el panel,
 * `can:guide.travelers.view` en la zona del guía) porque no es el mismo en las
 * dos y el Form Request sí lo es. Lo que se autoriza aquí es el segmento
 * «Con observaciones»: filtrar por él delata quién tiene una condición médica
 * sin mostrarla, así que sin el permiso médico es 403 (D7).
 */
final class PassengerManifestRequest extends FormRequest
{
    private const DEFAULT_STATUSES = [BookingStatus::Confirmed, BookingStatus::Completed];

    public function authorize(): bool
    {
        if ($this->input('segment') !== 'obs') {
            return true;
        }

        return $this->user()?->can('viewMedical', BookingTraveler::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tour_date_id' => ['nullable', 'integer', $this->departureRule()],
            'segment' => ['nullable', Rule::in(PassengerManifestFilters::SEGMENTS)],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(BookingStatus::class)],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  array<int, BookingStatus>|null  $statuses  la zona del guía no negocia los
     *                                                    estados: solo `confirmed` y
     *                                                    `completed` suben al vehículo.
     */
    public function filters(string $fallbackCurrency, ?array $statuses = null): PassengerManifestFilters
    {
        return new PassengerManifestFilters(
            segment: (string) ($this->validated('segment') ?? 'all'),
            search: $this->validated('q'),
            statuses: $statuses ?? $this->statuses(),
            fallbackCurrency: $fallbackCurrency,
            perPage: (int) ($this->validated('per_page') ?? 50),
        );
    }

    public function tourDateId(): ?int
    {
        $value = $this->validated('tour_date_id');

        return $value === null ? null : (int) $value;
    }

    public function page(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    /**
     * @return array<int, BookingStatus>
     */
    private function statuses(): array
    {
        $statuses = $this->validated('status');

        if (! is_array($statuses) || $statuses === []) {
            return self::DEFAULT_STATUSES;
        }

        return array_map(static fn (string $status): BookingStatus => BookingStatus::from($status), $statuses);
    }

    /**
     * `tour_date_id` que no pertenece al tour es 422, no un filtro vacío: el
     * frontend estaría pidiendo una salida que su selector no ofreció.
     */
    private function departureRule(): mixed
    {
        $tour = $this->route('tour');

        if (! $tour instanceof Tour) {
            return 'nullable';
        }

        return Rule::exists('tour_dates', 'id')
            ->where('tour_id', $tour->id)
            ->where('tenant_id', $tour->tenant_id);
    }
}
