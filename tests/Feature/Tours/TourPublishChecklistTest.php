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
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

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

        $checklist = $this->checklistOf($tenant, $admin, $tour);

        $this->assertSame(
            ['general', 'summary', 'pricing', 'image', 'guide', 'stops'],
            $checklist->keys()->all(),
        );
        $this->assertTrue($checklist['general']['done']);
        $this->assertTrue($checklist['image']['done']);
        $this->assertTrue($checklist['guide']['done']);
        // El guía por defecto y las paradas se recomiendan, no bloquean
        // (T8/D7).
        $this->assertFalse($checklist['guide']['blocking']);
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

        $checklist = $this->checklistOf($tenant, $admin, $tour);

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

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertSessionHasErrors(['status' => __('Tour needs a short summary before activating.')]);
        $this->assertSame(TourStatus::Draft, $tour->fresh()?->status);
    }

    public function test_missing_stops_do_not_block_activation(): void
    {
        // Endurecerlas dejaría en borrador a tours que hoy están activos la
        // próxima vez que alguien los toque (D7).
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour($guide);
        TourImage::factory()->for($tour)->cover()->create();

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/status",
            ['status' => 'active'],
        );

        $response->assertSessionHas('success');
        $this->assertSame(TourStatus::Active, $tour->fresh()?->status);
    }

    public function test_the_stops_requirement_is_met_with_pickup_and_drop(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour($guide);
        $this->stop($tour, TourStopKind::Pickup, 1);
        $this->stop($tour, TourStopKind::Drop, 2);

        $checklist = $this->checklistOf($tenant, $admin, $tour);

        $this->assertTrue($checklist['stops']['done']);
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function checklistOf(Tenant $tenant, User $admin, Tour $tour): Collection
    {
        $checklist = null;

        $this->actingAs($admin)
            ->get($this->host($tenant).'/admin/tours/'.$tour->id)
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$checklist): void {
                $checklist = $page->toArray()['props']['tour']['publish_checklist'];
            });

        return collect($checklist)->keyBy('id');
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
