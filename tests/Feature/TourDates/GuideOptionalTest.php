<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

use App\Enums\TourDateStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * T8 — revierte D7: `tour_dates.guide_id` vuelve a admitir `null`. Una salida
 * puede crearse, operar y reservarse sin guía, y asignárselo después por
 * cualquiera de los tres caminos que ya validan pertenencia y disponibilidad.
 */
final class GuideOptionalTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_editing_a_departure_can_remove_its_guide(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create();
        $departure = TourDate::factory()->for($tour)->create([
            'guide_id' => $guide->id,
            'starts_at' => now()->addDays(10),
        ]);

        $response = $this->actingAs($admin)->put(
            $this->host($tenant)."/admin/tour-dates/{$departure->id}",
            ['guide_id' => null],
        );

        $response->assertSessionHas('success');
        $this->assertNull($departure->fresh()?->guide_id);
    }

    public function test_a_guide_can_be_assigned_later_to_a_departure_that_had_none(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create();
        $departure = TourDate::factory()->for($tour)->withoutGuide()->create([
            'starts_at' => now()->addDays(10),
        ]);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tour-dates/{$departure->id}/guide",
            ['guide_id' => $guide->id],
        );

        $response->assertSessionHas('success');
        $this->assertSame($guide->id, $departure->fresh()?->guide_id);
    }

    public function test_the_board_kpi_counts_departures_without_a_guide(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->withoutGuide()->create([
            'starts_at' => now()->addDays(5),
            'status' => TourDateStatus::Open,
        ]);
        TourDate::factory()->for($tour)->create([
            'guide_id' => $guide->id,
            'starts_at' => now()->addDays(6),
            'status' => TourDateStatus::Open,
        ]);

        $response = $this->actingAs($admin)->get($this->host($tenant).'/admin/departures');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.without_guide', 1));
    }

    public function test_a_departure_without_a_guide_does_not_appear_in_any_guides_schedule(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create();
        TourDate::factory()->for($tour)->withoutGuide()->create(['starts_at' => now()->addDays(5)]);
        $assigned = TourDate::factory()->for($tour)->create([
            'guide_id' => $guide->id,
            'starts_at' => now()->addDays(6),
        ]);

        $response = $this->actingAs($guide)->getJson(
            $this->host($tenant).'/api/v1/guide/schedule',
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$assigned->id], $ids);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: User}
     */
    private function scenario(): array
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        return [$tenant, $this->memberFor($tenant, UserRole::Admin), $this->guideFor($tenant)];
    }
}
