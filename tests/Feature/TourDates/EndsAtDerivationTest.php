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
 * D9 — `ends_at` se deriva de `tours.duration_hours` y deja de aceptarse del
 * cliente. Un `ends_at` que miente es peor que uno vacío: la regla de
 * disponibilidad se lo cree.
 */
final class EndsAtDerivationTest extends TestCase
{
    use DepartureScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_ends_at_sent_by_the_client_is_rejected(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 51]);

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/dates",
            [
                'starts_at' => '2026-09-12 07:00:00',
                'ends_at' => '2026-09-12 11:00:00',
                'capacity' => 10,
                'guide_id' => $guide->id,
            ],
        );

        $response->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_a_51_hour_tour_ends_two_days_later(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 51]);

        $response = $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/tours/{$tour->id}/dates",
            ['starts_at' => '2026-09-12 07:00:00', 'capacity' => 10, 'guide_id' => $guide->id],
        );

        $response->assertCreated();
        $this->assertSame(
            '2026-09-14 10:00:00',
            TourDate::query()->findOrFail($response->json('data.id'))->ends_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_moving_the_start_rederives_the_end(): void
    {
        [$tenant, $admin, $guide] = $this->scenario();
        $tour = Tour::factory()->create(['duration_hours' => 51]);
        $departure = TourDate::factory()->for($tour)->create([
            'guide_id' => $guide->id,
            'starts_at' => '2026-09-12 07:00:00',
        ]);

        $response = $this->actingAs($admin)->putJson(
            $this->host($tenant)."/api/v1/admin/tour-dates/{$departure->id}",
            ['starts_at' => '2026-09-20 07:00:00'],
        );

        $response->assertOk();
        $this->assertSame('2026-09-22 10:00:00', $departure->fresh()?->ends_at->format('Y-m-d H:i:s'));
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
