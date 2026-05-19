<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin\Tour;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TourImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_admin_uploads_image_first_image_becomes_cover(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->image('photo.jpg', 800, 600)->size(200)],
            ['Accept' => 'application/json'],
        );

        $response->assertCreated();
        $response->assertJsonPath('data.is_cover', true);
        $this->assertSame(1, $tour->images()->count());
        $imagePath = (string) $tour->images()->first()?->path;
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_image_above_size_limit_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg')],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_invalid_mime_type_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_setting_cover_unmarks_previous_cover(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $oldCover = TourImage::factory()->for($tour)->cover()->create();
        $other = TourImage::factory()->for($tour)->create(['is_cover' => false, 'display_order' => 2]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patchJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/images/{$other->id}",
            ['is_cover' => true],
        );

        $response->assertOk();
        $response->assertJsonPath('data.is_cover', true);
        $this->assertFalse((bool) $oldCover->fresh()?->is_cover);
        $this->assertTrue((bool) $other->fresh()?->is_cover);
    }

    public function test_destroy_image_removes_record_and_file(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->post(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->image('photo.jpg')->size(200)],
            ['Accept' => 'application/json'],
        )->assertCreated();

        $image = $tour->images()->first();
        $path = (string) $image?->path;

        $response = $this->actingAs($admin)->deleteJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tour->id}/images/{$image?->id}",
        );

        $response->assertNoContent();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, $tour->images()->count());
    }

    public function test_image_from_other_tour_returns_404(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tourA = Tour::factory()->create();
        $tourB = Tour::factory()->create();
        $imageB = TourImage::factory()->for($tourB)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->deleteJson(
            "http://demo.montree.test/api/v1/admin/tours/{$tourA->id}/images/{$imageB->id}",
        );

        $response->assertStatus(404);
    }

    private function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge([
            'slug' => 'demo',
            'domain' => 'demo.montree.test',
        ], $attrs));
        TenantConfiguration::factory()->for($tenant)->create();

        return $tenant;
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
