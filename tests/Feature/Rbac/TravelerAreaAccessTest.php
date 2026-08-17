<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cierre de los bugs B4 y A3 (F018 Fase 2):
 *
 * - B4: el staff de la agencia no tiene zona de viajero. `EnsureTenantMember` solo pedía
 *   membresía activa, así que un admin entraba a `/account/*` y ahí perdía el panel.
 * - A3: `/dashboard` redirigía fijo a `/account/bookings` para los cinco roles, en vez de
 *   usar el home de rol que el login ya resolvía bien.
 *
 * Ambos cortes se resuelven por permiso (`dashboard.view` / `guide.schedule.view`) contra
 * el mismo `RoleHomeResolver` que usa el login, no por nombre de rol.
 */
final class TravelerAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://traveler-area.montree.test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'traveler-area',
            'domain' => 'traveler-area.montree.test',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: UserRole, 1: string}>
     */
    public static function staffRoles(): array
    {
        return [
            'admin' => [UserRole::Admin, RoleHomeResolver::ADMIN_HOME],
            'sales' => [UserRole::Sales, RoleHomeResolver::ADMIN_HOME],
            'operator' => [UserRole::Operator, RoleHomeResolver::ADMIN_HOME],
            'guide' => [UserRole::Guide, RoleHomeResolver::GUIDE_HOME],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function travelerRoutes(): array
    {
        return [
            'account.profile' => ['/account'],
            'account.bookings' => ['/account/bookings'],
            'account.favorites' => ['/account/favorites'],
            'account.notifications' => ['/account/notifications'],
            'account.bookings.review' => ['/account/bookings/BK-0001/review'],
        ];
    }

    /**
     * Las mismas rutas menos la de reseña, que necesita una reserva propia y se prueba
     * aparte: acá lo que importa es que el viajero siga entrando.
     *
     * @return array<string, array{0: string}>
     */
    public static function travelerPages(): array
    {
        return array_diff_key(self::travelerRoutes(), ['account.bookings.review' => null]);
    }

    #[DataProvider('staffRoles')]
    public function test_staff_is_redirected_out_of_the_traveler_area(UserRole $role, string $home): void
    {
        $staff = $this->memberFor($role);

        $response = $this->actingAs($staff)->get(self::HOST.'/account/bookings');

        $response->assertRedirect($home);
    }

    #[DataProvider('travelerRoutes')]
    public function test_every_traveler_route_is_closed_to_staff(string $path): void
    {
        $admin = $this->memberFor(UserRole::Admin);

        $response = $this->actingAs($admin)->get(self::HOST.$path);

        $response->assertRedirect(RoleHomeResolver::ADMIN_HOME);
    }

    #[DataProvider('travelerPages')]
    public function test_customer_keeps_the_traveler_area(string $path): void
    {
        $customer = $this->memberFor(UserRole::Customer);

        $response = $this->actingAs($customer)->get(self::HOST.$path);

        $response->assertOk();
    }

    public function test_customer_still_reviews_their_own_booking(): void
    {
        $customer = $this->memberFor(UserRole::Customer);
        $booking = $this->bookingFor($customer);

        $response = $this->actingAs($customer)->get(self::HOST."/account/bookings/{$booking->booking_number}/review");

        $response->assertOk();
    }

    #[DataProvider('staffRoles')]
    public function test_dashboard_redirects_staff_to_their_role_home(UserRole $role, string $home): void
    {
        $staff = $this->memberFor($role);

        $response = $this->actingAs($staff)->get(self::HOST.'/dashboard');

        $response->assertRedirect($home);
    }

    public function test_dashboard_sends_the_customer_to_the_agency_home(): void
    {
        $customer = $this->memberFor(UserRole::Customer);

        $response = $this->actingAs($customer)->get(self::HOST.'/dashboard');

        $response->assertRedirect(RoleHomeResolver::TRAVELER_HOME);
    }

    /**
     * Borde: miembro activo sin ninguna fila en `model_has_roles` (anomalía de datos, o un
     * rol propio del tenant todavía sin permisos). Sin permisos de panel es un viajero.
     */
    public function test_member_without_roles_is_treated_as_a_traveler(): void
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        $this->actingAs($user)->get(self::HOST.'/account/bookings')->assertOk();
        $this->actingAs($user)->get(self::HOST.'/dashboard')->assertRedirect(RoleHomeResolver::TRAVELER_HOME);
    }

    /**
     * Aislamiento: los roles de spatie son por equipo. Ser admin en la agencia A no puede
     * cerrarle la zona de viajero al mismo usuario en la agencia B, donde es cliente.
     */
    public function test_staff_of_another_tenant_keeps_the_traveler_area_here(): void
    {
        $other = Tenant::factory()->create([
            'slug' => 'traveler-area-b',
            'domain' => 'traveler-area-b.montree.test',
        ]);
        TenantConfiguration::factory()->for($other)->create();

        $user = $this->memberFor(UserRole::Customer);
        $other->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        setPermissionsTeamId($other->id);
        $user->assignRole(UserRole::Admin->value);
        Tenant::forgetCurrent();

        $this->actingAs($user)->get(self::HOST.'/account/bookings')->assertOk();
        $this->actingAs($user)->get('http://traveler-area-b.montree.test/account/bookings')
            ->assertRedirect(RoleHomeResolver::ADMIN_HOME);
    }

    private function bookingFor(User $customer): Booking
    {
        $this->tenant->makeCurrent();
        $booking = Booking::factory()->create(['user_id' => $customer->id]);
        Tenant::forgetCurrent();

        return $booking;
    }

    private function memberFor(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);

        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);
        Tenant::forgetCurrent();

        return $user;
    }
}
