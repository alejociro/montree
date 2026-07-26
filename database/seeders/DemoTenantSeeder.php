<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use App\Models\TourItinerary;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        // WHY: previous cached Tenant payloads can become __PHP_Incomplete_Class
        // when the model shape changes between fresh migrations.
        Cache::flush();

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'super@montree.test'],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password'),
                'password_set_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        // WHY: spatie/permission with teams=true requires a team scope when assigning roles.
        // For the global super_admin we use sentinel team_id=0 (no real tenant).
        setPermissionsTeamId(0);
        $superAdmin->unsetRelation('roles');
        $superAdmin->syncRoles([UserRole::SuperAdmin->value]);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Eco Adventures',
                'domain' => 'demo.'.config('montree.super_admin_host'),
                'contact_email' => 'hello@demo.montree.test',
                'contact_phone' => '+57 300 000 0000',
                'status' => TenantStatus::Active,
                'plan' => TenantPlan::Professional,
                'trial_ends_at' => null,
            ],
        );

        TenantConfiguration::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'primary_color' => '#16a34a',
                'secondary_color' => '#0f766e',
                'currency' => 'COP',
                'timezone' => 'America/Bogota',
                'locale' => 'es',
                'tagline' => 'Aventuras inolvidables en Colombia',
                'description' => 'Agencia demo precargada para desarrollo local.',
                'social_links' => ['instagram' => 'https://instagram.com/demo'],
                'contact_info' => ['email' => 'hello@demo.montree.test'],
                'reviews_require_moderation' => true,
                'require_traveler_details' => true,
            ],
        );

        $tenant->makeCurrent();

        $admin = $this->ensureMember($tenant, 'admin@demo.montree.test', 'Demo Admin', UserRole::Admin);
        $this->ensureMember($tenant, 'operator@demo.montree.test', 'Demo Operator', UserRole::Operator);
        $this->ensureMember($tenant, 'guide@demo.montree.test', 'Demo Guide', UserRole::Guide);
        $this->ensureMember($tenant, 'customer@demo.montree.test', 'Demo Customer', UserRole::Customer);

        $categories = collect([
            ['name' => 'Senderismo', 'icon' => 'mountain'],
            ['name' => 'Aventura', 'icon' => 'compass'],
            ['name' => 'Cultural', 'icon' => 'palette'],
        ])->map(fn (array $payload, int $index) => Category::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => Str::slug($payload['name'])],
            [
                'name' => $payload['name'],
                'icon' => $payload['icon'],
                'display_order' => $index,
                'is_active' => true,
            ],
        ));

        $routes = collect([
            ['name' => 'Ruta El Mirador', 'distance_km' => 12.50, 'duration_hours' => 5.0],
            ['name' => 'Ruta Cascadas', 'distance_km' => 8.20, 'duration_hours' => 3.5],
        ])->map(fn (array $payload) => Route::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $payload['name']],
            [
                'description' => 'Ruta demo precargada para desarrollo local.',
                'distance_km' => $payload['distance_km'],
                'duration_hours' => $payload['duration_hours'],
            ],
        ));

        $providers = collect([
            ['name' => 'Transportes Andinos', 'service_type' => 'transporte'],
            ['name' => 'Cocina del Valle', 'service_type' => 'alimentación'],
        ])->map(fn (array $payload) => Provider::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $payload['name']],
            [
                'service_type' => $payload['service_type'],
                'contact_name' => 'Contacto Demo',
                'contact_phone' => '+57 300 111 2233',
                'contact_email' => 'contacto@demo.montree.test',
            ],
        ));

        $hotels = collect([
            ['name' => 'Ecohotel La Montaña'],
            ['name' => 'Posada del Río'],
        ])->map(fn (array $payload) => Hotel::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $payload['name']],
            [
                'address' => 'Vereda Demo, Colombia',
                'contact_phone' => '+57 300 444 5566',
                'contact_email' => 'reservas@demo.montree.test',
            ],
        ));

        foreach (range(1, 5) as $i) {
            $tour = Tour::factory()
                ->state([
                    'category_id' => $categories->random()->id,
                    'name' => "Tour Demo #$i",
                    'slug' => "tour-demo-$i",
                    'status' => TourStatus::Active,
                ])
                ->create();

            TourImage::factory()->cover()->for($tour)->create();
            TourImage::factory()->count(2)->for($tour)->create();

            foreach ([1, 2, 3] as $step) {
                TourItinerary::factory()->for($tour)->state([
                    'step_number' => $step,
                ])->create();
            }

            $dates = TourDate::factory()->count(2)->for($tour)->state([
                'guide_id' => $admin->id,
            ])->create();

            // WHY: assign support logistics to the first date so demo data exercises
            // the route/provider/hotel relations end-to-end.
            $firstDate = $dates->first();
            $firstDate->update([
                'route_id' => $routes->random()->id,
                'provider_id' => $providers->random()->id,
            ]);
            $firstDate->hotels()->sync([$hotels->random()->id]);
        }

        Tenant::forgetCurrent();
    }

    private function ensureMember(Tenant $tenant, string $email, string $name, UserRole $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'password_set_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'status' => TenantMembershipStatus::Active->value,
                'joined_at' => now(),
            ],
        ]);

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $user->syncRoles([$role->value]);

        return $user;
    }
}
