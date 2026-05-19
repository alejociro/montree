<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\Catalog\CatalogTourResource;
use App\Http\Resources\Catalog\PublicTourResource;
use App\Models\Favorite;
use App\Models\Tour;
use App\Services\Catalog\TourDetailResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicTourPageController extends Controller
{
    public function __construct(private TourDetailResolver $resolver) {}

    public function show(Request $request, string $slug): Response
    {
        $tour = $this->resolver->bySlug($slug);

        if ($tour === null) {
            throw new NotFoundHttpException('Tour not found.');
        }

        $userId = $request->user()?->id;
        if ($userId !== null) {
            $isFavorite = Favorite::query()
                ->where('user_id', $userId)
                ->where('tour_id', $tour->id)
                ->exists();
            $tour->setAttribute('is_favorite', $isFavorite);
        }

        return Inertia::render('TourDetail', [
            'tour' => (new PublicTourResource($tour))->resolve($request),
            'relatedTours' => Inertia::defer(fn () => $this->relatedTours($tour)),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relatedTours(Tour $tour): array
    {
        $query = Tour::query()
            ->active()
            ->with(['category', 'coverImage'])
            ->where('id', '!=', $tour->id);

        if ($tour->category_id !== null) {
            $query->where('category_id', $tour->category_id);
        }

        $tours = $query->inRandomOrder()->limit(4)->get();

        if ($tours->count() < 4) {
            $existingIds = $tours->pluck('id')->push($tour->id);
            $extras = Tour::query()
                ->active()
                ->with(['category', 'coverImage'])
                ->whereNotIn('id', $existingIds)
                ->inRandomOrder()
                ->limit(4 - $tours->count())
                ->get();

            $tours = $tours->merge($extras);
        }

        return CatalogTourResource::collection($tours)->resolve();
    }
}
