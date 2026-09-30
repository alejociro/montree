<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Catalog;

use App\Enums\TourDateStatus;
use App\Http\Resources\Catalog\CatalogTourResource;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use App\Services\Catalog\TourCatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourCatalogQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_each_filter_narrows_the_result_set(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $hiking = Category::factory()->create(['slug' => 'hiking']);
        Category::factory()->create(['slug' => 'diving']);

        Tour::factory()->active()->create([
            'name' => 'Cocora Trail',
            'category_id' => $hiking->id,
            'difficulty' => 'easy',
            'base_price' => '90000.00',
        ]);
        Tour::factory()->active()->create([
            'name' => 'Tayrona Dive',
            'difficulty' => 'hard',
            'base_price' => '500000.00',
        ]);

        $query = new TourCatalogQuery;

        $byCategory = $query->paginate(['category' => 'hiking']);
        $this->assertCount(1, $byCategory->items());
        $this->assertSame('Cocora Trail', $byCategory->items()[0]->name);

        $bySearch = $query->paginate(['search' => 'Tayrona']);
        $this->assertCount(1, $bySearch->items());
        $this->assertSame('Tayrona Dive', $bySearch->items()[0]->name);

        $byDifficulty = $query->paginate(['difficulty' => 'easy']);
        $this->assertCount(1, $byDifficulty->items());
        $this->assertSame('Cocora Trail', $byDifficulty->items()[0]->name);

        $byPrice = $query->paginate(['price_min' => 100000, 'price_max' => 600000]);
        $this->assertCount(1, $byPrice->items());
        $this->assertSame('Tayrona Dive', $byPrice->items()[0]->name);
    }

    public function test_hydrates_is_favorite_when_viewer_has_favorites(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $viewer = User::factory()->create();
        $loved = Tour::factory()->active()->create();
        Tour::factory()->active()->create();
        Favorite::factory()->create(['user_id' => $viewer->id, 'tour_id' => $loved->id]);

        $page = (new TourCatalogQuery)->paginate([], $viewer);

        $byId = collect($page->items())->keyBy('id');
        $this->assertTrue($byId[$loved->id]->getAttribute('is_favorite'));
        $other = $byId->except([$loved->id])->first();
        $this->assertFalse($other->getAttribute('is_favorite'));
    }

    public function test_default_sort_pushes_tours_without_future_dates_to_the_end(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $withDate = Tour::factory()->active()->create(['name' => 'Has date']);
        TourDate::factory()->for($withDate)->create([
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Open,
        ]);
        Tour::factory()->active()->create(['name' => 'No date']);

        $page = (new TourCatalogQuery)->paginate([]);

        $items = array_map(static fn (Tour $tour) => $tour->name, $page->items());
        $this->assertSame(['Has date', 'No date'], $items);
    }

    public function test_from_price_is_minimum_effective_price_of_bookable_dates(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        $tour = Tour::factory()->active()->create(['base_price' => '100000.00']);

        // Reservable con override más barato que la base: manda.
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(3),
            'status' => TourDateStatus::Open,
            'price_override' => '70000.00',
        ]);

        // Reservable sin override: cuenta con el precio base.
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(10),
            'status' => TourDateStatus::Open,
            'price_override' => null,
        ]);

        // Cerrada para reservas: no cuenta aunque sea más barata.
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addDays(5),
            'status' => TourDateStatus::Open,
            'price_override' => '10000.00',
            'booking_closes_at' => now()->subHour(),
        ]);

        $page = (new TourCatalogQuery)->paginate([]);

        $resolved = (new CatalogTourResource($page->items()[0]))->resolve();
        $this->assertSame('70000.00', $resolved['from_price']);
    }

    public function test_from_price_falls_back_to_base_price_without_bookable_dates(): void
    {
        $tenant = $this->makeTenant();
        $tenant->makeCurrent();

        Tour::factory()->active()->create(['base_price' => '123456.00']);

        $page = (new TourCatalogQuery)->paginate([]);

        $resolved = (new CatalogTourResource($page->items()[0]))->resolve();
        $this->assertSame('123456.00', $resolved['from_price']);
    }

    /**
     * T12: la regla general de la agencia (`booking_advance_hours`) también
     * cierra la salida para el catálogo cuando no tiene su propio cierre.
     */
    public function test_from_price_and_next_date_respect_the_agency_advance_hours_rule(): void
    {
        $tenant = $this->makeTenant();
        $tenant->configuration->update(['booking_advance_hours' => 24]);
        $tenant->makeCurrent();

        $tour = Tour::factory()->active()->create(['base_price' => '100000.00']);

        // A 10h de la salida ya cerró (regla de 24h) aunque sea más barata.
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addHours(10),
            'status' => TourDateStatus::Open,
            'price_override' => '10000.00',
            'booking_closes_at' => null,
        ]);

        // A 30h todavía admite reservas.
        TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addHours(30),
            'status' => TourDateStatus::Open,
            'price_override' => '90000.00',
            'booking_closes_at' => null,
        ]);

        $page = (new TourCatalogQuery)->paginate([]);

        $resolved = (new CatalogTourResource($page->items()[0]))->resolve();
        $this->assertSame('90000.00', $resolved['from_price']);
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
}
