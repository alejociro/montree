<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class DeleteTourTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_destroy_soft_deletes_a_tour_without_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/tours/{$tour->id}");

        $response->assertRedirect($this->host($tenant).'/admin/tours');
        $response->assertSessionHas('success');
        $this->assertSoftDeleted('tours', ['id' => $tour->id]);
    }

    /**
     * El 409 JSON se volvió un error de formulario en la clave `tour`: la
     * pantalla que tiene el botón es la que tiene que explicar el rechazo.
     */
    public function test_destroy_is_blocked_when_the_tour_has_active_bookings(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $date = TourDate::factory()->for($tour)->create();
        $user = User::factory()->create();
        Booking::factory()->for($user)->for($tour)->for($date, 'tourDate')->create([
            'status' => BookingStatus::Confirmed,
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->delete($this->host($tenant)."/admin/tours/{$tour->id}");

        $response->assertSessionHasErrors('tour');
        $this->assertNull($tour->fresh()?->deleted_at);
    }

    public function test_an_operator_cannot_delete_a_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $this->actingAs($operator)
            ->delete($this->host($tenant)."/admin/tours/{$tour->id}")
            ->assertForbidden();
    }

    public function test_deleting_a_tour_from_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)
            ->delete($this->host($tenantA)."/admin/tours/{$tourB->id}")
            ->assertNotFound();

        $this->assertNull($tourB->fresh()?->deleted_at);
    }
}
