<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class ChangeTourStatusTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_activates_a_draft_tour_when_requirements_are_met(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create([
            'status' => TourStatus::Draft,
            'default_guide_id' => $this->guideFor($tenant)->id,
        ]);
        TourImage::factory()->for($tour)->cover()->create();
        TourDate::factory()->for($tour)->create([
            'status' => TourDateStatus::Open,
            'starts_at' => now()->addDays(7),
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertSessionHas('success');
        $this->assertSame(TourStatus::Active, $tour->fresh()?->status);
    }

    public function test_activating_without_an_image_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Draft]);
        TourDate::factory()->for($tour)->create([
            'status' => TourDateStatus::Open,
            'starts_at' => now()->addDays(7),
        ]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertSessionHasErrors(['status' => __('Tour needs at least one image before activating.')]);
        $this->assertSame(TourStatus::Draft, $tour->fresh()?->status);
    }

    /**
     * D7/D9: toda salida lleva guía, así que publicar un tour sin guía por
     * defecto deja un tour que no puede programar nada.
     */
    public function test_activating_without_a_default_guide_fails(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Draft, 'default_guide_id' => null]);
        TourImage::factory()->for($tour)->cover()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertSessionHasErrors(['status' => __('Tour needs a default guide before activating.')]);
        $this->assertSame(TourStatus::Draft, $tour->fresh()?->status);
    }

    public function test_activating_without_a_future_date_succeeds(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create([
            'status' => TourStatus::Draft,
            'default_guide_id' => $this->guideFor($tenant)->id,
        ]);
        TourImage::factory()->for($tour)->cover()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertSessionHas('success');
        $this->assertSame(TourStatus::Active, $tour->fresh()?->status);
    }

    /**
     * Contrato nuevo del rechazo: ya no hay `error_code` en un cuerpo JSON. El
     * motivo vuelve a la misma pantalla como error de la clave `status`, para
     * que el botón que lo disparó pueda mostrarlo sin perder la página.
     */
    public function test_a_rejected_transition_returns_to_the_same_page_with_the_reason(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Draft]);
        $admin = $this->memberFor($tenant, UserRole::Admin);
        $origin = $this->host($tenant).'/admin/tours/'.$tour->id;

        $response = $this->actingAs($admin)->from($origin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertRedirect($origin);
        $response->assertSessionHasErrors(['status' => __('Tour needs at least one image before activating.')]);
    }

    public function test_an_invalid_transition_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Archived]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertSessionHasErrors('status');
        $this->assertSame(TourStatus::Archived, $tour->fresh()?->status);
    }

    public function test_an_active_tour_can_be_paused(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'paused'],
        );

        $response->assertSessionHas('success');
        $this->assertSame(TourStatus::Paused, $tour->fresh()?->status);
    }

    public function test_an_operator_cannot_archive_a_tour(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $this->actingAs($operator)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'archived'],
        )->assertForbidden();
    }

    public function test_changing_the_status_of_another_tenants_tour_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create(['status' => TourStatus::Draft]);

        $tenantA->makeCurrent();
        $this->actingAs($adminA)->patch(
            $this->host($tenantA)."/admin/tours/{$tourB->id}/status",
            ['status' => 'paused'],
        )->assertNotFound();
    }
}
