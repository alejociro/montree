<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contracts\CheckoutClientFactory;
use App\Exceptions\PaymentException;
use App\Models\Tenant;
use App\Services\PlaceToPay\CheckoutCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CheckoutCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'placetopay.login' => 'platform-login',
            'placetopay.tran_key' => 'platform-tran-key',
            'placetopay.environment' => 'test',
            'placetopay.environments.test' => 'https://checkout.platform.test',
            'placetopay.environments.production' => 'https://checkout.placetopay.com',
        ]);
    }

    public function test_a_tenant_without_its_own_merchant_falls_back_to_the_platform(): void
    {
        $credentials = CheckoutCredentials::resolve($this->tenant());

        $this->assertNotNull($credentials);
        $this->assertSame('platform-login', $credentials->login);
        $this->assertSame('platform-tran-key', $credentials->tranKey);
        $this->assertSame('https://checkout.platform.test', $credentials->url);
    }

    /**
     * T14: el respaldo de plataforma también usa el ambiente de plataforma,
     * no siempre "test".
     */
    public function test_a_tenant_without_its_own_merchant_falls_back_to_the_platform_environment(): void
    {
        config(['placetopay.environment' => 'production']);

        $credentials = CheckoutCredentials::resolve($this->tenant());

        $this->assertSame('https://checkout.placetopay.com', $credentials->url);
    }

    public function test_a_tenant_with_its_own_merchant_in_test_uses_the_test_endpoint(): void
    {
        $tenant = $this->tenant([
            'placetopay_login' => 'tenant-login',
            'placetopay_tran_key' => 'tenant-tran-key',
            'placetopay_environment' => 'test',
        ]);

        $credentials = CheckoutCredentials::resolve($tenant);

        $this->assertSame('tenant-login', $credentials->login);
        $this->assertSame('tenant-tran-key', $credentials->tranKey);
        $this->assertSame('https://checkout.platform.test', $credentials->url);
    }

    /**
     * T14: la URL viaja con el ambiente del comercio propio, no con el de
     * plataforma — un comercio propio en producción no debe autenticar
     * contra el sandbox.
     */
    public function test_a_tenant_with_its_own_merchant_in_production_uses_the_production_endpoint(): void
    {
        $tenant = $this->tenant([
            'placetopay_login' => 'tenant-login',
            'placetopay_tran_key' => 'tenant-tran-key',
            'placetopay_environment' => 'production',
        ]);

        $credentials = CheckoutCredentials::resolve($tenant);

        $this->assertSame('tenant-login', $credentials->login);
        $this->assertSame('https://checkout.placetopay.com', $credentials->url);
    }

    /**
     * T14: sin ambiente explícito, la columna trae el default de la
     * migración ('test'), así que un comercio propio nunca hereda el
     * ambiente de plataforma.
     */
    public function test_a_tenant_merchant_without_environment_defaults_to_test(): void
    {
        config(['placetopay.environment' => 'production']);

        $tenant = $this->tenant([
            'placetopay_login' => 'tenant-login',
            'placetopay_tran_key' => 'tenant-tran-key',
        ]);

        $credentials = CheckoutCredentials::resolve($tenant);

        $this->assertSame('tenant-login', $credentials->login);
        $this->assertSame('https://checkout.platform.test', $credentials->url);
    }

    public function test_no_credentials_anywhere_resolves_to_null(): void
    {
        config(['placetopay.login' => null, 'placetopay.tran_key' => null]);

        $this->assertNull(CheckoutCredentials::resolve($this->tenant()));
    }

    public function test_the_factory_refuses_to_build_a_client_without_credentials(): void
    {
        config(['placetopay.login' => null, 'placetopay.tran_key' => null]);

        $this->expectException(PaymentException::class);

        app(CheckoutClientFactory::class)->for($this->tenant());
    }

    public function test_the_tran_key_is_stored_encrypted_at_rest(): void
    {
        $tenant = $this->tenant([
            'placetopay_login' => 'tenant-login',
            'placetopay_tran_key' => 'super-secret',
        ]);

        $stored = (string) DB::table('tenant_configurations')
            ->where('tenant_id', $tenant->id)
            ->value('placetopay_tran_key');

        $this->assertNotSame('super-secret', $stored);
        $this->assertStringNotContainsString('super-secret', $stored);
        $this->assertSame('super-secret', $tenant->configuration->placetopay_tran_key);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function tenant(array $configuration = []): Tenant
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();

        if ($configuration !== []) {
            // updateOrCreate y no update(): el cast `encrypted` del tranKey solo
            // se aplica pasando por el modelo, no por el query builder.
            $tenant->configuration()->updateOrCreate(['tenant_id' => $tenant->id], $configuration);
        }

        return $tenant->fresh();
    }
}
