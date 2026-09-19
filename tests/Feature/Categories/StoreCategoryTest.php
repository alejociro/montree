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

final class StoreCategoryTest extends TestCase
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

    public function test_creating_a_category_with_an_icon_stores_it_slugged_and_active(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post($this->host($this->tenant).'/admin/categories', [
            'name' => 'Avistamiento de aves',
            'description' => 'Salidas de observación',
            'icon' => 'binoculars',
            'is_active' => 1,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('categories', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Avistamiento de aves',
            'slug' => 'avistamiento-de-aves',
            'icon' => 'binoculars',
            'image_path' => null,
            'is_active' => true,
        ]);
    }

    public function test_creating_a_category_with_an_image_stores_the_file_on_the_public_disk(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post($this->host($this->tenant).'/admin/categories', [
            'name' => 'Buceo',
            'image' => UploadedFile::fake()->image('buceo.png'),
        ]);

        $response->assertSessionHas('success');

        $category = Category::query()->where('slug', 'buceo')->sole();

        $this->assertNotNull($category->image_path);
        Storage::disk('public')->assertExists((string) $category->image_path);
        $this->assertStringStartsWith("tenants/{$this->tenant->id}/categories/", (string) $category->image_path);
    }

    public function test_a_duplicated_name_gets_a_numeric_suffix_on_its_slug(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        Category::factory()->create(['name' => 'Aventura', 'slug' => 'aventura']);

        $this->actingAs($admin)->post($this->host($this->tenant).'/admin/categories', [
            'name' => 'Aventura',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['name' => 'Aventura', 'slug' => 'aventura-2']);
    }

    public function test_an_icon_outside_the_curated_set_is_rejected(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $this->actingAs($admin)->postJson($this->host($this->tenant).'/admin/categories', [
            'name' => 'Aventura',
            'icon' => 'rocket',
        ])->assertStatus(422)->assertJsonValidationErrors('icon');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_a_file_that_is_not_a_supported_image_is_rejected(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $this->actingAs($admin)->postJson($this->host($this->tenant).'/admin/categories', [
            'name' => 'Aventura',
            'image' => UploadedFile::fake()->create('itinerario.pdf', 200, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_without_the_manage_permission_creating_is_forbidden(): void
    {
        $sales = $this->memberFor($this->tenant, UserRole::Sales);

        $this->actingAs($sales)->postJson($this->host($this->tenant).'/admin/categories', [
            'name' => 'Aventura',
        ])->assertForbidden();

        $this->assertDatabaseCount('categories', 0);
    }
}
