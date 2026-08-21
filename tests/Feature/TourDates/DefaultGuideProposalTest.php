<?php

declare(strict_types=1);

namespace Tests\Feature\TourDates;

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
 * Regla 3 del handoff: `tours.default_guide_id` es una preferencia que se
 * **propone** al crear una salida. Propone, no impone: la salida sigue pasando
 * por la validación de pertenencia, rol y disponibilidad (D9).
 */
final class DefaultGuideProposalTest extends TestCase
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

    public function test_creating_a_departure_without_guide_takes_the_tour_default(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = $this->tour($guide);

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => self::START, 'capacity' => 10],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.guide.id', $guide->id);
    }

    public function test_an_explicit_guide_wins_over_the_default(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $chosen = $this->guideFor($tenant);
        $tour = $this->tour($guide);

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => self::START, 'capacity' => 10, 'guide_id' => $chosen->id],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.guide.id', $chosen->id);
    }

    public function test_the_proposed_guide_still_has_to_be_available(): void
    {
        // La preferencia no salta la agenda: si el guía por defecto ya sale ese
        // día, la salida se rechaza igual que si lo hubieran elegido a mano.
        [$tenant, $admin, $guide] = $this->scenario();
        $busy = Tour::factory()->create(['duration_hours' => 8, 'name' => 'Valle de Cocora']);
        TourDate::factory()->for($busy)->create([
            'guide_id' => $guide->id,
            'starts_at' => self::START,
        ]);
        $tour = $this->tour($guide);

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => self::START, 'capacity' => 10],
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('Valle de Cocora', (string) $response->json('errors.guide_id.0'));
    }

    public function test_a_tour_without_default_guide_still_demands_one(): void
    {
        [$tenant, $admin] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 8, 'default_guide_id' => null]);

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => self::START, 'capacity' => 10],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('guide_id');
    }

    public function test_editing_a_departure_never_proposes_the_default(): void
    {
        // Editar sin mandar guía deja el que ya tenía; no lo devuelve al del
        // tour a espaldas de quien edita.
        [$tenant, $admin, $guide] = $this->scenario();
        $assigned = $this->guideFor($tenant);
        $tour = $this->tour($guide);
        $departure = TourDate::factory()->for($tour)->create([
            'guide_id' => $assigned->id,
            'starts_at' => self::START,
        ]);

        $response = $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$departure->id}",
            ['capacity' => 12],
        );

        $response->assertOk();
        $this->assertSame($assigned->id, $departure->fresh()?->guide_id);
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

    private function tour(User $defaultGuide): Tour
    {
        return Tour::factory()->create([
            'duration_hours' => 8,
            'name' => 'Salento',
            'default_guide_id' => $defaultGuide->id,
        ]);
    }
}
