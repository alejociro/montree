<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\DocumentType;
use App\Enums\Eps;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SyncBookingTravelersRequest extends FormRequest
{
    private ?Booking $resolvedBooking = null;

    private bool $bookingResolved = false;

    public function authorize(): bool
    {
        return Tenant::current() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'travelers' => ['required', 'array', 'min:1'],
            'travelers.*.id' => ['nullable', 'integer'],
            'travelers.*.full_name' => ['required', 'string', 'max:255'],
            'travelers.*.is_minor' => ['required', 'boolean'],
            'travelers.*.document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'travelers.*.document_number' => ['nullable', 'string', 'max:40', 'required_with:travelers.*.document_type'],
            'travelers.*.birth_date' => ['nullable', 'date', 'before:today'],
            'travelers.*.nationality' => ['nullable', 'string', 'max:255'],
            'travelers.*.email' => ['nullable', 'email', 'max:255'],
            'travelers.*.phone' => ['nullable', 'string', 'max:30'],
            'travelers.*.dietary_restrictions' => ['nullable', 'string', 'max:2000'],
            'travelers.*.medical_notes' => ['nullable', 'string', 'max:2000'],
            'travelers.*.emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'travelers.*.emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'travelers.*.emergency_contact_phone' => ['nullable', 'string', 'max:40', 'required_with:travelers.*.emergency_contact_name'],
            'travelers.*.eps' => ['nullable', Rule::enum(Eps::class)],
            // WHY: el texto libre solo se exige con «Otra». Si la EPS es otra
            // cualquiera, lo que llegue se ignora y se persiste `null`
            // (`UpdatePassengerAction`), no se rechaza la petición.
            'travelers.*.eps_other' => ['nullable', 'string', 'max:120', 'required_if:travelers.*.eps,'.Eps::Other->value],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v): void {
            $booking = $this->booking();
            if ($booking === null) {
                return;
            }

            /** @var array<int, array<string, mixed>> $travelers */
            $travelers = $this->input('travelers', []);

            $ownedIds = $booking->travelers()->pluck('id')->all();
            $adults = 0;
            $minors = 0;

            foreach ($travelers as $index => $traveler) {
                $id = $traveler['id'] ?? null;
                if ($id !== null && ! in_array((int) $id, $ownedIds, true)) {
                    $v->errors()->add("travelers.{$index}.id", __('El viajero no pertenece a esta reserva.'));
                }

                if ((bool) ($traveler['is_minor'] ?? false)) {
                    $minors++;
                } else {
                    $adults++;
                }
            }

            if ($adults > $booking->adults_count) {
                $v->errors()->add('travelers', __('La reserva admite máximo :count adultos.', ['count' => $booking->adults_count]));
            }

            if ($minors > $booking->minors_count) {
                $v->errors()->add('travelers', __('La reserva admite máximo :count menores.', ['count' => $booking->minors_count]));
            }
        });
    }

    public function booking(): ?Booking
    {
        if ($this->bookingResolved) {
            return $this->resolvedBooking;
        }

        $this->bookingResolved = true;
        $this->resolvedBooking = Booking::query()
            ->where('booking_number', (string) $this->route('bookingNumber'))
            ->where('user_id', $this->user()?->id)
            ->first();

        return $this->resolvedBooking;
    }
}
