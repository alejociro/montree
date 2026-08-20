<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\Eps;
use App\Models\Booking;
use App\Models\BookingTraveler;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingTraveler>
 */
class BookingTravelerFactory extends Factory
{
    protected $model = BookingTraveler::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'full_name' => fake()->name(),
            'is_minor' => false,
            'document_type' => fake()->randomElement([DocumentType::Cc, DocumentType::Ce, DocumentType::Passport]),
            'document_number' => fake()->bothify('??######'),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-12 years'),
            'nationality' => fake()->countryCode(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'dietary_restrictions' => null,
            // WHY: el dato de salud nace vacío. Es opcional para el viajero y las
            // pruebas de privacidad necesitan poder distinguir «no lo cargó» de
            // «no lo puedo ver».
            'medical_notes' => null,
            'eps' => null,
            'eps_other' => null,
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_relationship' => fake()->randomElement(['Madre', 'Padre', 'Hermano', 'Hermana', 'Pareja', 'Amigo']),
            'emergency_contact_phone' => fake()->phoneNumber(),
        ];
    }

    public function minor(): self
    {
        return $this->state(fn (array $attrs) => [
            'is_minor' => true,
            'birth_date' => fake()->dateTimeBetween('-17 years', '-1 years'),
        ]);
    }

    /**
     * El saldo es de la reserva, no de la persona (D5): el estado se consigue
     * dejando la reserva a medio pagar.
     */
    public function withDue(): self
    {
        return $this->afterCreating(function (BookingTraveler $traveler): void {
            $booking = $traveler->booking()->withoutGlobalScopes()->first();

            if ($booking === null) {
                return;
            }

            $total = (float) $booking->total_amount;

            if ($total <= 0) {
                $total = 300000.0;
            }

            $booking->forceFill([
                'total_amount' => number_format($total, 2, '.', ''),
                'paid_amount' => number_format(round($total / 2, 2), 2, '.', ''),
            ])->save();
        });
    }

    public function withNotes(): self
    {
        return $this->state(fn () => [
            'medical_notes' => fake()->randomElement([
                'Alergia a la penicilina.',
                'Asma leve; lleva inhalador.',
                'Movilidad reducida en la rodilla derecha.',
            ]),
        ]);
    }

    public function withOtherEps(): self
    {
        return $this->state(fn () => [
            'eps' => Eps::Other,
            'eps_other' => fake()->randomElement(['Compensar', 'Famisanar', 'Coosalud']),
        ]);
    }
}
