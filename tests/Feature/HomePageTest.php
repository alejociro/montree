<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_guest_sees_the_landing_on_the_platform_host(): void
    {
        $response = $this->get('http://montree.test/');

        $response->assertOk();
        $response->assertSee('Landing');
    }

    public function test_super_admin_is_redirected_to_their_panel_from_the_platform_home(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        setPermissionsTeamId(0);
        $user->assignRole(UserRole::SuperAdmin->value);

        $response = $this->actingAs($user)->get('http://montree.test/');

        $response->assertRedirect('/super-admin/dashboard');
    }

    public function test_home_exposes_active_categories_with_active_tour_counts(): void
    {
        $tenant = $this->makeTenant();

        $withOne = Category::factory()->create(['display_order' => 0]);
        $withTwo = Category::factory()->create(['display_order' => 5]);
        $draftOnly = Category::factory()->create(['display_order' => 3]);
        $inactive = Category::factory()->inactive()->create(['display_order' => 1]);

        Tour::factory()->active()->for($withOne)->create();
        Tour::factory()->active()->count(2)->for($withTwo)->create();
        Tour::factory()->for($withTwo)->create();
        Tour::factory()->for($draftOnly)->create();
        Tour::factory()->active()->for($inactive)->create();

        $response = $this->partialReload($tenant, ['categories']);

        $response->assertOk();
        $this->assertCount(2, $response->json('props.categories'));
        $response->assertJsonPath('props.categories.0.name', $withOne->name);
        $response->assertJsonPath('props.categories.0.tours_count', 1);
        $response->assertJsonPath('props.categories.1.name', $withTwo->name);
        $response->assertJsonPath('props.categories.1.tours_count', 2);
    }

    public function test_home_exposes_approved_high_rated_testimonials(): void
    {
        $tenant = $this->makeTenant();

        $author = User::factory()->create(['name' => 'Marta Ruiz']);
        $tour = Tour::factory()->active()->create(['name' => 'Ruta del Volcán', 'slug' => 'ruta-del-volcan']);

        Review::factory()->approved()->for($author)->for($tour)->create([
            'rating' => 5,
            'title' => 'Inolvidable',
            'comment' => 'Una experiencia increíble de principio a fin.',
        ]);
        Review::factory()->for($tour)->create(['rating' => 5, 'status' => ReviewStatus::Pending]);
        Review::factory()->approved()->for($tour)->create(['rating' => 3]);

        $response = $this->partialReload($tenant, ['testimonials']);

        $response->assertOk();
        $this->assertCount(1, $response->json('props.testimonials'));
        $response->assertJsonPath('props.testimonials.0.rating', 5);
        $response->assertJsonPath('props.testimonials.0.title', 'Inolvidable');
        $response->assertJsonPath('props.testimonials.0.body', 'Una experiencia increíble de principio a fin.');
        $response->assertJsonPath('props.testimonials.0.author_name', 'Marta Ruiz');
        $response->assertJsonPath('props.testimonials.0.tour.name', 'Ruta del Volcán');
        $response->assertJsonPath('props.testimonials.0.tour.slug', 'ruta-del-volcan');
        $this->assertNotNull($response->json('props.testimonials.0.created_at'));
    }

    public function test_home_returns_empty_testimonials_when_none_qualify(): void
    {
        $tenant = $this->makeTenant();

        $tour = Tour::factory()->active()->create();
        Review::factory()->for($tour)->create(['rating' => 5, 'status' => ReviewStatus::Pending]);

        $response = $this->partialReload($tenant, ['testimonials']);

        $response->assertOk();
        $this->assertSame([], $response->json('props.testimonials'));
    }

    public function test_home_isolates_categories_and_testimonials_by_tenant(): void
    {
        $tenant = $this->makeTenant();

        $ownCategory = Category::factory()->create(['display_order' => 0]);
        $ownTour = Tour::factory()->active()->for($ownCategory)->create();
        Review::factory()->approved()->for($ownTour)->for(User::factory())->create(['rating' => 5, 'title' => 'Nuestra reseña']);

        $otherTenant = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        TenantConfiguration::factory()->for($otherTenant)->create();
        $otherTenant->makeCurrent();
        $otherCategory = Category::factory()->create(['display_order' => 0]);
        $otherTour = Tour::factory()->active()->for($otherCategory)->create();
        Review::factory()->approved()->for($otherTour)->for(User::factory())->create(['rating' => 5, 'title' => 'Reseña ajena']);

        $tenant->makeCurrent();

        $response = $this->partialReload($tenant, ['categories', 'testimonials']);

        $response->assertOk();
        $this->assertCount(1, $response->json('props.categories'));
        $response->assertJsonPath('props.categories.0.name', $ownCategory->name);
        $this->assertCount(1, $response->json('props.testimonials'));
        $response->assertJsonPath('props.testimonials.0.title', 'Nuestra reseña');
    }

    private function makeTenant(): Tenant
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $tenant->makeCurrent();

        return $tenant;
    }

    /**
     * @param  array<int, string>  $only
     */
    private function partialReload(Tenant $tenant, array $only): TestResponse
    {
        $version = app(HandleInertiaRequests::class)->version(request());

        return $this->get('http://'.$tenant->domain.'/', [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Home',
            'X-Inertia-Partial-Data' => implode(',', $only),
            'X-Inertia-Version' => $version ?? '',
        ]);
    }
}
