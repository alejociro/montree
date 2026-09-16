<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\AccommodationType;
use App\Enums\CancellationPolicy;
use App\Enums\PaymentTerms;
use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class CreateHotelTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_creates_a_hotel(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/hotels',
            ['name' => 'Ecohotel La Montaña', 'contact_email' => 'hola@montana.test'],
        );

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('hotels', ['name' => 'Ecohotel La Montaña', 'tenant_id' => $tenant->id]);
    }

    public function test_it_stores_a_hotel_with_rooms_and_amenities(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/hotels',
            [
                'name' => 'Ecohotel La Montaña',
                'accommodation_type' => AccommodationType::Ecolodge->value,
                'star_rating' => 3,
                'tax_id' => '900.884.201-1',
                'address' => 'Vereda El Castillo, km 4',
                'city' => 'Salento',
                'total_capacity' => 28,
                'currency' => 'USD',
                'check_in' => '3:00 p. m.',
                'check_out' => '11:00 a. m.',
                'amenities' => ['breakfast', 'wifi', 'hot_water'],
                'cancellation_policy' => CancellationPolicy::Free48Hours->value,
                'payment_terms' => PaymentTerms::Advance30->value,
                'contact_name' => 'Recepción',
                'contact_phone' => '+57 312 400 1188',
                'rooms' => [
                    ['name' => 'Doble', 'quantity' => 6, 'nightly_rate' => 62],
                    ['name' => 'Familiar', 'quantity' => 2, 'nightly_rate' => 95],
                ],
            ],
        );

        $response->assertSessionHas('success');

        $hotel = Hotel::query()->firstOrFail();
        $this->assertSame(AccommodationType::Ecolodge, $hotel->accommodation_type);
        $this->assertSame(3, $hotel->star_rating);
        $this->assertCount(3, $hotel->amenities);
        $this->assertSame(2, $hotel->rooms()->count());
        $this->assertSame('Doble', $hotel->rooms()->orderBy('position')->firstOrFail()->name);
    }

    public function test_creating_a_hotel_without_a_name_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post($this->host($tenant).'/admin/hotels', ['city' => 'Salento'])
            ->assertSessionHasErrors('name');
    }

    public function test_the_new_hotel_belongs_to_the_acting_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $this->actingAs($adminA)
            ->post($this->host($tenantA).'/admin/hotels', ['name' => 'Solo de Alpha'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('hotels', ['name' => 'Solo de Alpha', 'tenant_id' => $tenantA->id]);
    }
}
