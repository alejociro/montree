<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\PublicTourResource;
use App\Models\Favorite;
use App\Services\Catalog\TourDetailResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicTourController extends Controller
{
    public function __construct(private TourDetailResolver $resolver) {}

    public function show(Request $request, string $slug): PublicTourResource
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

        return new PublicTourResource($tour);
    }
}
