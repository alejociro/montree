<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tour\CategoryResource;
use App\Http\Resources\Tour\TourResource;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TourPagesController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Tour::class);

        return Inertia::render('Admin/Tour/Index', [
            'categories' => $this->categories(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Tour::class);

        return Inertia::render('Admin/Tour/Create', [
            'categories' => $this->categories(),
        ]);
    }

    public function edit(Tour $tour): Response
    {
        Gate::authorize('update', $tour);

        $tour->load(['category', 'images', 'itineraries']);

        return Inertia::render('Admin/Tour/Edit', [
            'tour' => (new TourResource($tour))->resolve(),
            'categories' => $this->categories(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function categories(): array
    {
        return CategoryResource::collection(
            Category::query()->orderBy('display_order')->orderBy('name')->get()
        )->resolve();
    }
}
