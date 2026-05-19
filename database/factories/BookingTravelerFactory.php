<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'document_type' => fake()->randomElement(['passport', 'national_id']),
            'document_number' => fake()->bothify('??######'),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-12 years'),
            'nationality' => fake()->countryCode(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'dietary_restrictions' => null,
            'medical_notes' => null,
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
        ];
    }
}
