<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Cierre del bug B2: `tenant_guide.only` dejaba entrar a `guide/*` a cualquier admin u
 * operador de la agencia. Desde F018 la puerta es `can:guide.*`, que solo tienen el guía
 * y el admin (que se lleva el catálogo completo).
 */
final class GuideRouteAccessTest extends TestCase
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

    public function test_guide_sees_their_own_schedule(): void
    {
        $guide = $this->memberFor(UserRole::Guide);
        $this->tourDateFor($guide);
        Tenant::forgetCurrent();

        $response = $this->actingAs($guide)->getJson(self::HOST.'/api/v1/guide/schedule');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_operator_cannot_reach_the_guide_schedule(): void
    {
        $operator = $this->memberFor(UserRole::Operator);

        $response = $this->actingAs($operator)->getJson(self::HOST.'/api/v1/guide/schedule');

        $response->assertForbidden();
        $response->assertJsonPath('error_code', 'INSUFFICIENT_PERMISSION');
    }

    public function test_sales_cannot_reach_the_guide_schedule(): void
    {
        $sales = $this->memberFor(UserRole::Sales);

        $this->actingAs($sales)
            ->getJson(self::HOST.'/api/v1/guide/schedule')
            ->assertForbidden();
    }

    public function test_guide_sees_the_travelers_of_their_own_departure(): void
    {
        $guide = $this->memberFor(UserRole::Guide);
        $tourDate = $this->tourDateFor($guide);
        Tenant::forgetCurrent();

        $this->actingAs($guide)
            ->getJson(self::HOST."/api/v1/guide/tour-dates/{$tourDate->id}/travelers")
            ->assertOk();
    }

    public function test_operator_cannot_read_the_travelers_of_a_departure(): void
    {
        $guide = $this->memberFor(UserRole::Guide);
        $operator = $this->memberFor(UserRole::Operator);
        $tourDate = $this->tourDateFor($guide);
        Tenant::forgetCurrent();

        $this->actingAs($operator)
            ->getJson(self::HOST."/api/v1/guide/tour-dates/{$tourDate->id}/travelers")
            ->assertForbidden();
    }

    public function test_guide_cannot_reach_the_admin_panel(): void
    {
        $guide = $this->memberFor(UserRole::Guide);

        $this->actingAs($guide)
            ->getJson(self::HOST.'/api/v1/admin/dashboard')
            ->assertForbidden();

        Auth::forgetGuards();

        $this->actingAs($guide)
            ->getJson(self::HOST.'/api/v1/admin/tours')
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
