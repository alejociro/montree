<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\TenantPlan;
use App\Enums\TenantStatus;
use App\Enums\TourStatus;
use App\Enums\TourStopKind;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingTraveler;
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
use App\Models\TourStop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoTenantSeeder extends Seeder
{
    /**
     * Días calendario ya ocupados por guía: `guide_id => ['Y-m-d' => true]`.
     * WHY (D9): el seeder no puede producir un solape. Cada salida se programa
     * en el primer hueco libre del guía, no en una fecha al azar.
     *
     * @var array<int, array<string, true>>
     */
    private array $occupiedDays = [];

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
                'domain' => 'demo.'.config('montree.platform_host'),
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
        $this->ensureMember($tenant, 'sales@demo.montree.test', 'Demo Sales', UserRole::Sales);
        $this->ensureMember($tenant, 'operator@demo.montree.test', 'Demo Operator', UserRole::Operator);
        $guide = $this->ensureMember($tenant, 'guide@demo.montree.test', 'Demo Guide', UserRole::Guide);
        $secondGuide = $this->ensureMember($tenant, 'guide2@demo.montree.test', 'Demo Guide 2', UserRole::Guide);
        $customer = $this->ensureMember($tenant, 'customer@demo.montree.test', 'Demo Customer', UserRole::Customer);

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
            if (Tour::query()->where('slug', "tour-demo-$i")->exists()) {
                continue;
            }

            $tour = Tour::factory()
                ->state([
                    'category_id' => $categories->random()->id,
                    'default_guide_id' => $guide->id,
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

            $dates = collect([
                $this->scheduleDeparture($tour, $guide),
                $this->scheduleDeparture($tour, $secondGuide),
            ]);

            // WHY: assign support logistics to the first date so demo data exercises
            // the route/provider/hotel relations end-to-end.
            $firstDate = $dates->first();
            $firstDate->update([
                'route_id' => $routes->random()->id,
                'provider_id' => $providers->random()->id,
            ]);
            $firstDate->hotels()->sync([$hotels->random()->id]);
        }

        $cocora = $this->seedRouteMapTour($categories->first()->id, $guide->id);
        $this->seedMultiDayTour($categories->last()->id, $guide->id);
        $this->seedManifestBookings($cocora, $customer);

        Tenant::forgetCurrent();
    }

    /**
     * Tour del Valle de Cocora con las paradas del handoff de diseño: es el único
     * dato demo que llena el mapa de ruta del detalle público de punta a punta.
     */
    private function seedRouteMapTour(int $categoryId, int $guideId): Tour
    {
        $existing = Tour::query()->where('slug', 'valle-de-cocora')->first();

        if ($existing !== null) {
            return $existing;
        }

        $tour = Tour::factory()
            ->state([
                'category_id' => $categoryId,
                'default_guide_id' => $guideId,
                'name' => 'Valle de Cocora',
                'slug' => 'valle-de-cocora',
                'short_description' => 'Caminata de un día entre las palmas de cera más altas del mundo.',
                'duration_hours' => 10,
                'meeting_point' => 'Plaza de Bolívar, Armenia',
                'meeting_latitude' => 4.5350,
                'meeting_longitude' => -75.6813,
                'status' => TourStatus::Active,
                'includes' => [
                    'Alojamiento 2 noches en Salento',
                    'Comidas durante la caminata',
                    'Guía experto certificado',
                    'Equipo necesario y seguro',
                ],
                'excludes' => [
                    'Vuelos hacia Armenia',
                    'Bebidas alcohólicas',
                    'Actividades no mencionadas',
                    'Propinas',
                ],
                'requirements' => [
                    'Usa ropa de senderismo adecuada y calzado resistente.',
                    'Mantente hidratado durante todo el recorrido.',
                    'Lleva impermeable: la lluvia en el valle es frecuente después del mediodía.',
                ],
            ])
            ->create();

        TourImage::factory()->cover()->for($tour)->create();
        TourImage::factory()->count(2)->for($tour)->create();

        foreach ([
            ['Salida desde Armenia', 'Recogida en la Plaza de Bolívar y traslado hasta Salento.', '1 h 10 min'],
            ['Sendero de las palmas', 'Caminata por el bosque de palmas de cera hasta la reserva Acaime.', '5 h'],
            ['Regreso', 'Traslado de vuelta a Armenia y fin del tour en la terminal.', '1 h 30 min'],
        ] as $index => [$title, $description, $duration]) {
            TourItinerary::factory()->for($tour)->state([
                'step_number' => $index + 1,
                'title' => $title,
                'description' => $description,
                'duration_label' => $duration,
            ])->create();
        }

        foreach ([
            [TourStopKind::Pickup, 'A', 'Recogida', 'Recogida · Plaza de Bolívar', 'Armenia, Quindío', '8:00 a. m.', 4.5350, -75.6813, 1],
            [TourStopKind::Site, '1', null, 'Salento — registro', 'Salento, Quindío', '9:10 a. m.', 4.6376, -75.5706, 2],
            [TourStopKind::Site, '2', null, 'Entrada Valle de Cocora', 'Salento, Quindío', '10:30 a. m.', 4.6378, -75.4869, 2],
            [TourStopKind::Site, '3', null, 'Bosque de palmas de cera', 'Valle de Cocora', '12:15 p. m.', 4.6428, -75.4790, 2],
            [TourStopKind::Site, '4', null, 'Reserva Acaime (2.860 m)', 'Valle de Cocora', '2:00 p. m.', 4.6497, -75.4620, 2],
            [TourStopKind::Drop, 'B', 'Regreso', 'Regreso · Terminal de Transportes', 'Armenia, Quindío', '5:30 p. m.', 4.5252, -75.6812, 3],
        ] as $index => [$kind, $code, $label, $name, $place, $time, $latitude, $longitude, $step]) {
            TourStop::factory()->for($tour)->state([
                'position' => $index + 1,
                'kind' => $kind,
                'code' => $code,
                'label' => $label,
                'name' => $name,
                'place' => $place,
                'time_label' => $time,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'itinerary_step' => $step,
            ])->create();
        }

        $guide = User::query()->findOrFail($guideId);

        $this->scheduleDeparture($tour, $guide);
        $this->scheduleDeparture($tour, $guide);

        return $tour;
    }

    /**
     * Tour de varios días: es el que permite probar en local que un guía queda
     * bloqueado los días completos de la salida (D9), no solo la mañana.
     */
    private function seedMultiDayTour(int $categoryId, int $guideId): void
    {
        if (Tour::query()->where('slug', 'travesia-sierra-nevada')->exists()) {
            return;
        }

        $tour = Tour::factory()
            ->state([
                'category_id' => $categoryId,
                'default_guide_id' => $guideId,
                'name' => 'Travesía Sierra Nevada — 4 días',
                'slug' => 'travesia-sierra-nevada',
                'short_description' => 'Cuatro días de travesía con campamento entre la selva y la nieve.',
                'duration_hours' => 96,
                'status' => TourStatus::Active,
            ])
            ->create();

        TourImage::factory()->cover()->for($tour)->create();

        foreach ([1, 2, 3] as $step) {
            TourItinerary::factory()->for($tour)->state(['step_number' => $step])->create();
        }

        $this->scheduleDeparture($tour, User::query()->findOrFail($guideId));
    }

    /**
     * Reservas de la planilla: los tres casos que hay que poder ver en pantalla
     * —con saldo, con observaciones y con EPS «Otra»— sobre una misma salida,
     * más una reserva sin viajeros cargados para la fila «Datos pendientes».
     */
    private function seedManifestBookings(Tour $tour, User $customer): void
    {
        $departure = $tour->dates()->orderBy('starts_at')->first();

        if ($departure === null || $departure->bookings()->exists()) {
            return;
        }

        $departure->update(['capacity' => max($departure->capacity, 12)]);

        // Reserva pagada: aporta el pasajero con observaciones médicas y el de EPS «Otra».
        $paid = Booking::factory()
            ->state([
                'user_id' => $customer->id,
                'tour_id' => $tour->id,
                'tour_date_id' => $departure->id,
                'travelers_count' => 2,
                'adults_count' => 2,
                'minors_count' => 0,
                'currency' => 'COP',
                'subtotal' => '360000.00',
                'discount_amount' => '0.00',
                'total_amount' => '360000.00',
            ])
            ->confirmed()
            ->create();

        BookingTraveler::factory()->for($paid)->withNotes()->create([
            'full_name' => 'María Fernanda Ríos',
            'email' => 'maria@example.com',
        ]);
        BookingTraveler::factory()->for($paid)->withOtherEps()->create([
            'full_name' => 'Julián Ríos',
            'email' => 'julian@example.com',
        ]);

        // Reserva a medio pagar: el pasajero con saldo pendiente.
        $due = Booking::factory()
            ->state([
                'user_id' => $customer->id,
                'tour_id' => $tour->id,
                'tour_date_id' => $departure->id,
                'travelers_count' => 1,
                'adults_count' => 1,
                'minors_count' => 0,
                'currency' => 'COP',
                'subtotal' => '180000.00',
                'discount_amount' => '0.00',
                'total_amount' => '180000.00',
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
                'expires_at' => null,
            ])
            ->create();

        BookingTraveler::factory()->for($due)->withDue()->create([
            'full_name' => 'Andrés Camilo Peña',
            'email' => 'andres@example.com',
        ]);

        // Reserva sin viajeros cargados: la planilla la pinta como «Datos pendientes».
        Booking::factory()
            ->state([
                'user_id' => $customer->id,
                'tour_id' => $tour->id,
                'tour_date_id' => $departure->id,
                'travelers_count' => 1,
                'adults_count' => 1,
                'minors_count' => 0,
                'currency' => 'COP',
                'subtotal' => '180000.00',
                'discount_amount' => '0.00',
                'total_amount' => '180000.00',
            ])
            ->confirmed()
            ->create();

        $departure->update(['booked_count' => 4]);
    }

    /**
     * Programa una salida en el primer día libre del guía. `ends_at` lo deriva la
     * factory de `tours.duration_hours`; acá solo se elige la fecha de inicio.
     */
    private function scheduleDeparture(Tour $tour, User $guide, array $state = []): TourDate
    {
        $duration = max(1, (int) $tour->duration_hours);
        $cursor = Carbon::today()->addDays(fake()->numberBetween(2, 6));

        while (true) {
            $startsAt = $cursor->copy()->setTime(7, 0);
            $days = $this->calendarDays($startsAt, $startsAt->copy()->addHours($duration));

            if (! $this->guideIsBusy($guide->id, $days)) {
                foreach ($days as $day) {
                    $this->occupiedDays[$guide->id][$day] = true;
                }

                return TourDate::factory()->for($tour)->create(array_merge([
                    'guide_id' => $guide->id,
                    'starts_at' => $startsAt,
                    'ends_at' => null,
                ], $state));
            }

            $cursor->addDay();
        }
    }

    /**
     * @param  array<int, string>  $days
     */
    private function guideIsBusy(int $guideId, array $days): bool
    {
        foreach ($days as $day) {
            if (isset($this->occupiedDays[$guideId][$day])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function calendarDays(Carbon $startsAt, Carbon $endsAt): array
    {
        $days = [];
        $cursor = $startsAt->copy()->startOfDay();
        $last = $endsAt->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $days;
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
