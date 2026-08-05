<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Events\AgencyRegistered;
use App\Exceptions\SubdomainTakenException;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Tenant\AttachUserToTenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class RegisterAgencyAction
{
    public function __construct(private AttachUserToTenant $attachUserToTenant) {}

    /**
     * Provision a pending agency plus its founder in a single transaction. The
     * tenant stays `pending` until the founder verifies their email.
     *
     * @param  array{agency_name: string, subdomain: string, founder_name: string, email: string, password: string}  $data
     */
    public function handle(array $data): Tenant
    {
        $slug = mb_strtolower($data['subdomain']);
        $email = mb_strtolower($data['email']);

        try {
            return DB::transaction(fn (): Tenant => $this->provision($data, $slug, $email));
        } catch (QueryException $e) {
            throw $this->translateSlugCollision($e, $slug);
        }
    }

    /**
     * @param  array{agency_name: string, subdomain: string, founder_name: string, email: string, password: string}  $data
     */
    private function provision(array $data, string $slug, string $email): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => $data['agency_name'],
            'slug' => $slug,
            'domain' => $slug.'.'.Config::get('montree.super_admin_host'),
            'contact_email' => $email,
            'status' => TenantStatus::Pending,
            'plan' => $this->defaultPlan(),
        ]);

        TenantConfiguration::query()->create(['tenant_id' => $tenant->id]);

        $user = User::query()->create([
            'name' => $data['founder_name'],
            'email' => $email,
            'password' => $data['password'],
        ]);
        $user->forceFill(['password_set_at' => now()])->save();

        $this->attachUserToTenant->handle($user, $tenant, UserRole::Admin, 'onboarding');

        AgencyRegistered::dispatch($tenant, $user);

        return $tenant;
    }

    private function defaultPlan(): TenantPlan
    {
        $plan = Config::get('montree.onboarding.default_plan');

        return $plan instanceof TenantPlan ? $plan : TenantPlan::Professional;
    }

    private function translateSlugCollision(QueryException $e, string $slug): QueryException|SubdomainTakenException
    {
        if (str_contains($e->getMessage(), $slug) || str_contains(mb_strtolower($e->getMessage()), 'slug')) {
            return new SubdomainTakenException;
        }

        return $e;
    }
}
