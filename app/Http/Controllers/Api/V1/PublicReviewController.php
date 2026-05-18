<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReviewStatus;
use App\Enums\TourStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Catalog\PublicReviewResource;
use App\Models\Tour;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicReviewController extends Controller
{
    public function index(string $slug)
    {
        $tour = Tour::query()->where('slug', $slug)->where('status', TourStatus::Active)->first();

        if ($tour === null) {
            throw new NotFoundHttpException('Tour not found.');
        }

        $reviews = $tour->reviews()
            ->with('user:id,name')
            ->where('status', ReviewStatus::Approved)
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->paginate(10);

        return PublicReviewResource::collection($reviews);
    }
}
