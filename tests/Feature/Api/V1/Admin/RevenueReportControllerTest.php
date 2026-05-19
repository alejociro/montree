<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RevenueReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_can_export_revenue_as_json(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $tenant->makeCurrent();

        $booking = Booking::factory()->confirmed()->create();
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 350,
            'processed_at' => Carbon::parse('2026-04-01 13:00:00'),
        ]);
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 150,
            'processed_at' => Carbon::parse('2026-04-02 14:00:00'),
        ]);

        Tenant::forgetCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://demo.montree.test/api/v1/admin/reports/revenue?from=2026-04-01&to=2026-04-30&group_by=day&format=json',
        );

        $response->assertOk();
        $response->assertJsonPath('data.from', '2026-04-01');
        $response->assertJsonPath('data.to', '2026-04-30');
        $response->assertJsonPath('data.group_by', 'day');
        $response->assertJsonPath('data.totals.gross', '500.00');
        $response->assertJsonCount(2, 'data.rows');
    }

    public function test_admin_can_export_revenue_as_csv(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'csv-demo', 'domain' => 'csv-demo.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $tenant->makeCurrent();

        $booking = Booking::factory()->confirmed()->create();
        Payment::factory()->completed()->for($booking)->create([
            'amount' => 200,
            'processed_at' => Carbon::parse('2026-04-15 13:00:00'),
        ]);

        Tenant::forgetCurrent();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->get(
            'http://csv-demo.montree.test/api/v1/admin/reports/revenue?from=2026-04-01&to=2026-04-30&format=csv',
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="revenue-2026-04-01-to-2026-04-30.csv"');

        $body = $response->streamedContent();
        $this->assertStringContainsString('bucket,gross,net,bookings_count', $body);
        $this->assertStringContainsString('2026-04-15,200.00,200.00,', $body);
    }

    public function test_operator_cannot_export_reports(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'op-deny', 'domain' => 'op-deny.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $operator = $this->memberFor($tenant, UserRole::Operator);

        $response = $this->actingAs($operator)->getJson(
            'http://op-deny.montree.test/api/v1/admin/reports/revenue?from=2026-04-01&to=2026-04-30',
        );

        $response->assertStatus(403);
    }

    public function test_range_greater_than_max_returns_validation_error(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'too-big', 'domain' => 'too-big.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://too-big.montree.test/api/v1/admin/reports/revenue?from=2024-01-01&to=2026-01-01',
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);
    }

    public function test_from_greater_than_to_returns_validation_error(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'rev-bad', 'domain' => 'rev-bad.montree.test']);
        TenantConfiguration::factory()->for($tenant)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->getJson(
            'http://rev-bad.montree.test/api/v1/admin/reports/revenue?from=2026-05-01&to=2026-04-01',
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from']);
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
