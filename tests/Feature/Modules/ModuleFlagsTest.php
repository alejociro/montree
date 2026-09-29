<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Enums\Module;
use App\Enums\TenantMembershipStatus;
use App\Enums\TourDateStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\NewsletterSubscriber;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * WHY: `phpunit.xml` corre la suite con los dos módulos ENCENDIDOS, que es el
 * producto completo. Apagarlos es el caso especial y se declara acá con
 * `config()->set`, en vez de obligar a cada test de promociones o newsletter a
 * encender su módulo en el `setUp`.
 */
final class ModuleFlagsTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://demo.montree.test';

    private Tenant $tenant;

    private User $admin;

    private Promotion $promotion;

    private NewsletterSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($this->tenant)->create();
        $this->tenant->makeCurrent();

        $this->admin = $this->memberFor(UserRole::Admin);
        $this->promotion = Promotion::factory()->create();
        $this->subscriber = NewsletterSubscriber::factory()->create();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    /**
     * Cada ruta de los dos módulos, pública y de panel.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function moduleRoutes(): array
    {
        return [
            'newsletter: baja pública' => ['newsletter', 'GET', '/unsubscribe/{token}', false],
            'newsletter: alta por API' => ['newsletter', 'POST', '/api/v1/newsletter/subscribe', false],
            'newsletter: baja por API' => ['newsletter', 'POST', '/api/v1/newsletter/unsubscribe', false],
            'newsletter: pantalla del panel' => ['newsletter', 'GET', '/admin/newsletter', true],
            'newsletter: suscriptores' => ['newsletter', 'GET', '/api/v1/admin/newsletter/subscribers', true],
            'newsletter: enviar campaña' => ['newsletter', 'POST', '/api/v1/admin/newsletter/send', true],
            'newsletter: enviar prueba' => ['newsletter', 'POST', '/api/v1/admin/newsletter/send-test', true],
            'newsletter: dar de baja a un suscriptor' => ['newsletter', 'PATCH', '/api/v1/admin/newsletter/subscribers/{subscriber}/unsubscribe', true],
            'promociones: pantalla del panel' => ['promotions', 'GET', '/admin/promotions', true],
            'promociones: validar un código' => ['promotions', 'POST', '/api/v1/promotions/validate', true],
            'promociones: listado' => ['promotions', 'GET', '/api/v1/admin/promotions', true],
            'promociones: creación' => ['promotions', 'POST', '/api/v1/admin/promotions', true],
            'promociones: detalle' => ['promotions', 'GET', '/api/v1/admin/promotions/{promotion}', true],
            'promociones: edición' => ['promotions', 'PUT', '/api/v1/admin/promotions/{promotion}', true],
            'promociones: baja' => ['promotions', 'DELETE', '/api/v1/admin/promotions/{promotion}', true],
        ];
    }

    #[DataProvider('moduleRoutes')]
    public function test_a_disabled_module_answers_not_found_even_to_an_admin(
        string $module,
        string $method,
        string $path,
        bool $authenticated,
    ): void {
        $this->disable($module);

        $this->visit($method, $path, $authenticated)->assertNotFound();
    }

    #[DataProvider('moduleRoutes')]
    public function test_an_enabled_module_keeps_answering(
        string $module,
        string $method,
        string $path,
        bool $authenticated,
    ): void {
        $this->assertNotSame(404, $this->visit($method, $path, $authenticated)->getStatusCode());
    }

    public function test_the_shared_props_report_which_modules_are_on(): void
    {
        $this->disable('promotions');

        $this->actingAs($this->admin)
            ->get(self::HOST.'/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('modules.promotions', false)
                ->where('modules.newsletter', true));
    }

    public function test_the_home_drops_the_promotions_prop_when_the_module_is_off(): void
    {
        $this->disable('promotions');

        $this->assertArrayNotHasKey('promotions', $this->homePromotions());
    }

    public function test_the_home_keeps_the_promotions_prop_when_the_module_is_on(): void
    {
        $this->assertArrayHasKey('promotions', $this->homePromotions());
    }

    public function test_the_role_catalog_hides_the_permissions_of_a_disabled_module(): void
    {
        $this->disable('promotions');
        $this->disable('newsletter');

        $response = $this->actingAs($this->admin)->getJson(self::HOST.'/api/v1/admin/roles');

        $response->assertOk();
        $modules = array_column($response->json('meta.available_permissions'), 'module');

        $this->assertNotContains('promotions', $modules);
        $this->assertNotContains('newsletter', $modules);
        $this->assertContains('tours', $modules);
    }

    public function test_a_role_never_counts_more_permissions_than_the_visible_catalog(): void
    {
        $this->disable('promotions');
        $this->disable('newsletter');

        $response = $this->actingAs($this->admin)->getJson(self::HOST.'/api/v1/admin/roles');

        $response->assertOk();
        $visible = count($response->json('meta.available_permissions'));
        $admin = collect($response->json('data'))->firstWhere('name', UserRole::Admin->value);

        $this->assertNotNull($admin);
        $this->assertSame($visible, $admin['permissions_count']);
    }

    public function test_editing_a_role_keeps_the_permissions_of_a_disabled_module(): void
    {
        setPermissionsTeamId($this->tenant->id);
        $role = Role::create(['name' => 'Ventas plus', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $role->syncPermissions(['tours.view', 'promotions.view']);

        $this->disable('promotions');

        $this->actingAs($this->admin)
            ->putJson(self::HOST.'/api/v1/admin/roles/'.$role->id, [
                'name' => 'Ventas plus',
                'permissions' => ['tours.view'],
            ])
            ->assertOk();

        $this->assertTrue($role->fresh()->hasPermissionTo('promotions.view'));
    }

    public function test_the_checkout_rejects_a_promotion_code_when_the_module_is_off(): void
    {
        $this->disable('promotions');

        $this->actingAs($this->admin)
            ->postJson(self::HOST.'/api/v1/bookings', [
                'tour_date_id' => $this->openTourDate()->id,
                'adults_count' => 1,
                'minors_count' => 0,
                'promotion_code' => $this->promotion->code,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('promotion_code');
    }

    public function test_the_checkout_still_takes_a_promotion_code_when_the_module_is_on(): void
    {
        $this->actingAs($this->admin)
            ->postJson(self::HOST.'/api/v1/bookings', [
                'tour_date_id' => $this->openTourDate()->id,
                'adults_count' => 1,
                'minors_count' => 0,
            ])
            ->assertCreated();

        $this->assertTrue(Module::Promotions->isEnabled());
    }

    /**
     * Props del parcial que resuelve `promotions`: la prop es diferida, así que
     * no viaja en el primer render.
     *
     * @return array<string, mixed>
     */
    private function homePromotions(): array
    {
        $response = $this->get(self::HOST.'/', [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'Home',
            'X-Inertia-Partial-Data' => 'promotions',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '',
        ]);

        $response->assertOk();

        return $response->json('props');
    }

    private function disable(string $module): void
    {
        config()->set('montree.modules.'.$module, false);
    }

    private function visit(string $method, string $path, bool $authenticated): TestResponse
    {
        $url = self::HOST.str_replace(
            ['{token}', '{subscriber}', '{promotion}'],
            [$this->subscriber->unsubscribe_token, (string) $this->subscriber->id, (string) $this->promotion->id],
            $path,
        );

        $test = $authenticated ? $this->actingAs($this->admin) : $this;

        return str_starts_with($path, '/api/')
            ? $test->json($method, $url)
            : $test->call($method, $url);
    }

    private function openTourDate(): TourDate
    {
        $tour = Tour::factory()->create(['status' => TourStatus::Active, 'base_price' => '100000.00']);

        return TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(7),
            'capacity' => 10,
            'booked_count' => 0,
            'status' => TourDateStatus::Open,
        ]);
    }

    private function memberFor(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
