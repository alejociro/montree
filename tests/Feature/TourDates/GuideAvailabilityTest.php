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
use Illuminate\Support\Carbon;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * D9 — un guía, una salida a la vez, por días calendario completos, en los tres
 * caminos que asignan guía: crear salida, editar salida y el `PATCH` de
 * asignación. Si la regla se saltara por uno solo, no existiría.
 */
final class GuideAvailabilityTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    private const START = '2026-09-12 07:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_store_rejects_a_guide_already_busy_that_day(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $busyTour = $this->tour(8, 'Valle de Cocora');
        $this->departure($busyTour, $guide, self::START);
        $other = $this->tour(8, 'Salento');

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$other->id}/dates",
            ['starts_at' => self::START, 'capacity' => 10, 'guide_id' => $guide->id],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Valle de Cocora', $response->json('errors.guide_id.0'));
    }

    public function test_a_three_day_tour_blocks_its_three_calendar_days(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        // 50 h desde el 12 a las 07:00 termina el 14 a las 09:00: ocupa 12, 13 y 14.
        $this->departure($this->tour(50, 'Valle de Cocora'), $guide, self::START);
        $other = $this->tour(6, 'Salento');

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$other->id}/dates",
            ['starts_at' => '2026-09-14 06:00:00', 'capacity' => 10, 'guide_id' => $guide->id],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('12–14 sep', $response->json('errors.guide_id.0'));
    }

    public function test_a_cancelled_departure_frees_its_days(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $this->departure($this->tour(50, 'Valle de Cocora'), $guide, self::START, TourDateStatus::Cancelled);
        $other = $this->tour(6, 'Salento');

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$other->id}/dates",
            ['starts_at' => '2026-09-13 06:00:00', 'capacity' => 10, 'guide_id' => $guide->id],
        );

        $response->assertCreated();
    }

    public function test_editing_the_own_departure_is_not_a_false_positive(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $departure = $this->departure($this->tour(8, 'Valle de Cocora'), $guide, self::START);

        $response = $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$departure->id}",
            ['capacity' => 14, 'guide_id' => $guide->id],
        );

        $response->assertOk()->assertJsonPath('data.capacity', 14);
    }

    public function test_moving_the_start_onto_a_busy_day_is_rejected_even_without_changing_the_guide(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $this->departure($this->tour(8, 'Valle de Cocora'), $guide, self::START);
        $moving = $this->departure($this->tour(8, 'Salento'), $guide, '2026-09-20 07:00:00');

        $response = $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$moving->id}",
            ['starts_at' => self::START],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Valle de Cocora', $response->json('errors.guide_id.0'));
    }

    public function test_the_patch_path_runs_the_same_rule(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $this->departure($this->tour(8, 'Valle de Cocora'), $guide, self::START);
        $target = $this->departure($this->tour(8, 'Salento'), $this->guideFor($tenant), self::START);

        $response = $this->actingAs($admin)->patchJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$target->id}/guide",
            ['guide_id' => $guide->id],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Valle de Cocora', $response->json('errors.guide_id.0'));
        $this->assertNotSame($guide->id, $target->fresh()?->guide_id);
    }

    public function test_the_patch_path_rejects_a_user_that_is_not_a_guide_of_the_tenant(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $departure = $this->departure($this->tour(8, 'Salento'), $guide, self::START);
        $stranger = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$departure->id}/guide",
            ['guide_id' => $stranger->id],
        );

        $response->assertStatus(422)->assertJsonValidationErrors('guide_id');
    }

    public function test_guide_id_is_required_in_the_three_paths(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour(8, 'Salento');
        $departure = $this->departure($tour, $guide, self::START);

        $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => '2026-10-01 07:00:00', 'capacity' => 10],
        )->assertStatus(422)->assertJsonValidationErrors('guide_id');

        $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$departure->id}",
            ['guide_id' => null],
        )->assertStatus(422)->assertJsonValidationErrors('guide_id');

        $this->actingAs($admin)->patchJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$departure->id}/guide",
            [],
        )->assertStatus(422)->assertJsonValidationErrors('guide_id');
    }

    public function test_the_availability_endpoint_lists_busy_blocks_and_honours_the_exclusion(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $departure = $this->departure($this->tour(50, 'Valle de Cocora'), $guide, self::START);

        $response = $this->actingAs($admin)->getJson(
            $this->host($tenant).'/api/v1/admin/guides/availability?from=2026-09-01&to=2026-09-30',
        );

        $response->assertOk();
        $entry = collect($response->json('data'))->firstWhere('id', $guide->id);
        $this->assertSame('2026-09-12', $entry['busy'][0]['from']);
        $this->assertSame('2026-09-14', $entry['busy'][0]['to']);
        $this->assertSame('Valle de Cocora', $entry['busy'][0]['tour_name']);

        $excluded = $this->actingAs($admin)->getJson(
            $this->host($tenant)."/api/v1/admin/guides/availability?from=2026-09-01&to=2026-09-30&exclude_tour_date_id={$departure->id}",
        );

        $excluded->assertOk();
        $this->assertSame([], collect($excluded->json('data'))->firstWhere('id', $guide->id)['busy']);
    }

    public function test_the_availability_endpoint_rejects_a_range_longer_than_180_days(): void
    {
        [$tenant, $admin] = $this->scenario();

        $this->actingAs($admin)->getJson(
            $this->host($tenant).'/api/v1/admin/guides/availability?from=2026-01-01&to=2026-12-31',
        )->assertStatus(422)->assertJsonValidationErrors('to');
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

    private function tour(int $durationHours, string $name): Tour
    {
        return Tour::factory()->create(['duration_hours' => $durationHours, 'name' => $name]);
    }

    private function departure(Tour $tour, User $guide, string $startsAt, ?TourDateStatus $status = null): TourDate
    {
        return TourDate::factory()->for($tour)->create([
            'guide_id' => $guide->id,
            'starts_at' => $startsAt,
            'status' => $status ?? TourDateStatus::Open,
        ]);
    }
}
