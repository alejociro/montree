<?php

declare(strict_types=1);

namespace Tests\Feature\Tours;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DepartureScenario;
use Tests\TestCase;

final class UpdateTourImageTest extends TestCase
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

    public function test_setting_a_new_cover_unmarks_the_previous_one(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $oldCover = TourImage::factory()->for($tour)->cover()->create();
        $other = TourImage::factory()->for($tour)->create(['is_cover' => false, 'display_order' => 2]);
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $response = $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/images/{$other->id}",
            ['is_cover' => true],
        );

        $response->assertSessionHas('success');
        $this->assertFalse((bool) $oldCover->fresh()?->is_cover);
        $this->assertTrue((bool) $other->fresh()?->is_cover);
    }

    public function test_updating_an_image_of_another_tour_returns_404(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tourA = Tour::factory()->create();
        $tourB = Tour::factory()->create();
        $imageB = TourImage::factory()->for($tourB)->create();
        $admin = $this->memberFor($tenant, UserRole::Admin);

        $this->actingAs($admin)->patch(
            $this->host($tenant)."/admin/tours/{$tourA->id}/images/{$imageB->id}",
            ['is_cover' => true],
        )->assertNotFound();
    }

    public function test_a_sales_member_cannot_change_the_cover(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create();
        $image = TourImage::factory()->for($tour)->create(['is_cover' => false]);
        $sales = $this->memberFor($tenant, UserRole::Sales);

        $this->actingAs($sales)->patch(
            $this->host($tenant)."/admin/tours/{$tour->id}/images/{$image->id}",
            ['is_cover' => true],
        )->assertForbidden();
    }
}
