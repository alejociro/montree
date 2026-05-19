<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_sees_paginated_recent_bookings(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $tenant->makeCurrent();

        $tour = Tour::factory()->active()->create();
        Booking::factory()->count(3)->confirmed()->create(['tour_id' => $tour->id]);

        Tenant::forgetCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/bookings?per_page=10',
        );

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'data' => [['id', 'booking_number', 'status', 'tour_name', 'total_amount']],
            'links',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
    }

    public function test_attention_only_filters_to_pending_or_expiring_soon(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'attn', 'domain' => 'attn.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $tenant->makeCurrent();

        $tour = Tour::factory()->active()->create();

        Booking::factory()->create([
            'tour_id' => $tour->id,
            'status' => BookingStatus::PendingPayment,
            'expires_at' => Carbon::now()->addHour(),
        ]);
        Booking::factory()->confirmed()->create([
            'tour_id' => $tour->id,
            'expires_at' => null,
        ]);
        Booking::factory()->cancelled()->create(['tour_id' => $tour->id]);

        Tenant::forgetCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://attn.montree.test/api/v1/admin/bookings?attention_only=1',
        );

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', BookingStatus::PendingPayment->value);
    }

    public function test_customer_cannot_list_admin_bookings(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'no-access', 'domain' => 'no-access.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $customer = $this->memberFor($tenant, UserRole::Customer);

        $response = $this->actingAs($customer)->getJson(
            'http://no-access.montree.test/api/v1/admin/bookings',
        );

        $response->assertStatus(403);
    }

    public function test_per_page_validation_rejects_out_of_range_values(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'pp', 'domain' => 'pp.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://pp.montree.test/api/v1/admin/bookings?per_page=500',
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
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
