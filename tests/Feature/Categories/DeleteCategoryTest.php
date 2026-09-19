<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class DeleteCategoryTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_deleting_a_category_without_tours_succeeds(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $category = Category::factory()->create(['name' => 'Aventura']);

        $response = $this->actingAs($admin)
            ->delete($this->host($this->tenant)."/admin/categories/{$category->id}");

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_deleting_a_category_with_tours_is_blocked_and_names_them(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $category = Category::factory()->create(['name' => 'Aventura']);
        Tour::factory()->create(['category_id' => $category->id, 'name' => 'Cañón del Río Claro']);
        Tour::factory()->create(['category_id' => $category->id, 'name' => 'Rafting Samaná']);

        $response = $this->actingAs($admin)
            ->delete($this->host($this->tenant)."/admin/categories/{$category->id}");

        $response->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $message = (string) session('errors')->first('category');

        $this->assertStringContainsString('2 productos', $message);
        $this->assertStringContainsString('Cañón del Río Claro', $message);
    }

    public function test_deleting_a_category_removes_its_stored_image(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $category = Category::factory()->create(['name' => 'Aventura']);
        Storage::disk('public')->put('tenants/1/categories/aventura.png', 'x');
        $category->forceFill(['image_path' => 'tenants/1/categories/aventura.png'])->save();

        $this->actingAs($admin)
            ->delete($this->host($this->tenant)."/admin/categories/{$category->id}")
            ->assertSessionHas('success');

        Storage::disk('public')->assertMissing('tenants/1/categories/aventura.png');
    }

    public function test_deleting_a_category_of_another_tenant_returns_404(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $other = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);
        $other->makeCurrent();
        $foreign = Category::factory()->create(['name' => 'Ajena']);
        $this->tenant->makeCurrent();

        $this->actingAs($admin)
            ->delete($this->host($this->tenant)."/admin/categories/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('categories', ['id' => $foreign->id]);
    }
}
