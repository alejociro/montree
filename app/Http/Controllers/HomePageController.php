<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Http\Resources\Catalog\CatalogTourResource;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class HomePageController extends Controller
{
    public function __invoke(): Response
    {
        if (Tenant::current() === null) {
            return Inertia::render('Landing');
        }

        return Inertia::render('Home', [
            'featuredTours' => Inertia::defer(fn () => $this->featuredTours()),
            'suggestedTours' => Inertia::defer(fn () => $this->suggestedTours()),
            'promotions' => Inertia::defer(fn () => $this->activePromotions()),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function featuredTours(): array
    {
        $tours = Tour::query()
            ->active()
            ->with(['category', 'coverImage'])
            ->withCount('bookings')
            ->orderByDesc('bookings_count')
            ->limit(4)
            ->get();

        return CatalogTourResource::collection($tours)->resolve();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function suggestedTours(): array
    {
        $featuredIds = Tour::query()
            ->active()
            ->withCount('bookings')
            ->orderByDesc('bookings_count')
            ->limit(4)
            ->pluck('id');

        $tours = Tour::query()
            ->active()
            ->with(['category', 'coverImage'])
            ->whereNotIn('id', $featuredIds)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        if ($tours->isEmpty()) {
            $tours = Tour::query()
                ->active()
                ->with(['category', 'coverImage'])
                ->inRandomOrder()
                ->limit(4)
                ->get();
        }

        return CatalogTourResource::collection($tours)->resolve();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activePromotions(): array
    {
        $now = Carbon::now();

        $promotions = Promotion::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->limit(3)
            ->get();

        $tourIds = $promotions
            ->pluck('applicable_tours')
            ->flatten()
            ->unique()
            ->filter()
            ->values();

        $tours = $tourIds->isNotEmpty()
            ? Tour::query()
                ->whereIn('id', $tourIds)
                ->with('coverImage')
                ->get()
                ->keyBy('id')
            : collect();

        return $promotions->map(function (Promotion $promo) use ($tours) {
            $firstTourId = $promo->applicable_tours[0] ?? null;
            $tour = $firstTourId !== null ? $tours->get($firstTourId) : null;

            $discountLabel = $promo->type === PromotionType::Percentage
                ? (int) $promo->value.'%'
                : '$'.number_format((float) $promo->value, 0);

            return [
                'id' => $promo->id,
                'name' => $promo->name,
                'description' => $promo->description,
                'discount_label' => $discountLabel,
                'cover_image_url' => $tour?->coverImage !== null
                    ? (str_starts_with((string) $tour->coverImage->path, 'http')
                        ? $tour->coverImage->path
                        : Storage::disk('public')->url($tour->coverImage->path))
                    : null,
                'tour' => $tour ? [
                    'slug' => $tour->slug,
                    'name' => $tour->name,
                ] : null,
            ];
        })->all();
    }
}
