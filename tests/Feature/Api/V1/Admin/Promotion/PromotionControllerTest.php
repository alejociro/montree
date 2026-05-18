<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Promotion;

use App\Enums\PromotionType;
use App\Enums\UserRole;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_can_list_promotions_for_current_tenant(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Promotion::factory()->count(3)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/promotions');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [['id', 'code', 'type', 'value', 'is_active', 'is_expired', 'is_exhausted']],
            'links',
            'meta',
        ]);
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Promotion::factory()->create(['code' => 'VERANO2026', 'is_active' => true]);
        Promotion::factory()->create(['code' => 'OTOÑO2026', 'is_active' => false]);
        Promotion::factory()->expired()->create(['code' => 'INVIERNO2025']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $byInactive = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/promotions?status=inactive');
        $byInactive->assertOk();
        $byInactive->assertJsonCount(1, 'data');
        $byInactive->assertJsonPath('data.0.code', 'OTOÑO2026');

        $bySearch = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/promotions?search=VERANO');
        $bySearch->assertJsonCount(1, 'data');
        $bySearch->assertJsonPath('data.0.code', 'VERANO2026');

        $byExpired = $this->actingAs($admin)
            ->getJson('http://demo.montree.test/api/v1/admin/promotions?status=expired');
        $byExpired->assertJsonCount(1, 'data');
        $byExpired->assertJsonPath('data.0.code', 'INVIERNO2025');
    }

    public function test_admin_can_create_promotion_with_uppercased_code(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->postJson('http://demo.montree.test/api/v1/admin/promotions', [
                'code' => 'verano2026',
                'name' => 'Verano 2026',
                'type' => 'percentage',
                'value' => '10',
                'max_uses' => 100,
                'starts_at' => '2026-05-01T00:00:00Z',
                'ends_at' => '2026-06-30T23:59:59Z',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'VERANO2026');
        $response->assertJsonPath('data.type', 'percentage');
        $this->assertDatabaseHas('promotions', [
            'code' => 'VERANO2026',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_store_returns_409_when_code_is_duplicated_in_tenant(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        Promotion::factory()->create(['code' => 'VERANO2026']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->postJson('http://demo.montree.test/api/v1/admin/promotions', [
                'code' => 'VERANO2026',
                'name' => 'Otra',
                'type' => 'percentage',
                'value' => '5',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'PROMOTION_CODE_TAKEN');
    }

    public function test_store_validates_required_fields(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->postJson('http://demo.montree.test/api/v1/admin/promotions', [
                'code' => 'invalid code!',
                'type' => 'unknown',
                'value' => 0,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'name', 'type', 'value']);
    }

    public function test_store_rejects_percentage_value_above_100(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->postJson('http://demo.montree.test/api/v1/admin/promotions', [
                'code' => 'OVER100',
                'name' => 'Bad',
                'type' => 'percentage',
                'value' => '150',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['value']);
    }

    public function test_update_allows_changing_code_when_no_uses(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $promotion = Promotion::factory()->create(['code' => 'OLD', 'uses_count' => 0]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->putJson("http://demo.montree.test/api/v1/admin/promotions/{$promotion->id}", [
                'code' => 'new-code',
                'value' => '20',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'NEW-CODE');
    }

    public function test_update_returns_422_when_code_locked_due_to_uses(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $promotion = Promotion::factory()->create(['code' => 'USED', 'uses_count' => 3]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->putJson("http://demo.montree.test/api/v1/admin/promotions/{$promotion->id}", [
                'code' => 'DIFFERENT',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_CODE_LOCKED');
    }

    public function test_destroy_hard_deletes_unused_promotion(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $promotion = Promotion::factory()->create(['uses_count' => 0]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->deleteJson("http://demo.montree.test/api/v1/admin/promotions/{$promotion->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }

    public function test_destroy_deactivates_used_promotion(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $promotion = Promotion::factory()->create(['uses_count' => 5, 'is_active' => true]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->deleteJson("http://demo.montree.test/api/v1/admin/promotions/{$promotion->id}");

        $response->assertNoContent();
        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'is_active' => false,
        ]);
    }

    public function test_customer_cannot_create_promotion(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $customer = $this->memberFor($tenant, UserRole::Customer);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/admin/promotions', [
                'code' => 'NOPE',
                'name' => 'Test',
                'type' => 'percentage',
                'value' => '5',
            ]);

        $response->assertStatus(403);
    }

    public function test_tenant_isolation_prevents_seeing_other_tenant_promotions(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        Promotion::factory()->create(['code' => 'A-CODE']);
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $promotionB = Promotion::factory()->create(['code' => 'B-CODE']);

        $tenantA->makeCurrent();
        setPermissionsTeamId($tenantA->id);
        $response = $this->actingAs($adminA)
            ->getJson('http://alpha.montree.test/api/v1/admin/promotions');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.code', 'A-CODE');

        $detail = $this->actingAs($adminA)
            ->getJson("http://alpha.montree.test/api/v1/admin/promotions/{$promotionB->id}");
        $detail->assertStatus(404);
    }

    public function test_tenant_isolation_allows_same_code_across_tenants(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        Promotion::factory()->create(['code' => 'SHARED']);

        $tenantB->makeCurrent();
        $adminB = $this->memberFor($tenantB, UserRole::Admin);

        $response = $this->actingAs($adminB)
            ->postJson('http://bravo.montree.test/api/v1/admin/promotions', [
                'code' => 'SHARED',
                'name' => 'B promo',
                'type' => 'fixed',
                'value' => '5000',
            ]);

        $response->assertCreated();
    }

    public function test_store_accepts_applicable_tours_scoped_to_tenant(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)
            ->postJson('http://demo.montree.test/api/v1/admin/promotions', [
                'code' => 'TOURONLY',
                'name' => 'Tour specific',
                'type' => PromotionType::Fixed->value,
                'value' => '5000',
                'applicable_tours' => [$tour->id],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.applicable_tours', [$tour->id]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
