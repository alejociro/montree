<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Provider;
use App\Models\Review;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * WHY: this is the authorization boundary of `admin/*`, subject by subject. It was born
 * (Fase 0) as a photograph of the `hasRole()` era so the migration to `can:<permiso>`
 * had a safety net; F018 rewrote the cells the feature deliberately moved and left the
 * rest untouched, so any accidental widening or narrowing still breaks a case here.
 *
 * Every cell where `operator` used to pass only because the group middleware
 * `tenant_admin.only` let it in — the holes listed in F018 `spec.md` §"Cierre de huecos
 * de autorización" — now reads OPERATOR_FORBIDDEN. The reason is always the same: after
 * F018 `operator` is product/operations only, and reservas, reseñas, newsletter, equipo
 * and promociones belong to `sales`. Cells that did NOT change are the ones the matrix
 * kept identical (tours, salidas, logística).
 *
 * When a later feature intentionally changes a boundary, update the expectation in the
 * matching data provider in the same commit.
 *
 * Out of scope on purpose: `dashboard.show` and `reports.revenue`, already covered by
 * DashboardControllerTest and RevenueReportControllerTest.
 */
final class CurrentAdminAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://rbac-matrix.montree.test';

    private const PASSES = true;

    private const FORBIDDEN = false;

    private Tenant $tenant;

    private User $admin;

    private User $sales;

    private User $operator;

    private User $guide;

    private User $customer;

    /**
     * @var array<string, Model>
     */
    private array $resources = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'slug' => 'rbac-matrix',
            'domain' => 'rbac-matrix.montree.test',
        ]);
        TenantConfiguration::factory()->for($this->tenant)->create();

        $this->admin = $this->memberFor(UserRole::Admin);
        $this->sales = $this->memberFor(UserRole::Sales);
        $this->operator = $this->memberFor(UserRole::Operator);
        $this->guide = $this->memberFor(UserRole::Guide);
        $this->customer = $this->memberFor(UserRole::Customer);

        Tenant::forgetCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function tenantSettingsRoutes(): array
    {
        return [
            'tenant.update' => ['tenant.update', 'PUT', [], [], self::FORBIDDEN, self::FORBIDDEN],
            'tenant.configuration.update' => ['tenant.configuration.update', 'PUT', [], [], self::FORBIDDEN, self::FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function readOnlyOperationRoutes(): array
    {
        return [
            // F018: `bookings.view`, `reviews.view`, `newsletter.view` y `team.view` pasan a
            // ser de admin/vendedor. El operador entraba solo por el middleware de grupo.
            'bookings.index' => ['bookings.index', 'GET', [], [], self::FORBIDDEN, self::PASSES],
            'tour-dates.index' => ['tour-dates.index', 'GET', [], [], self::PASSES, self::PASSES],
            'reviews.index' => ['reviews.index', 'GET', [], [], self::FORBIDDEN, self::PASSES],
            'newsletter.subscribers' => ['newsletter.subscribers', 'GET', [], [], self::FORBIDDEN, self::PASSES],
            'users.index' => ['users.index', 'GET', [], [], self::FORBIDDEN, self::FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function tourRoutes(): array
    {
        return [
            'tours.index' => ['tours.index', 'GET', [], [], self::PASSES, self::PASSES],
            'tours.store' => ['tours.store', 'POST', [], [], self::PASSES, self::FORBIDDEN],
            'tours.show' => ['tours.show', 'GET', ['tour'], [], self::PASSES, self::PASSES],
            'tours.update' => ['tours.update', 'PUT', ['tour'], [], self::PASSES, self::FORBIDDEN],
            'tours.destroy' => ['tours.destroy', 'DELETE', ['tour'], [], self::FORBIDDEN, self::FORBIDDEN],
            'tours.status to a non-archived status' => ['tours.status', 'PATCH', ['tour'], ['status' => 'active'], self::PASSES, self::FORBIDDEN],
            'tours.status to archived' => ['tours.status', 'PATCH', ['tour'], ['status' => 'archived'], self::FORBIDDEN, self::FORBIDDEN],
            'tours.images.store' => ['tours.images.store', 'POST', ['tour'], [], self::PASSES, self::FORBIDDEN],
            'tours.images.update' => ['tours.images.update', 'PATCH', ['tour', 'image'], [], self::PASSES, self::FORBIDDEN],
            'tours.images.destroy' => ['tours.images.destroy', 'DELETE', ['tour', 'image'], [], self::PASSES, self::FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function tourDateRoutes(): array
    {
        return [
            'tours.dates.index' => ['tours.dates.index', 'GET', ['tour'], [], self::PASSES, self::PASSES],
            'tours.dates.store' => ['tours.dates.store', 'POST', ['tour'], [], self::PASSES, self::FORBIDDEN],
            'tour-dates.update' => ['tour-dates.update', 'PUT', ['tourDate'], [], self::PASSES, self::FORBIDDEN],
            'tour-dates.cancel' => ['tour-dates.cancel', 'PATCH', ['tourDate'], [], self::PASSES, self::FORBIDDEN],
            // F018: `departures.delete` es de admin; borrar una salida deja de ser del operador.
            'tour-dates.destroy' => ['tour-dates.destroy', 'DELETE', ['tourDate'], [], self::FORBIDDEN, self::FORBIDDEN],
            'tour-dates.guide' => ['tour-dates.guide', 'PATCH', ['tourDate'], [], self::PASSES, self::FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function logisticsRoutes(): array
    {
        return [
            'routes.index' => ['routes.index', 'GET', [], [], self::PASSES, self::FORBIDDEN],
            'routes.store' => ['routes.store', 'POST', [], [], self::PASSES, self::FORBIDDEN],
            'routes.update' => ['routes.update', 'PUT', ['route'], [], self::PASSES, self::FORBIDDEN],
            'routes.destroy' => ['routes.destroy', 'DELETE', ['route'], [], self::PASSES, self::FORBIDDEN],
            'providers.index' => ['providers.index', 'GET', [], [], self::PASSES, self::FORBIDDEN],
            'providers.store' => ['providers.store', 'POST', [], [], self::PASSES, self::FORBIDDEN],
            'providers.update' => ['providers.update', 'PUT', ['provider'], [], self::PASSES, self::FORBIDDEN],
            'providers.destroy' => ['providers.destroy', 'DELETE', ['provider'], [], self::PASSES, self::FORBIDDEN],
            'hotels.index' => ['hotels.index', 'GET', [], [], self::PASSES, self::FORBIDDEN],
            'hotels.store' => ['hotels.store', 'POST', [], [], self::PASSES, self::FORBIDDEN],
            'hotels.update' => ['hotels.update', 'PUT', ['hotel'], [], self::PASSES, self::FORBIDDEN],
            'hotels.destroy' => ['hotels.destroy', 'DELETE', ['hotel'], [], self::PASSES, self::FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function promotionRoutes(): array
    {
        // F018: las promociones son del vendedor. El operador las perdió enteras.
        return [
            'promotions.index' => ['promotions.index', 'GET', [], [], self::FORBIDDEN, self::PASSES],
            'promotions.store' => ['promotions.store', 'POST', [], [], self::FORBIDDEN, self::PASSES],
            'promotions.show' => ['promotions.show', 'GET', ['promotion'], [], self::FORBIDDEN, self::PASSES],
            'promotions.update' => ['promotions.update', 'PUT', ['promotion'], [], self::FORBIDDEN, self::PASSES],
            'promotions.destroy' => ['promotions.destroy', 'DELETE', ['promotion'], [], self::FORBIDDEN, self::FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function moderationAndBillingRoutes(): array
    {
        // F018: moderar y responder reseñas viajan juntas al vendedor (decisión 3 de la
        // matriz); enviar campañas deja de ser exclusivo de admin por la misma razón.
        return [
            'reviews.status' => ['reviews.status', 'PATCH', ['review'], [], self::FORBIDDEN, self::PASSES],
            'reviews.respond' => ['reviews.respond', 'POST', ['review'], [], self::FORBIDDEN, self::PASSES],
            'payments.refund' => ['payments.refund', 'POST', ['payment'], [], self::FORBIDDEN, self::FORBIDDEN],
            'newsletter.send' => ['newsletter.send', 'POST', [], [], self::FORBIDDEN, self::PASSES],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool, 5: bool}>
     */
    public static function teamRoutes(): array
    {
        return [
            'users.store' => ['users.store', 'POST', [], [], self::FORBIDDEN, self::FORBIDDEN],
            'users.role' => ['users.role', 'PATCH', ['member'], [], self::FORBIDDEN, self::FORBIDDEN],
            'users.suspend' => ['users.suspend', 'PATCH', ['member'], [], self::FORBIDDEN, self::FORBIDDEN],
            'users.reactivate' => ['users.reactivate', 'PATCH', ['member'], [], self::FORBIDDEN, self::FORBIDDEN],
        ];
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('tenantSettingsRoutes')]
    public function test_tenant_settings_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('readOnlyOperationRoutes')]
    public function test_read_only_operation_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('tourRoutes')]
    public function test_tour_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('tourDateRoutes')]
    public function test_tour_date_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('logisticsRoutes')]
    public function test_logistics_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('promotionRoutes')]
    public function test_promotion_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('moderationAndBillingRoutes')]
    public function test_moderation_and_billing_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('teamRoutes')]
    public function test_team_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses, $salesPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    private function assertCurrentAccessMatrix(string $route, string $method, array $parameters, array $payload, bool $operatorPasses, bool $salesPasses): void
    {
        $call = fn (?User $user): TestResponse => $this->callRoute($user, $route, $method, $parameters, $payload);

        $this->assertPassesAuthorization($call($this->admin), $route, 'admin');
        $this->assertSubject($call($this->sales), $route, 'sales', $salesPasses);
        $this->assertSubject($call($this->operator), $route, 'operator', $operatorPasses);

        $call($this->guide)->assertForbidden();
        $call($this->customer)->assertForbidden();
        $call(null)->assertUnauthorized();
    }

    private function assertSubject(TestResponse $response, string $route, string $subject, bool $passes): void
    {
        if (! $passes) {
            $response->assertForbidden();

            return;
        }

        $this->assertPassesAuthorization($response, $route, $subject);
    }

    private function assertPassesAuthorization(TestResponse $response, string $route, string $subject): void
    {
        $this->assertNotContains(
            $response->getStatusCode(),
            [401, 403],
            sprintf(
                '[%s] %s is expected to get past authorization today, got %d.',
                $route,
                $subject,
                $response->getStatusCode(),
            ),
        );
    }

    /**
     * WHY: every subject gets its own untouched fixtures. Otherwise a destructive
     * case (tours.destroy, users.suspend) would leave the next subject hitting a
     * 404 from route model binding, which happens before the authorization layer
     * and would silently hide the boundary this test is meant to freeze.
     *
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    private function callRoute(?User $user, string $route, string $method, array $parameters, array $payload): TestResponse
    {
        $this->resources = [];
        Auth::forgetGuards();

        $url = self::HOST.route(
            'api.v1.admin.'.$route,
            array_map(fn (string $key): Model => $this->resource($key), $parameters),
            absolute: false,
        );

        if ($user !== null) {
            $this->actingAs($user);
        }

        return $this->json($method, $url, $payload);
    }

    private function resource(string $key): Model
    {
        return $this->resources[$key] ??= $this->createResource($key);
    }

    private function createResource(string $key): Model
    {
        $tour = in_array($key, ['tourDate', 'image'], true) ? $this->resource('tour') : null;

        if ($key === 'member') {
            return $this->memberFor(UserRole::Guide);
        }

        $this->tenant->makeCurrent();

        $model = match ($key) {
            'tour' => Tour::factory()->create(),
            'tourDate' => TourDate::factory()->for($tour)->create(),
            'image' => TourImage::factory()->for($tour)->create(),
            'review' => Review::factory()->create(),
            'promotion' => Promotion::factory()->create(),
            'hotel' => Hotel::factory()->create(),
            'route' => Route::factory()->create(),
            'provider' => Provider::factory()->create(),
            'payment' => Payment::factory()->completed()->create(),
        };

        Tenant::forgetCurrent();

        return $model;
    }

    private function memberFor(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');

        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
