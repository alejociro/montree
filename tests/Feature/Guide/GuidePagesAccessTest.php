<?php

declare(strict_types=1);

namespace Tests\Feature\Guide;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las dos páginas Inertia de la zona del guía (Fase 4).
 *
 * WHY: sus guardas son de **pertenencia**, no de permiso, y viven en el
 * controlador de páginas — no en un `can:` de la ruta ni en una Policy. Sin
 * test, un guía llegaría a una pantalla que responde 200 y después se queda
 * vacía porque la API que la llena le devuelve 403. La regla de oro del menú
 * (F018) es la contraria: si el item aparece, la ruta responde 200.
 */
final class GuidePagesAccessTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://demo.montree.test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_guide_opens_the_manifest_page_of_their_own_departure(): void
    {
        $guide = $this->memberFor(UserRole::Guide);
        $tourDate = $this->tourDateFor($guide);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->get(self::HOST."/guide/tour-dates/{$tourDate->id}/passengers")
            ->assertOk();
    }

    public function test_guide_cannot_open_the_manifest_page_of_someone_elses_departure(): void
    {
        $guide = $this->memberFor(UserRole::Guide);
        $other = $this->memberFor(UserRole::Guide);
        $tourDate = $this->tourDateFor($other);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->get(self::HOST."/guide/tour-dates/{$tourDate->id}/passengers")
            ->assertForbidden();
    }

    public function test_guide_opens_the_detail_of_a_tour_where_they_have_a_departure(): void
    {
        $guide = $this->memberFor(UserRole::Guide);
        $tourDate = $this->tourDateFor($guide);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->get(self::HOST."/guide/tours/{$tourDate->tour_id}")
            ->assertOk();
    }

    /**
     * Mismo tenant, ninguna salida suya: 403. El alcance del guía se filtra por
     * pertenencia, no por catálogo de permisos (D1).
     */
    public function test_guide_cannot_open_the_detail_of_a_tour_without_any_departure_of_theirs(): void
    {
        $guide = $this->memberFor(UserRole::Guide);
        $other = $this->memberFor(UserRole::Guide);
        $tourDate = $this->tourDateFor($other);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->get(self::HOST."/guide/tours/{$tourDate->tour_id}")
            ->assertForbidden();
    }

    private function tourDateFor(User $guide): TourDate
    {
        $this->tenant->makeCurrent();

        return TourDate::factory()->for(Tour::factory()->create())->create(['guide_id' => $guide->id]);
    }

    private function memberFor(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
