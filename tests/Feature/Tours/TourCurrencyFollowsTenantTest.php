<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

/**
 * El producto ya no tiene moneda propia: hereda la de la agencia en cada alta y
 * en cada edición (spec §H).
 */
final class TourCurrencyFollowsTenantTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_a_new_tour_takes_the_currency_of_the_tenant(): void
    {
        $tenant = $this->makeTenant();
        $tenant->configuration->update(['currency' => 'COP']);
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post($this->host($tenant).'/admin/tours', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('COP', Tour::query()->sole()->currency);
    }

    public function test_a_currency_sent_by_the_client_is_ignored(): void
    {
        $tenant = $this->makeTenant();
        $tenant->configuration->update(['currency' => 'COP']);
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post($this->host($tenant).'/admin/tours', [...$this->payload(), 'currency' => 'EUR'])
            ->assertSessionHasNoErrors();

        $this->assertSame('COP', Tour::query()->sole()->currency);
    }

    public function test_editing_a_tour_realigns_it_with_the_current_currency(): void
    {
        $tenant = $this->makeTenant();
        $tenant->configuration->update(['currency' => 'COP']);
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['currency' => 'USD']);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->put($this->host($tenant).'/admin/tours/'.$tour->id, ['name' => 'Otro nombre'])
            ->assertSessionHasNoErrors();

        $this->assertSame('COP', $tour->fresh()?->currency);
    }

    public function test_the_currency_of_another_tenant_never_leaks_into_the_product(): void
    {
        $other = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $other->configuration->update(['currency' => 'EUR']);

        $tenant = $this->makeTenant();
        $tenant->configuration->update(['currency' => 'PEN']);
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)
            ->post($this->host($tenant).'/admin/tours', $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('PEN', Tour::query()->sole()->currency);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Sendero del Quindío',
            'description' => 'Recorrido por el valle del Cocora.',
            'base_price' => '150000.00',
            'duration_hours' => 6,
            'difficulty' => 'moderate',
            'default_capacity' => 12,
            'meeting_point' => 'Plaza Cocora',
            'meeting_latitude' => 4.6371,
            'meeting_longitude' => -75.5096,
            'stops' => [
                ['kind' => 'pickup', 'name' => 'Plaza Cocora', 'latitude' => 4.6371, 'longitude' => -75.5096],
                ['kind' => 'site', 'name' => 'Mirador', 'latitude' => 4.6428, 'longitude' => -75.4790],
                ['kind' => 'drop', 'name' => 'Plaza Cocora', 'latitude' => 4.6371, 'longitude' => -75.5096],
            ],
        ];
    }
}
