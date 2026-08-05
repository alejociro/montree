<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SubdomainAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    private function check(string $slug): TestResponse
    {
        return $this->getJson('http://montree.test/api/v1/onboarding/subdomain-availability?slug='.$slug);
    }

    public function test_reports_available_for_a_free_slug(): void
    {
        $response = $this->check('eco-adventures');

        $response->assertOk()->assertExactJson([
            'slug' => 'eco-adventures',
            'available' => true,
            'reason' => null,
        ]);
    }

    public function test_reports_taken_for_an_existing_slug(): void
    {
        Tenant::factory()->create(['slug' => 'eco-adventures']);

        $response = $this->check('eco-adventures');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'taken']);
    }

    public function test_reports_reserved_for_a_brand_slug(): void
    {
        $response = $this->check('admin');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'reserved']);
    }

    public function test_reports_invalid_format_for_a_malformed_slug(): void
    {
        $response = $this->check('a_b');

        $response->assertOk()->assertJson(['available' => false, 'reason' => 'invalid_format']);
    }

    public function test_requires_a_slug(): void
    {
        $this->getJson('http://montree.test/api/v1/onboarding/subdomain-availability')
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }
}
