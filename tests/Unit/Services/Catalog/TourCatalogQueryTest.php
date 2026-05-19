<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Catalog;

use App\Enums\TourDateStatus;
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
