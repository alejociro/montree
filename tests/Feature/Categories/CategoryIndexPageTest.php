<?php

declare(strict_types=1);

namespace Tests\Feature\Categories;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class CategoryIndexPageTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant();
        $this->tenant->makeCurrent();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_page_carries_the_categories_in_display_order_with_their_tour_count(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        $second = Category::factory()->create(['name' => 'Aventura', 'display_order' => 2]);
        Category::factory()->create(['name' => 'Senderismo', 'display_order' => 1, 'icon' => 'mountain']);
        Tour::factory()->count(2)->create(['category_id' => $second->id]);

        $response = $this->actingAs($admin)->get($this->host($this->tenant).'/admin/categories');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Categories/Index', false)
            ->has('categories', 2)
            ->where('categories.0.name', 'Senderismo')
            ->where('categories.0.icon', 'mountain')
            ->where('categories.0.image_url', null)
            ->where('categories.0.tours_count', 0)
            ->where('categories.1.name', 'Aventura')
            ->where('categories.1.tours_count', 2)
            ->has('icons', 16)
            ->where('icons.0.value', 'mountain')
        );
    }

    public function test_the_page_only_lists_categories_of_the_current_tenant(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);
        Category::factory()->create(['name' => 'Propia']);

        $other = $this->makeTenant(['slug' => 'otra', 'domain' => 'otra.montree.test']);
        $other->makeCurrent();
        Category::factory()->create(['name' => 'Ajena']);
        $this->tenant->makeCurrent();

        $response = $this->actingAs($admin)->get($this->host($this->tenant).'/admin/categories');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories', 1)
            ->where('categories.0.name', 'Propia')
        );
    }

    public function test_without_the_view_permission_the_page_is_forbidden(): void
    {
        $guide = $this->memberFor($this->tenant, UserRole::Guide);

        $this->actingAs($guide)
            ->get($this->host($this->tenant).'/admin/categories')
            ->assertForbidden();
    }

    public function test_the_tour_count_does_not_add_a_query_per_category(): void
    {
        $admin = $this->memberFor($this->tenant, UserRole::Admin);

        $this->catalogOf(2);
        $this->countQueriesOfTheIndex($admin, 2);
        $withTwo = $this->countQueriesOfTheIndex($admin, 2);

        $this->catalogOf(8);
        $withTen = $this->countQueriesOfTheIndex($admin, 10);

        $this->assertSame(
            $withTwo,
            $withTen,
            "El listado pasó de {$withTwo} a {$withTen} consultas al crecer el catálogo.",
        );
    }

    private function countQueriesOfTheIndex(User $admin, int $expected): int
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin)
            ->get($this->host($this->tenant).'/admin/categories')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('categories', $expected));

        DB::getEventDispatcher()->forget(QueryExecuted::class);

        return $queries;
    }

    private function catalogOf(int $categories): void
    {
        foreach (range(1, $categories) as $index) {
            $category = Category::factory()->create(['display_order' => $index]);
            Tour::factory()->create(['category_id' => $category->id]);
        }
    }
}
