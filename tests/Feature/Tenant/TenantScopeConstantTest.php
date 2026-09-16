<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El global scope de `BelongsToTenant` se registra por nombre, no por clase.
 *
 * WHY: hasta B2 el código llamaba `withoutGlobalScope(BelongsToTenant::class)`,
 * que Eloquent ignora en silencio porque la clave registrada es `'tenant'`. El
 * filtro seguía puesto sin decirlo. Este test fija `Tenant::SCOPE` como el único
 * identificador válido.
 */
final class TenantScopeConstantTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_constant_lifts_the_scope_across_tenants(): void
    {
        $first = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $second = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);

        $first->makeCurrent();
        Tour::factory()->create();
        $second->makeCurrent();
        Tour::factory()->count(2)->create();

        $first->makeCurrent();

        $this->assertSame(1, Tour::query()->count());
        $this->assertSame(3, Tour::query()->withoutGlobalScope(Tenant::SCOPE)->count());
    }

    public function test_the_trait_class_name_is_not_a_scope_key(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $other = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);

        $other->makeCurrent();
        Tour::factory()->create();
        $tenant->makeCurrent();

        // Pasar la clase no levanta nada: sigue viéndose solo el tenant actual.
        $this->assertSame(0, Tour::query()->withoutGlobalScope(BelongsToTenant::class)->count());
    }

    public function test_the_scope_is_inert_without_a_current_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();
        Tour::factory()->count(2)->create();

        Tenant::forgetCurrent();

        $this->assertSame(2, Tour::query()->count());
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }
}
