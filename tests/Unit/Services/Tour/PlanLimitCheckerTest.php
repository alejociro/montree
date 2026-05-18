<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tour;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use App\Models\Tour;
use App\Services\Tour\PlanLimitChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        parent::tearDown();
    }

    public function test_basic_plan_can_create_until_reaching_limit(): void
    {
        $tenant = Tenant::factory()->basic()->create();
        $tenant->makeCurrent();
        $checker = new PlanLimitChecker;

        Tour::factory()->count(9)->create();
        $this->assertTrue($checker->canCreateTour($tenant));

        Tour::factory()->create();
        $this->assertFalse($checker->canCreateTour($tenant));
    }

    public function test_per_tenant_override_takes_precedence(): void
    {
        $tenant = Tenant::factory()->basic()->create(['plan_limits' => ['max_tours' => 2]]);
        $tenant->makeCurrent();
        $checker = new PlanLimitChecker;

        Tour::factory()->count(2)->create();
        $this->assertFalse($checker->canCreateTour($tenant));
        $this->assertSame(2, $checker->maxToursForTenant($tenant));
    }

    public function test_enterprise_plan_has_higher_limit(): void
    {
        $tenant = Tenant::factory()->enterprise()->create();
        $tenant->makeCurrent();
        $checker = new PlanLimitChecker;

        $this->assertSame(TenantPlan::Enterprise->limits()['max_tours'], $checker->maxToursForTenant($tenant));
    }
}
