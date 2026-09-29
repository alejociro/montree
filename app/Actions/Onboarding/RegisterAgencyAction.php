<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Tenant\SeedDefaultCategoriesAction;
use App\Enums\Currency;
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
use Illuminate\Support\Str;

final class RegisterAgencyAction
{
    public function __construct(
        private readonly AttachUserToTenant $attachUserToTenant,
        private readonly SeedDefaultCategoriesAction $seedCategories,
    ) {}

    public function handle(array $data): Tenant
    {
        try {
            [$tenant, $founder] = DB::transaction(fn (): array => $this->provision($data));
        } catch (QueryException $e) {
            throw $this->translateSlugCollision($e);
        }

        AgencyRegistered::dispatch($tenant, $founder);

        return $tenant;
    }

    private function provision(array $data): array
    {
        $tenant = Tenant::query()->create([
            'name' => $data['agency_name'],
            'slug' => $data['subdomain'],
            'domain' => $data['subdomain'].'.'.Config::get('montree.platform_host'),
            'contact_email' => $data['email'],
            'status' => TenantStatus::Pending,
            'plan' => $this->defaultPlan(),
        ]);

        TenantConfiguration::query()->create([
            'tenant_id' => $tenant->id,
            'currency' => Currency::FALLBACK,
        ]);

        $this->seedCategories->execute($tenant);

        $user = User::query()->create([
            'name' => $data['founder_name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        // WHY: prueba de la autorización (Ley 1581, art. 9). Solo se sella lo que
        // el formulario de registro recibió aceptado; sin la bandera queda en null.
        $user->forceFill([
            'password_set_at' => now(),
            'terms_accepted_at' => ($data['accepts_terms'] ?? false) === true ? now() : null,
            'data_policy_accepted_at' => ($data['accepts_data_policy'] ?? false) === true ? now() : null,
        ])->save();

        $this->attachUserToTenant->handle($user, $tenant, UserRole::Admin, 'onboarding');

        return [$tenant, $user];
    }

    private function defaultPlan(): TenantPlan
    {
        $plan = Config::get('montree.onboarding.default_plan');

        return $plan instanceof TenantPlan ? $plan : TenantPlan::Professional;
    }

    private function translateSlugCollision(QueryException $e): QueryException|SubdomainTakenException
    {
        $isIntegrityViolation = (string) $e->getCode() === '23000';
        $hitTheTenantsTable = Str::contains(Str::lower($e->getMessage()), (new Tenant)->getTable());

        if ($isIntegrityViolation && $hitTheTenantsTable) {
            return new SubdomainTakenException;
        }

        return $e;
    }
}
