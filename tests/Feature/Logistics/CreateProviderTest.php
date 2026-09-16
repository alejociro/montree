<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Enums\PaymentTerms;
use App\Enums\ProviderDocumentType;
use App\Enums\ProviderServiceType;
use App\Enums\RateUnit;
use App\Enums\UserRole;
use App\Models\Provider;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class CreateProviderTest extends TestCase
{
    use DepartureScenario;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_it_stores_a_provider_with_rates_and_documents(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/providers',
            [
                'name' => 'Transportes Andinos',
                'service_type' => ProviderServiceType::Transport->value,
                'legal_name' => 'Transportes Andinos S.A.S.',
                'tax_id' => '901.223.114-3',
                'payment_terms' => PaymentTerms::Advance50->value,
                'contact_name' => 'Jorge Rivas',
                'contact_phone' => '+57 310 555 8821',
                'city' => 'Armenia',
                'currency' => 'USD',
                'rates_valid_until' => '2026-12-31',
                'rates' => [
                    ['concept' => 'Bus 40 puestos', 'amount' => 320, 'unit' => RateUnit::PerService->value],
                    ['concept' => 'Van 12 puestos', 'amount' => 140, 'unit' => RateUnit::PerDay->value],
                ],
                'documents' => [
                    [
                        'kind' => ProviderDocumentType::LiabilityPolicy->value,
                        'number' => 'POL-90211',
                        'expires_at' => '2027-03-15',
                    ],
                ],
            ],
        );

        $response->assertSessionHas('success');

        $provider = Provider::query()->firstOrFail();
        $this->assertSame(ProviderServiceType::Transport, $provider->service_type);
        $this->assertSame('2026-12-31', $provider->rates_valid_until?->format('Y-m-d'));
        $this->assertSame(2, $provider->rates()->count());
        $this->assertSame(RateUnit::PerService, $provider->rates()->orderBy('position')->firstOrFail()->unit);
        $this->assertSame(1, $provider->documents()->count());
        $this->assertSame('2027-03-15', $provider->documents()->firstOrFail()->expires_at?->format('Y-m-d'));
    }

    public function test_an_unknown_enum_value_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/providers',
            ['name' => 'Raro', 'service_type' => 'teletransporte'],
        )->assertSessionHasErrors('service_type');
    }

    public function test_a_rate_without_a_concept_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->post(
            $this->host($tenant).'/admin/providers',
            [
                'name' => 'Sin concepto',
                'rates' => [['amount' => 10, 'unit' => RateUnit::PerDay->value]],
            ],
        )->assertSessionHasErrors('rates.0.concept');
    }

    public function test_the_new_provider_belongs_to_the_acting_tenant(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);
        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $this->actingAs($adminA)
            ->post($this->host($tenantA).'/admin/providers', ['name' => 'Solo de Alpha'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('providers', ['name' => 'Solo de Alpha', 'tenant_id' => $tenantA->id]);
    }
}
