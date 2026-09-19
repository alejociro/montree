<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class ReorderCategoriesTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_submitted_order_becomes_the_display_order(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $first = Category::factory()->create(['display_order' => 1]);
        $second = Category::factory()->create(['display_order' => 2]);
        $third = Category::factory()->create(['display_order' => 3]);

        $response = $this->actingAs($admin)->patch($this->host($this->tenant).'/admin/categories/reorder', [
            'ids' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(1, $third->refresh()->display_order);
        $this->assertSame(2, $first->refresh()->display_order);
        $this->assertSame(3, $second->refresh()->display_order);
    }

    public function test_an_id_of_another_tenant_is_rejected(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $own = Category::factory()->create(['display_order' => 1]);

        $other = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);
        $other->makeCurrent();
        $foreign = Category::factory()->create(['display_order' => 7]);
        $this->tenant->makeCurrent();

        $this->actingAs($admin)->patchJson($this->host($this->tenant).'/admin/categories/reorder', [
            'ids' => [$foreign->id, $own->id],
        ])->assertStatus(422)->assertJsonValidationErrors('ids.0');

        $this->assertSame(7, $foreign->refresh()->display_order);
    }

    public function test_an_empty_list_is_rejected(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $this->actingAs($admin)->patchJson($this->host($this->tenant).'/admin/categories/reorder', [
            'ids' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('ids');
    }

    public function test_without_the_manage_permission_reordering_is_forbidden(): void
    {
        $sales = $this->memberFor($this->tenant, UserRole::Sales);
        $category = Category::factory()->create(['display_order' => 1]);

        $this->actingAs($sales)->patchJson($this->host($this->tenant).'/admin/categories/reorder', [
            'ids' => [$category->id],
        ])->assertForbidden();

        $this->assertSame(1, $category->refresh()->display_order);
    }
}
