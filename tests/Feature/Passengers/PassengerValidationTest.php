<?php

declare(strict_types=1);

namespace Tests\Feature\Passengers;

use App\Enums\Eps;
use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Captura del dato de salud y de emergencia por el propio viajero
 * (`PUT /api/v1/bookings/{bookingNumber}/travelers`).
 */
final class PassengerValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User, 2: Booking}
     */
    private function setupBooking(string $slug = 'demo', int $adults = 2): array
    {
        $tenant = Tenant::factory()->create(['slug' => $slug, 'domain' => "{$slug}.montree.test"]);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'capacity' => 20,
            'booked_count' => 0,
            'status' => TourDateStatus::Open,
        ]);

        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $booking = Booking::factory()
            ->for($user)
            ->for($tour)
            ->for($tourDate)
            ->create(['adults_count' => $adults, 'minors_count' => 0, 'travelers_count' => $adults]);

        return [$tenant, $user, $booking];
    }

    public function test_owner_stores_health_and_emergency_fields(): void
    {
        [, $user, $booking] = $this->setupBooking();

        $response = $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [[
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'document_type' => 'cc',
                'document_number' => '1017234567',
                'email' => 'maria@example.com',
                'emergency_contact_name' => 'Julian Rios',
                'emergency_contact_relationship' => 'Hermano',
                'emergency_contact_phone' => '+57 311 222 3344',
                'eps' => 'other',
                'eps_other' => 'Compensar',
                'medical_notes' => 'Alergia a la penicilina.',
            ]],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.travelers.0.eps', 'other')
            ->assertJsonPath('data.travelers.0.eps_other', 'Compensar')
            ->assertJsonPath('data.travelers.0.emergency_contact_relationship', 'Hermano');

        $this->assertDatabaseHas('booking_travelers', [
            'booking_id' => $booking->id,
            'eps' => Eps::Other->value,
            'eps_other' => 'Compensar',
            'emergency_contact_name' => 'Julian Rios',
            'emergency_contact_relationship' => 'Hermano',
            'emergency_contact_phone' => '+57 311 222 3344',
            'medical_notes' => 'Alergia a la penicilina.',
        ]);
    }

    public function test_rejects_missing_eps_other_when_eps_is_other(): void
    {
        [, $user, $booking] = $this->setupBooking();

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [[
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'eps' => 'other',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('travelers.0.eps_other');
    }

    public function test_nullifies_eps_other_when_eps_is_not_other(): void
    {
        [, $user, $booking] = $this->setupBooking();

        $response = $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [[
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'eps' => 'sura',
                'eps_other' => 'Compensar',
            ]],
        ]);

        $response->assertOk()->assertJsonPath('data.travelers.0.eps_other', null);

        $this->assertDatabaseHas('booking_travelers', [
            'booking_id' => $booking->id,
            'eps' => Eps::Sura->value,
            'eps_other' => null,
        ]);
    }

    public function test_clears_eps_other_when_the_traveler_moves_away_from_other(): void
    {
        [, $user, $booking] = $this->setupBooking();

        $traveler = BookingTraveler::factory()->for($booking)->withOtherEps()->create(['full_name' => 'Maria Fernanda Rios']);

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [[
                'id' => $traveler->id,
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'eps' => 'sanitas',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('booking_travelers', [
            'id' => $traveler->id,
            'eps' => Eps::Sanitas->value,
            'eps_other' => null,
        ]);
    }

    public function test_rejects_an_eps_outside_the_catalog(): void
    {
        [, $user, $booking] = $this->setupBooking();

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [[
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'eps' => 'coomeva',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('travelers.0.eps');
    }

    public function test_requires_the_emergency_phone_when_a_contact_is_given(): void
    {
        [, $user, $booking] = $this->setupBooking();

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}/travelers", [
            'travelers' => [[
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'emergency_contact_name' => 'Julian Rios',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('travelers.0.emergency_contact_phone');
    }

    public function test_owner_reads_back_the_health_fields(): void
    {
        [, $user, $booking] = $this->setupBooking();

        BookingTraveler::factory()->for($booking)->withOtherEps()->withNotes()->create([
            'full_name' => 'Maria Fernanda Rios',
            'emergency_contact_name' => 'Julian Rios',
            'emergency_contact_relationship' => 'Hermano',
        ]);

        $this->actingAs($user)
            ->getJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}")
            ->assertOk()
            ->assertJsonPath('data.travelers.0.eps', Eps::Other->value)
            ->assertJsonPath('data.travelers.0.eps_label', 'Otra')
            ->assertJsonPath('data.travelers.0.emergency_contact_relationship', 'Hermano')
            ->assertJsonStructure(['data' => ['travelers' => [['eps_other', 'medical_notes', 'dietary_restrictions']]]]);
    }

    public function test_health_fields_do_not_reach_a_non_owner(): void
    {
        [$tenant, , $booking] = $this->setupBooking();

        BookingTraveler::factory()->for($booking)->withOtherEps()->withNotes()->create();

        $other = User::factory()->create();
        $tenant->users()->attach($other->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($other)
            ->getJson("http://demo.montree.test/api/v1/bookings/{$booking->booking_number}");

        $response->assertStatus(404);
        $response->assertDontSee('eps_other');
        $response->assertDontSee('medical_notes');
    }

    public function test_cannot_write_health_fields_on_a_booking_of_another_tenant(): void
    {
        [, , $foreignBooking] = $this->setupBooking('other');
        Tenant::forgetCurrent();

        [, $user] = $this->setupBooking();

        $this->actingAs($user)->putJson("http://demo.montree.test/api/v1/bookings/{$foreignBooking->booking_number}/travelers", [
            'travelers' => [[
                'full_name' => 'Maria Fernanda Rios',
                'is_minor' => false,
                'eps' => 'sura',
            ]],
        ])->assertStatus(404);

        $this->assertDatabaseMissing('booking_travelers', [
            'booking_id' => $foreignBooking->id,
            'eps' => Eps::Sura->value,
        ]);
    }
}
