<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TourDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuideController extends Controller
{
    public function schedule(Request $request): JsonResponse
    {
        $dates = TourDate::query()
            ->where('guide_id', $request->user()->id)
            ->where('starts_at', '>=', now())
            ->with('tour:id,name,slug')
            ->orderBy('starts_at')
            ->get();

        return new JsonResponse([
            'data' => $dates->map(fn ($d) => [
                'id' => $d->id,
                'starts_at' => $d->starts_at->toIso8601String(),
                'ends_at' => $d->ends_at?->toIso8601String(),
                'capacity_total' => $d->capacity,
                'capacity_booked' => $d->booked_count,
                'tour' => [
                    'id' => $d->tour->id,
                    'name' => $d->tour->name,
                    'slug' => $d->tour->slug,
                ],
            ])->values(),
        ]);
    }
}
