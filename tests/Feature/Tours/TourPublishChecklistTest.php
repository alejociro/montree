<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\TourStatus;
use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourImage;
use App\Models\TourStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * D7 — el checklist «Para publicar» sale del servidor y es la misma lista que
 * decide la activación. Lo que la pantalla marca como obligatorio es exactamente
 * lo que `ChangeTourStatusAction` rechaza; lo recomendado no bloquea a nadie.
 */
final class TourPublishChecklistTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_tour_response_carries_the_checklist(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour($guide);
        TourImage::factory()->for($tour)->cover()->create();

        $response = $this->actingAs($admin)->getJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}",
        );

        $response->assertOk();

        $checklist = collect($response->json('data.publish_checklist'))->keyBy('id');

        $this->assertSame(
            ['general', 'summary', 'pricing', 'image', 'guide', 'stops'],
            $checklist->keys()->all(),
        );
        $this->assertTrue($checklist['general']['done']);
        $this->assertTrue($checklist['image']['done']);
        $this->assertTrue($checklist['guide']['done']);
        // Las paradas se recomiendan, no bloquean (D7).
        $this->assertFalse($checklist['stops']['blocking']);
        $this->assertFalse($checklist['stops']['done']);
    }

    public function test_the_checklist_marks_what_is_missing(): void
    {
        [$tenant, $admin] = $this->scenario();
        $tour = Tour::factory()->create([
            'status' => TourStatus::Draft,
            'short_description' => null,
            'default_guide_id' => null,
        ]);

        $response = $this->actingAs($admin)->getJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}",
        );

        $checklist = collect($response->json('data.publish_checklist'))->keyBy('id');

        $this->assertFalse($checklist['summary']['done']);
        $this->assertTrue($checklist['summary']['blocking']);
        $this->assertFalse($checklist['image']['done']);
        $this->assertFalse($checklist['guide']['done']);
    }

    public function test_activating_without_summary_fails(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour($guide, ['short_description' => null]);
        TourImage::factory()->for($tour)->cover()->create();

        $response = $this->actingAs($admin)->patchJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'TOUR_NEEDS_SUMMARY_TO_ACTIVATE');
        $this->assertSame(TourStatus::Draft, $tour->fresh()?->status);
    }

    public function test_missing_stops_do_not_block_activation(): void
    {
        // Endurecerlas dejaría en borrador a tours que hoy están activos la
        // próxima vez que alguien los toque (D7).
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour($guide);
        TourImage::factory()->for($tour)->cover()->create();

        $response = $this->actingAs($admin)->patchJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertOk();
        $this->assertSame(TourStatus::Active, $tour->fresh()?->status);
    }

    public function test_the_stops_requirement_is_met_with_pickup_and_drop(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour($guide);
        $this->stop($tour, TourStopKind::Pickup, 1);
        $this->stop($tour, TourStopKind::Drop, 2);

        $response = $this->actingAs($admin)->getJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}",
        );

        $checklist = collect($response->json('data.publish_checklist'))->keyBy('id');

        $this->assertTrue($checklist['stops']['done']);
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

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function tour(User $guide, array $attrs = []): Tour
    {
        return Tour::factory()->create(array_merge([
            'status' => TourStatus::Draft,
            'default_guide_id' => $guide->id,
            'short_description' => 'Un día en el valle.',
        ], $attrs));
    }

    private function stop(Tour $tour, TourStopKind $kind, int $position): TourStop
    {
        return TourStop::factory()->for($tour)->create([
            'kind' => $kind,
            'position' => $position,
            'code' => $kind === TourStopKind::Pickup ? 'A' : 'B',
        ]);
    }
}
