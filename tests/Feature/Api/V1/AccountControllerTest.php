<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TenantMembershipStatus;
use App\Enums\TourStatus;
use App\Models\Favorite;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authedUser(): array
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $tenant->makeCurrent();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        return [$tenant, $user];
    }

    public function test_profile_endpoint_returns_user(): void
    {
        [, $user] = $this->authedUser();

        $this->actingAs($user)
            ->getJson('http://demo.montree.test/api/v1/account/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_profile_update_persists_changes(): void
    {
        [, $user] = $this->authedUser();

        $this->actingAs($user)->putJson(
            'http://demo.montree.test/api/v1/account/profile',
            ['name' => 'Nuevo Nombre', 'email' => $user->email, 'phone' => '+57 300 1111111'],
        )->assertOk()->assertJsonPath('data.name', 'Nuevo Nombre');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nuevo Nombre']);
    }

    public function test_favorites_endpoint_returns_user_favorites(): void
    {
        [, $user] = $this->authedUser();
        $tour = Tour::factory()->create(['status' => TourStatus::Active]);
        Favorite::query()->create(['user_id' => $user->id, 'tour_id' => $tour->id]);

        $this->actingAs($user)
            ->getJson('http://demo.montree.test/api/v1/account/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_bookings_groups_by_status(): void
    {
        [, $user] = $this->authedUser();
        $this->actingAs($user)
            ->getJson('http://demo.montree.test/api/v1/account/bookings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['upcoming', 'past', 'cancelled']]);
    }
}
