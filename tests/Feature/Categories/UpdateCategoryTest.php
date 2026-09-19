<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class UpdateCategoryTest extends TestCase
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

    public function test_uploading_an_image_replaces_the_icon_as_the_visible_face(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $category = Category::factory()->create(['name' => 'Aventura', 'icon' => 'compass']);

        $response = $this->actingAs($admin)->post($this->host($this->tenant)."/admin/categories/{$category->id}", [
            '_method' => 'put',
            'name' => 'Aventura',
            'icon' => 'compass',
            'image' => UploadedFile::fake()->image('aventura.webp'),
        ]);

        $response->assertSessionHas('success');

        $category->refresh();

        $this->assertNotNull($category->image_path);
        $this->assertSame('compass', $category->icon);
        Storage::disk('public')->assertExists((string) $category->image_path);
    }

    public function test_removing_the_image_deletes_the_stored_file(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $category = Category::factory()->create(['name' => 'Aventura']);

        $this->actingAs($admin)->post($this->host($this->tenant)."/admin/categories/{$category->id}", [
            '_method' => 'put',
            'name' => 'Aventura',
            'image' => UploadedFile::fake()->image('aventura.png'),
        ])->assertSessionHas('success');

        $stored = (string) $category->refresh()->image_path;

        $this->actingAs($admin)->post($this->host($this->tenant)."/admin/categories/{$category->id}", [
            '_method' => 'put',
            'name' => 'Aventura',
            'remove_image' => 1,
        ])->assertSessionHas('success');

        $this->assertNull($category->refresh()->image_path);
        Storage::disk('public')->assertMissing($stored);
    }

    public function test_renaming_keeps_the_slug_unique_within_the_tenant(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        Category::factory()->create(['name' => 'Cultural', 'slug' => 'cultural']);
        $category = Category::factory()->create(['name' => 'Aventura', 'slug' => 'aventura']);

        $this->actingAs($admin)->put($this->host($this->tenant)."/admin/categories/{$category->id}", [
            'name' => 'Cultural',
        ])->assertSessionHas('success');

        $this->assertSame('cultural-2', $category->refresh()->slug);
    }

    public function test_updating_a_category_of_another_tenant_returns_404(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $other = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);
        $other->makeCurrent();
        $foreign = Category::factory()->create(['name' => 'Ajena']);
        $this->tenant->makeCurrent();

        $this->actingAs($admin)
            ->put($this->host($this->tenant)."/admin/categories/{$foreign->id}", ['name' => 'Robada'])
            ->assertNotFound();

        $this->assertSame('Ajena', $foreign->refresh()->name);
    }

    public function test_without_the_manage_permission_updating_is_forbidden(): void
    {
        $sales = $this->memberFor($this->tenant, UserRole::Sales);
        $category = Category::factory()->create(['name' => 'Aventura']);

        $this->actingAs($sales)
            ->putJson($this->host($this->tenant)."/admin/categories/{$category->id}", ['name' => 'Otra'])
            ->assertForbidden();
    }
}
