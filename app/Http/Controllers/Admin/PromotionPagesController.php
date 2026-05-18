<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tour\TourSummaryResource;
use App\Models\Promotion;
use App\Models\Tour;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PromotionPagesController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Promotion::class);

        return Inertia::render('Admin/Promotion/Index', [
            'tours' => $this->tours(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tours(): array
    {
        return TourSummaryResource::collection(
            Tour::query()->orderBy('name')->get(),
        )->resolve();
    }
}
