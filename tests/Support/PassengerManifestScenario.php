<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\BookingStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingTraveler;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;

/**
 * Andamiaje de la planilla: una agencia, un tour, una salida con guía y las
 * reservas que hagan falta. Es la misma escena en los seis archivos de prueba
 * del feature; lo específico de cada caso se arma en el propio test.
 */
trait PassengerManifestScenario
{
    protected function tenantAt(string $slug = 'demo'): Tenant
    {
        $tenant = Tenant::factory()->create([
            'slug' => $slug,
            'domain' => "{$slug}.montree.test",
        ]);

        $tenant->makeCurrent();

        return $tenant;
    }

    protected function memberOf(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }

    protected function departureFor(User $guide, ?Tour $tour = null): TourDate
    {
        return TourDate::factory()
            ->for($tour ?? Tour::factory()->create())
            ->create(['guide_id' => $guide->id, 'capacity' => 20]);
    }

    protected function bookingOn(TourDate $departure, int $travelers = 1, string $total = '400000.00', string $paid = '400000.00'): Booking
    {
        return Booking::factory()
            ->for(User::factory())
            ->for($departure->tour)
            ->for($departure)
            ->create([
                'status' => BookingStatus::Confirmed,
                'travelers_count' => $travelers,
                'adults_count' => $travelers,
                'minors_count' => 0,
                'subtotal' => $total,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'currency' => 'COP',
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function passengerOn(Booking $booking, array $attributes = []): BookingTraveler
    {
        return BookingTraveler::factory()->for($booking)->create($attributes);
    }

    protected function host(Tenant $tenant): string
    {
        return "http://{$tenant->domain}";
    }
}
