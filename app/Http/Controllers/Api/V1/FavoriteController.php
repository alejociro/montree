<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Favorite\ToggleFavoriteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Favorite\ToggleFavoriteRequest;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;

final class FavoriteController extends Controller
{
    public function __construct(private ToggleFavoriteAction $toggle) {}

    public function store(ToggleFavoriteRequest $request): JsonResponse
    {
        $tour = Tour::query()->findOrFail((int) $request->validated('tour_id'));
        $isFavorite = $this->toggle->handle($request->user(), $tour);

        return new JsonResponse([
            'data' => [
                'tour_id' => $tour->id,
                'is_favorite' => $isFavorite,
            ],
        ]);
    }
}
