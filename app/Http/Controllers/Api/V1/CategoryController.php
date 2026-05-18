<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TourStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\CatalogCategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

final class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('tours', fn ($query) => $query->where('status', TourStatus::Active->value))
            ->withCount(['tours' => fn ($query) => $query->where('status', TourStatus::Active->value)])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return new JsonResponse([
            'data' => CatalogCategoryResource::collection($categories)->resolve(),
        ]);
    }
}
