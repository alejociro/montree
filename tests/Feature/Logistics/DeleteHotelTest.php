<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class DeleteHotelTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_deleting_an_unused_hotel_succeeds(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $hotel = Hotel::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/hotels/{$hotel->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('hotels', ['id' => $hotel->id]);
    }

    public function test_deleting_a_hotel_used_by_a_departure_is_blocked(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $hotel = Hotel::factory()->create();
        $tour = Tour::factory()->create();
        $departure = TourDate::factory()->for($tour)->create();
        $departure->hotels()->attach($hotel->id);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/hotels/{$hotel->id}");

        $response->assertSessionHasErrors('hotel');
        $this->assertDatabaseHas('hotels', ['id' => $hotel->id]);
    }

    public function test_deleting_a_hotel_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $hotelB = Hotel::factory()->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->delete($this->host($tenantA)."/admin/hotels/{$hotelB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('hotels', ['id' => $hotelB->id]);
    }
}
