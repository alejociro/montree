<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Promotion;

use App\Enums\PromotionType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromotionValidationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_validates_percentage_promotion_happy_path(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->create([
            'code' => 'VERANO2026',
            'type' => PromotionType::Percentage,
            'value' => '10',
        ]);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'verano2026',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '120000.00',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.code', 'VERANO2026');
        $response->assertJsonPath('data.discount', '12000.00');
        $response->assertJsonPath('data.total_after', '108000.00');
    }

    public function test_validates_fixed_promotion_with_max_discount_cap(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->create([
            'code' => 'BIG50',
            'type' => PromotionType::Percentage,
            'value' => '50',
            'max_discount' => '20000.00',
        ]);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'BIG50',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '100000.00',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount', '20000.00');
        $response->assertJsonPath('data.total_after', '80000.00');
    }

    public function test_returns_not_found_error_for_unknown_code(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'GHOST',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(404);
        $response->assertJsonPath('error_code', 'PROMOTION_NOT_FOUND');
    }

    public function test_returns_expired_error_when_ends_at_in_past(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->expired()->create(['code' => 'OLDIE']);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'OLDIE',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_EXPIRED');
    }

    public function test_returns_inactive_error_when_promotion_disabled(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->create(['code' => 'OFF', 'is_active' => false]);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'OFF',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_INACTIVE');
    }

    public function test_returns_inactive_error_when_starts_at_in_future(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->create([
            'code' => 'SOON',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'SOON',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_INACTIVE');
    }

    public function test_returns_exhausted_error_when_max_uses_reached(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->exhausted()->create(['code' => 'EMPTY']);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'EMPTY',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_EXHAUSTED');
    }

    public function test_returns_min_amount_not_met_error_when_subtotal_low(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->create(['code' => 'BIG', 'min_amount' => '100000.00']);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'BIG',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_MIN_AMOUNT_NOT_MET');
    }

    public function test_returns_tour_not_applicable_error(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $allowedTour = Tour::factory()->create();
        $otherTourDate = $this->makeTourDate();
        Promotion::factory()->create([
            'code' => 'TOURONLY',
            'applicable_tours' => [$allowedTour->id],
        ]);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'TOURONLY',
                'tour_date_id' => $otherTourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_TOUR_NOT_APPLICABLE');
    }

    public function test_allows_tour_when_present_in_applicable_tours(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        Promotion::factory()->create([
            'code' => 'TOURIN',
            'type' => PromotionType::Fixed,
            'value' => '5000',
            'applicable_tours' => [$tourDate->tour_id],
        ]);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'TOURIN',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.discount', '5000.00');
    }

    public function test_returns_user_limit_reached_error(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();
        $tourDate = $this->makeTourDate();
        $promotion = Promotion::factory()->create([
            'code' => 'ONEPERUSER',
            'max_uses_per_user' => 1,
        ]);
        Booking::factory()->for($customer)->for($tourDate->tour)->for($tourDate, 'tourDate')->create([
            'promotion_id' => $promotion->id,
        ]);

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => 'ONEPERUSER',
                'tour_date_id' => $tourDate->id,
                'subtotal' => '50000.00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PROMOTION_USER_LIMIT_REACHED');
    }

    public function test_unauthenticated_user_cannot_validate(): void
    {
        $this->makeTenant();
        Tenant::forgetCurrent();

        $response = $this->postJson('http://demo.montree.test/api/v1/promotions/validate', [
            'code' => 'ANY',
            'tour_date_id' => 1,
            'subtotal' => '100',
        ]);

        $response->assertStatus(401);
    }

    public function test_request_validates_required_payload(): void
    {
        [$tenant, $customer] = $this->bootstrapTenantAndCustomer();

        $response = $this->actingAs($customer)
            ->postJson('http://demo.montree.test/api/v1/promotions/validate', [
                'code' => '',
                'tour_date_id' => 99999,
                'subtotal' => '-10',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'tour_date_id', 'subtotal']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function bootstrapTenantAndCustomer(): array
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $customer = $this->memberFor($tenant, UserRole::Customer);

        return [$tenant, $customer];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
    }

    private function makeTourDate(): TourDate
    {
        $tour = Tour::factory()->create();

        return TourDate::factory()->for($tour)->create();
    }

    private function memberFor(Tenant $tenant, UserRole $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
