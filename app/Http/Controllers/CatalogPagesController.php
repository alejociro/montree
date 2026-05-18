<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TourStatus;
use App\Http\Requests\Catalog\CatalogIndexRequest;
use App\Http\Resources\Catalog\CatalogCategoryResource;
use App\Http\Resources\Catalog\CatalogTourResource;
use App\Models\Category;
use App\Models\User;
use App\Services\Catalog\TourCatalogQuery;
use Inertia\Inertia;
use Inertia\Response;

final class CatalogPagesController extends Controller
{
    public function __construct(private TourCatalogQuery $catalogQuery) {}

    public function index(CatalogIndexRequest $request): Response
    {
        /** @var User|null $viewer */
        $viewer = $request->user();
        $filters = $request->filters();

        return Inertia::render('Catalog', [
            'filters' => $filters,
            'tours' => Inertia::defer(fn () => $this->paginatedTours($filters, $viewer)),
            'categories' => Inertia::defer(fn () => $this->categories()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function paginatedTours(array $filters, ?User $viewer): array
    {
        return CatalogTourResource::collection(
            $this->catalogQuery->paginate($filters, $viewer)
        )->response()->getData(assoc: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return CatalogCategoryResource::collection(
            Category::query()
                ->where('is_active', true)
                ->whereHas('tours', fn ($query) => $query->where('status', TourStatus::Active->value))
                ->withCount(['tours' => fn ($query) => $query->where('status', TourStatus::Active->value)])
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
        )->resolve();
    }
}
