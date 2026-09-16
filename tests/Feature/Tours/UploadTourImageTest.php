<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class UploadTourImageTest extends TestCase
{
    use DepartureScenario;
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

    public function test_the_first_uploaded_image_becomes_the_cover(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->image('photo.jpg', 800, 600)->size(200)],
        );

        $response->assertSessionHas('success');
        $this->assertSame(1, $tour->images()->count());
        $image = $tour->images()->firstOrFail();
        $this->assertTrue((bool) $image->is_cover);
        Storage::disk('public')->assertExists((string) $image->path);
    }

    public function test_an_image_above_the_size_limit_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg')],
        );

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, $tour->images()->count());
    }

    public function test_an_invalid_mime_type_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        );

        $response->assertSessionHasErrors('image');
    }

    public function test_uploading_to_a_tour_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)->post(
            $this->host($tenantA)."/admin/tours/{$tourB->id}/images",
            ['image' => UploadedFile::fake()->image('photo.jpg')->size(100)],
        )->assertNotFound();
    }
}
