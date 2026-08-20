<?php

declare(strict_types=1);

namespace App\Http\Requests\Passenger;

use App\Http\Requests\Passenger\Concerns\PassengerPayload;
use App\Models\BookingTraveler;
use Illuminate\Foundation\Http\FormRequest;

final class StorePassengerRequest extends FormRequest
{
    use PassengerPayload;

    public function authorize(): bool
    {
        return $this->user()?->can('create', BookingTraveler::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->passengerRules(isMinorRequired: true);
    }

    protected function prepareForValidation(): void
    {
        $this->maskMedicalInput(null);
    }
}
