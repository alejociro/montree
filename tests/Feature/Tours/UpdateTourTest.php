<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class UpdateTourTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_update_replaces_the_itinerary(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['name' => 'Original Tour']);
        $tour->itineraries()->create(['step_number' => 1, 'title' => 'Old step', 'description' => 'old']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->put($this->host($tenant)."/admin/tours/{$tour->id}", [
            'name' => 'Updated Tour',
            'description' => 'New description.',
            'base_price' => '200000.00',
            'duration_hours' => 5,
            'difficulty' => 'easy',
            'default_capacity' => 8,
            'itinerary' => [
                ['step_number' => 1, 'title' => 'New step', 'description' => 'fresh'],
            ],
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tours', ['id' => $tour->id, 'name' => 'Updated Tour']);
        $this->assertSame(1, $tour->itineraries()->count());
        $this->assertSame('New step', $tour->itineraries()->first()?->title);
    }

    public function test_update_rejects_an_invalid_difficulty(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->put($this->host($tenant)."/admin/tours/{$tour->id}", ['difficulty' => 'lunar'])
            ->assertSessionHasErrors('difficulty');
    }

    public function test_a_sales_member_cannot_update_a_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)
            ->put($this->host($tenant)."/admin/tours/{$tour->id}", ['name' => 'Otra cosa'])
            ->assertForbidden();
    }

    public function test_updating_a_tour_from_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create(['name' => 'Tour B']);

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->put($this->host($tenantA)."/admin/tours/{$tourB->id}", ['name' => 'Hackeado'])
            ->assertNotFound();

        $this->assertDatabaseHas('tours', ['id' => $tourB->id, 'name' => 'Tour B']);
    }
}
