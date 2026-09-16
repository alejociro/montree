<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class DeleteTourImageTest extends TestCase
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

    public function test_destroy_removes_the_record_and_the_stored_file(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->post(
            $this->host($tenant)."/admin/tours/{$tour->id}/images",
            ['image' => UploadedFile::fake()->image('photo.jpg')->size(200)],
        )->assertSessionHasNoErrors();

        $image = $tour->images()->firstOrFail();
        $path = (string) $image->path;

        $response = $this->actingAs($admin)->delete(
            $this->host($tenant)."/admin/tours/{$tour->id}/images/{$image->id}",
        );

        $response->assertSessionHas('success');
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, $tour->images()->count());
    }

    public function test_deleting_an_image_of_another_tour_returns_404(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tourA = Tour::factory()->create();
        $tourB = Tour::factory()->create();
        $imageB = TourImage::factory()->for($tourB)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->delete(
            $this->host($tenant)."/admin/tours/{$tourA->id}/images/{$imageB->id}",
        )->assertNotFound();

        $this->assertDatabaseHas('tour_images', ['id' => $imageB->id]);
    }

    public function test_deleting_an_image_of_another_tenant_returns_404(): void
    {
        $tenantA = $this->makeTenant(['slug' => 'alpha', 'domain' => 'alpha.montree.test']);
        $tenantB = $this->makeTenant(['slug' => 'bravo', 'domain' => 'bravo.montree.test']);

        $tenantA->makeCurrent();
        $adminA = $this->memberFor($tenantA, UserRole::Admin);

        $tenantB->makeCurrent();
        $tourB = Tour::factory()->create();
        $imageB = TourImage::factory()->for($tourB)->create();

        $tenantA->makeCurrent();
        $this->actingAs($adminA)->delete(
            $this->host($tenantA)."/admin/tours/{$tourB->id}/images/{$imageB->id}",
        )->assertNotFound();
    }
}
