<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Tour;

use App\Models\Tenant;
use App\Models\Tour;
use App\Services\Tour\TourSlugGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourSlugGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        parent::tearDown();
    }

    public function test_generates_simple_slug_when_no_collision(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();
        $generator = new TourSlugGenerator;

        $slug = $generator->generate('Camino de Cocora');

        $this->assertSame('camino-de-cocora', $slug);
    }

    public function test_appends_numeric_suffix_when_slug_collides(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();
        Tour::factory()->create(['slug' => 'sendero-quindio']);
        Tour::factory()->create(['slug' => 'sendero-quindio-2']);
        $generator = new TourSlugGenerator;

        $slug = $generator->generate('Sendero Quindío');

        $this->assertSame('sendero-quindio-3', $slug);
    }

    public function test_excludes_current_tour_when_updating(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();
        $tour = Tour::factory()->create(['slug' => 'mi-tour']);
        $generator = new TourSlugGenerator;

        $slug = $generator->generate('Mi Tour', $tour->id);

        $this->assertSame('mi-tour', $slug);
    }

    public function test_falls_back_to_tour_when_name_yields_empty_slug(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->makeCurrent();
        $generator = new TourSlugGenerator;

        $slug = $generator->generate('!!!');

        $this->assertSame('tour', $slug);
    }
}
