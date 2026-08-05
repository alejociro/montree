<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\OnboardingHandoff;
use Illuminate\Http\Request;
use Tests\TestCase;

class OnboardingHandoffTest extends TestCase
{
    private function subject(): OnboardingHandoff
    {
        return new OnboardingHandoff;
    }

    private function tenant(int $id, string $slug): Tenant
    {
        $tenant = new Tenant(['slug' => $slug]);
        $tenant->id = $id;

        return $tenant;
    }

    private function user(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    public function test_builds_signed_claim_url_on_subdomain_preserving_scheme_and_port(): void
    {
        $url = $this->subject()->issueClaimUrl(
            $this->tenant(1, 'eco-adventures'),
            $this->user(5),
            Request::create('http://montree.test:8000/onboarding/verify/1/5', 'GET'),
        );

        $this->assertStringStartsWith('http://eco-adventures.montree.test:8000/onboarding/claim', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('nonce=', $url);
    }

    public function test_nonce_is_single_use(): void
    {
        $handoff = $this->subject();

        $url = $handoff->issueClaimUrl(
            $this->tenant(2, 'eco'),
            $this->user(9),
            Request::create('http://montree.test/onboarding/verify/2/9', 'GET'),
        );

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $nonce = (string) $query['nonce'];

        $first = $handoff->consume($nonce);
        $second = $handoff->consume($nonce);

        $this->assertSame(['tenant_id' => 2, 'user_id' => 9], $first);
        $this->assertNull($second);
    }

    public function test_consume_returns_null_for_unknown_nonce(): void
    {
        $this->assertNull($this->subject()->consume('does-not-exist'));
    }
}
