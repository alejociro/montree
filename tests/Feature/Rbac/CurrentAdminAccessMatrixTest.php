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
 * WHY: this is a photograph of the authorization boundary of `admin/*` AS IT IS TODAY,
 * not as it should be. It exists so the Fase 1 migration from `hasRole()` to
 * `can:<permiso>` has a safety net: any accidental widening or narrowing of access
 * breaks a case here.
 *
 * It therefore freezes the current holes as well. Today `operator` can manage
 * logistics, tour dates, tour images, reviews listing, newsletter subscribers and the
 * team listing with no Policy at all (the group middleware `tenant_admin.only` is the
 * only gate), while the admin-only checks that do exist are scattered across
 * FormRequest::authorize(), inline `hasRole()` in controllers and Gate calls. Do NOT
 * "fix" any of that here — describe it. When Fase 1 intentionally changes a boundary,
 * update the expectation in the matching data provider in the same commit.
 *
 * Out of scope on purpose: `dashboard.show` and `reports.revenue`, already covered by
 * DashboardControllerTest and RevenueReportControllerTest.
 */
final class CurrentAdminAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://rbac-matrix.montree.test';

    private const OPERATOR_PASSES = true;

    private const OPERATOR_FORBIDDEN = false;

    private Tenant $tenant;

    private User $admin;

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
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function tenantSettingsRoutes(): array
    {
        return [
            'tenant.update' => ['tenant.update', 'PUT', [], [], self::OPERATOR_FORBIDDEN],
            'tenant.configuration.update' => ['tenant.configuration.update', 'PUT', [], [], self::OPERATOR_FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function readOnlyOperationRoutes(): array
    {
        return [
            'bookings.index' => ['bookings.index', 'GET', [], [], self::OPERATOR_PASSES],
            'tour-dates.index' => ['tour-dates.index', 'GET', [], [], self::OPERATOR_PASSES],
            'reviews.index' => ['reviews.index', 'GET', [], [], self::OPERATOR_PASSES],
            'newsletter.subscribers' => ['newsletter.subscribers', 'GET', [], [], self::OPERATOR_PASSES],
            'users.index' => ['users.index', 'GET', [], [], self::OPERATOR_PASSES],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function tourRoutes(): array
    {
        return [
            'tours.index' => ['tours.index', 'GET', [], [], self::OPERATOR_PASSES],
            'tours.store' => ['tours.store', 'POST', [], [], self::OPERATOR_PASSES],
            'tours.show' => ['tours.show', 'GET', ['tour'], [], self::OPERATOR_PASSES],
            'tours.update' => ['tours.update', 'PUT', ['tour'], [], self::OPERATOR_PASSES],
            'tours.destroy' => ['tours.destroy', 'DELETE', ['tour'], [], self::OPERATOR_FORBIDDEN],
            'tours.status to a non-archived status' => ['tours.status', 'PATCH', ['tour'], ['status' => 'active'], self::OPERATOR_PASSES],
            'tours.status to archived' => ['tours.status', 'PATCH', ['tour'], ['status' => 'archived'], self::OPERATOR_FORBIDDEN],
            'tours.images.store' => ['tours.images.store', 'POST', ['tour'], [], self::OPERATOR_PASSES],
            'tours.images.update' => ['tours.images.update', 'PATCH', ['tour', 'image'], [], self::OPERATOR_PASSES],
            'tours.images.destroy' => ['tours.images.destroy', 'DELETE', ['tour', 'image'], [], self::OPERATOR_PASSES],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function tourDateRoutes(): array
    {
        return [
            'tours.dates.index' => ['tours.dates.index', 'GET', ['tour'], [], self::OPERATOR_PASSES],
            'tours.dates.store' => ['tours.dates.store', 'POST', ['tour'], [], self::OPERATOR_PASSES],
            'tour-dates.update' => ['tour-dates.update', 'PUT', ['tourDate'], [], self::OPERATOR_PASSES],
            'tour-dates.cancel' => ['tour-dates.cancel', 'PATCH', ['tourDate'], [], self::OPERATOR_PASSES],
            'tour-dates.destroy' => ['tour-dates.destroy', 'DELETE', ['tourDate'], [], self::OPERATOR_PASSES],
            'tour-dates.guide' => ['tour-dates.guide', 'PATCH', ['tourDate'], [], self::OPERATOR_PASSES],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function logisticsRoutes(): array
    {
        return [
            'routes.index' => ['routes.index', 'GET', [], [], self::OPERATOR_PASSES],
            'routes.store' => ['routes.store', 'POST', [], [], self::OPERATOR_PASSES],
            'routes.update' => ['routes.update', 'PUT', ['route'], [], self::OPERATOR_PASSES],
            'routes.destroy' => ['routes.destroy', 'DELETE', ['route'], [], self::OPERATOR_PASSES],
            'providers.index' => ['providers.index', 'GET', [], [], self::OPERATOR_PASSES],
            'providers.store' => ['providers.store', 'POST', [], [], self::OPERATOR_PASSES],
            'providers.update' => ['providers.update', 'PUT', ['provider'], [], self::OPERATOR_PASSES],
            'providers.destroy' => ['providers.destroy', 'DELETE', ['provider'], [], self::OPERATOR_PASSES],
            'hotels.index' => ['hotels.index', 'GET', [], [], self::OPERATOR_PASSES],
            'hotels.store' => ['hotels.store', 'POST', [], [], self::OPERATOR_PASSES],
            'hotels.update' => ['hotels.update', 'PUT', ['hotel'], [], self::OPERATOR_PASSES],
            'hotels.destroy' => ['hotels.destroy', 'DELETE', ['hotel'], [], self::OPERATOR_PASSES],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function promotionRoutes(): array
    {
        return [
            'promotions.index' => ['promotions.index', 'GET', [], [], self::OPERATOR_PASSES],
            'promotions.store' => ['promotions.store', 'POST', [], [], self::OPERATOR_PASSES],
            'promotions.show' => ['promotions.show', 'GET', ['promotion'], [], self::OPERATOR_PASSES],
            'promotions.update' => ['promotions.update', 'PUT', ['promotion'], [], self::OPERATOR_PASSES],
            'promotions.destroy' => ['promotions.destroy', 'DELETE', ['promotion'], [], self::OPERATOR_FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function moderationAndBillingRoutes(): array
    {
        return [
            'reviews.status' => ['reviews.status', 'PATCH', ['review'], [], self::OPERATOR_FORBIDDEN],
            'reviews.respond' => ['reviews.respond', 'POST', ['review'], [], self::OPERATOR_PASSES],
            'payments.refund' => ['payments.refund', 'POST', ['payment'], [], self::OPERATOR_FORBIDDEN],
            'newsletter.send' => ['newsletter.send', 'POST', [], [], self::OPERATOR_FORBIDDEN],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: array<string, mixed>, 4: bool}>
     */
    public static function teamRoutes(): array
    {
        return [
            'users.store' => ['users.store', 'POST', [], [], self::OPERATOR_FORBIDDEN],
            'users.role' => ['users.role', 'PATCH', ['member'], [], self::OPERATOR_FORBIDDEN],
            'users.suspend' => ['users.suspend', 'PATCH', ['member'], [], self::OPERATOR_FORBIDDEN],
            'users.reactivate' => ['users.reactivate', 'PATCH', ['member'], [], self::OPERATOR_FORBIDDEN],
        ];
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('tenantSettingsRoutes')]
    public function test_tenant_settings_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('readOnlyOperationRoutes')]
    public function test_read_only_operation_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('tourRoutes')]
    public function test_tour_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('tourDateRoutes')]
    public function test_tour_date_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('logisticsRoutes')]
    public function test_logistics_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('promotionRoutes')]
    public function test_promotion_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('moderationAndBillingRoutes')]
    public function test_moderation_and_billing_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('teamRoutes')]
    public function test_team_routes_keep_their_current_access(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $this->assertCurrentAccessMatrix($route, $method, $parameters, $payload, $operatorPasses);
    }

    /**
     * @param  array<int, string>  $parameters
     * @param  array<string, mixed>  $payload
     */
    private function assertCurrentAccessMatrix(string $route, string $method, array $parameters, array $payload, bool $operatorPasses): void
    {
        $call = fn (?User $user): TestResponse => $this->callRoute($user, $route, $method, $parameters, $payload);

        $this->assertPassesAuthorization($call($this->admin), $route, 'admin');

        $operatorPasses
            ? $this->assertPassesAuthorization($call($this->operator), $route, 'operator')
            : $call($this->operator)->assertForbidden();

        $call($this->guide)->assertForbidden();
        $call($this->customer)->assertForbidden();
        $call(null)->assertUnauthorized();
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
