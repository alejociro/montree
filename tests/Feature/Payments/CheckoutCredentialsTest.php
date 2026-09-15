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
            'placetopay.url' => 'https://checkout.platform.test',
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

    public function test_a_tenant_with_its_own_merchant_uses_its_own_credentials_and_url(): void
    {
        $tenant = $this->tenant([
            'placetopay_login' => 'tenant-login',
            'placetopay_tran_key' => 'tenant-tran-key',
            'placetopay_url' => 'https://checkout.placetopay.ec',
        ]);

        $credentials = CheckoutCredentials::resolve($tenant);

        $this->assertSame('tenant-login', $credentials->login);
        $this->assertSame('tenant-tran-key', $credentials->tranKey);
        $this->assertSame('https://checkout.placetopay.ec', $credentials->url);
    }

    /**
     * Un comercio propio sin URL hereda la de plataforma; lo que nunca hereda es
     * el tranKey, porque un login propio con tranKey ajeno no autentica.
     */
    public function test_a_tenant_merchant_without_url_inherits_the_platform_endpoint(): void
    {
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
