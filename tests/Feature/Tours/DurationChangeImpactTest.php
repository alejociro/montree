<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * D9 — cambiar `duration_hours` alarga retroactivamente el `ends_at` derivado de
 * todas las salidas futuras del tour, y eso puede cruzar dos que hoy no se
 * tocan. Se avisa antes de guardar, con las salidas nombradas.
 */
final class DurationChangeImpactTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_a_longer_duration_that_creates_an_overlap_is_reported_before_saving(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 6, 'name' => 'Valle de Cocora']);
        $other = Tour::factory()->create(['duration_hours' => 6, 'name' => 'Salento']);
        TourDate::factory()->for($tour)->create(['guide_id' => $guide->id, 'starts_at' => '2026-09-12 07:00:00']);
        TourDate::factory()->for($other)->create(['guide_id' => $guide->id, 'starts_at' => '2026-09-15 07:00:00']);

        $response = $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}",
            ['duration_hours' => 96],
        );

        $response->assertStatus(422)->assertJsonValidationErrors('duration_hours');
        $this->assertStringContainsString('Salento', $response->json('errors.duration_hours.0'));
        $this->assertSame(6, $tour->fresh()?->duration_hours);
    }

    public function test_a_longer_duration_without_overlap_goes_through(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 6, 'name' => 'Valle de Cocora']);
        TourDate::factory()->for($tour)->create(['guide_id' => $guide->id, 'starts_at' => '2026-09-12 07:00:00']);
        TourDate::factory()->for($tour)->create([
            'guide_id' => $this->guideFor($tenant)->id,
            'starts_at' => '2026-09-15 07:00:00',
        ]);

        $response = $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}",
            ['duration_hours' => 30],
        );

        $response->assertOk();
        $this->assertSame(30, $tour->fresh()?->duration_hours);
    }

    public function test_a_past_departure_does_not_block_a_duration_change(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 6, 'name' => 'Valle de Cocora']);
        $other = Tour::factory()->create(['duration_hours' => 6, 'name' => 'Salento']);
        TourDate::factory()->for($tour)->create(['guide_id' => $guide->id, 'starts_at' => '2026-08-01 07:00:00']);
        TourDate::factory()->for($other)->create(['guide_id' => $guide->id, 'starts_at' => '2026-08-04 07:00:00']);

        $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}",
            ['duration_hours' => 96],
        )->assertOk();
    }

    public function test_the_factory_cannot_produce_an_overlap(): void
    {
        [$tenant] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 72]);

        $guides = TourDate::factory()->count(15)->for($tour)->create()->pluck('guide_id');

        $this->assertSame($guides->count(), $guides->unique()->count());
    }

    /**
     * @return array{0: Tenant, 1: User, 2: User}
     */
    private function scenario(): array
    {
        Carbon::setTestNow('2026-09-01 08:00:00');

        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        return [$tenant, $this->memberFor($tenant, UserRole::Admin), $this->guideFor($tenant)];
    }
}
