<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Actions\Tenant\SeedDefaultCategoriesAction;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class CreateTenantAction
{
    public function __construct(
        private CreateTenantUserAction $createUser,
        private SeedDefaultCategoriesAction $seedCategories,
    ) {}

    /**
     * Provision a new tenant plus its initial admin user. The tenant is created
     * active and its admin receives an email invitation to set their password.
     *
     * @param  array{name:string, slug:string, plan:string, admin_name:string, admin_email:string}  $data
     */
    public function handle(array $data): Tenant
    {
        $tenant = DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'domain' => $data['slug'].'.'.Config::get('montree.platform_host'),
                'contact_email' => $data['admin_email'],
                'status' => TenantStatus::Active,
                'plan' => TenantPlan::from($data['plan']),
            ]);

            TenantConfiguration::query()->create(['tenant_id' => $tenant->id]);

            $this->seedCategories->handle($tenant);

            return $tenant;
        });

        $this->createUser->handle($tenant, [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'role' => UserRole::Admin->value,
        ]);

        return $tenant->fresh()->loadMissing('configuration');
    }
}
