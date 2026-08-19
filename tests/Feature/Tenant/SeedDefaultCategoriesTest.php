<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Actions\Onboarding\RegisterAgencyAction;
use App\Actions\SuperAdmin\CreateTenantAction;
use App\Actions\Tenant\SeedDefaultCategoriesAction;
use App\Enums\TenantPlan;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedDefaultCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_it_seeds_the_configured_categories_into_the_tenant(): void
    {
        Config::set('montree.default_categories', [
            ['name' => 'Senderismo', 'icon' => 'mountain'],
            ['name' => 'Aventura', 'icon' => 'compass'],
        ]);

        $tenant = Tenant::factory()->create();

        $created = app(SeedDefaultCategoriesAction::class)->handle($tenant);

        $this->assertSame(2, $created);

        $categories = Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->orderBy('display_order')
            ->get();

        $this->assertSame(['Senderismo', 'Aventura'], $categories->pluck('name')->all());
        $this->assertSame(['senderismo', 'aventura'], $categories->pluck('slug')->all());
        $this->assertSame([0, 1], $categories->pluck('display_order')->all());
        $this->assertTrue($categories->every(fn (Category $category): bool => $category->is_active));
    }

    public function test_it_is_idempotent_and_does_not_duplicate_categories(): void
    {
        Config::set('montree.default_categories', [
            ['name' => 'Senderismo', 'icon' => 'mountain'],
        ]);

        $tenant = Tenant::factory()->create();
        $action = app(SeedDefaultCategoriesAction::class);

        $action->handle($tenant);
        $secondRun = $action->handle($tenant);

        $this->assertSame(0, $secondRun);
        $this->assertSame(1, Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count());
    }

    public function test_it_never_leaks_categories_into_another_tenant(): void
    {
        Config::set('montree.default_categories', [
            ['name' => 'Senderismo', 'icon' => 'mountain'],
        ]);

        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();

        $other->makeCurrent();

        app(SeedDefaultCategoriesAction::class)->handle($tenant);

        $this->assertSame(0, Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $other->id)
            ->count());
        $this->assertSame(1, Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count());
    }

    public function test_it_tolerates_an_empty_configuration(): void
    {
        Config::set('montree.default_categories', []);

        $tenant = Tenant::factory()->create();

        $this->assertSame(0, app(SeedDefaultCategoriesAction::class)->handle($tenant));
        $this->assertSame(0, Category::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_the_backfill_command_seeds_a_single_tenant_by_slug(): void
    {
        Config::set('montree.default_categories', [
            ['name' => 'Senderismo', 'icon' => 'mountain'],
        ]);

        $target = Tenant::factory()->create(['slug' => 'target-agency']);
        $untouched = Tenant::factory()->create();

        $this->artisan('montree:seed-default-categories', ['--tenant' => 'target-agency'])
            ->assertSuccessful();

        $this->assertSame(1, Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $target->id)
            ->count());
        $this->assertSame(0, Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $untouched->id)
            ->count());
    }

    public function test_the_backfill_command_fails_on_an_unknown_slug(): void
    {
        $this->artisan('montree:seed-default-categories', ['--tenant' => 'does-not-exist'])
            ->assertFailed();
    }

    public function test_super_admin_provisioning_seeds_the_new_tenant(): void
    {
        Notification::fake();
        Config::set('montree.default_categories', [
            ['name' => 'Senderismo', 'icon' => 'mountain'],
            ['name' => 'Aventura', 'icon' => 'compass'],
        ]);
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $tenant = app(CreateTenantAction::class)->handle([
            'name' => 'Eco Adventures',
            'slug' => 'eco-adventures',
            'plan' => TenantPlan::Professional->value,
            'admin_name' => 'Ada Admin',
            'admin_email' => 'ada@eco-adventures.test',
        ]);

        $this->assertSame(2, Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count());
    }

    public function test_self_serve_onboarding_seeds_the_new_tenant(): void
    {
        Event::fake();
        Config::set('montree.default_categories', [
            ['name' => 'Senderismo', 'icon' => 'mountain'],
            ['name' => 'Aventura', 'icon' => 'compass'],
        ]);
        Role::findOrCreate(UserRole::Admin->value, 'web');

        $tenant = app(RegisterAgencyAction::class)->handle([
            'agency_name' => 'Rio Tours',
            'subdomain' => 'rio-tours',
            'founder_name' => 'Fran Founder',
            'email' => 'fran@rio-tours.test',
            'password' => 'password',
        ]);

        $this->assertSame(2, Category::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->count());
    }
}
