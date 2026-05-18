<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Promotion\CreatePromotionAction;
use App\Actions\Promotion\DeactivatePromotionAction;
use App\Actions\Promotion\UpdatePromotionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Promotion\StorePromotionRequest;
use App\Http\Requests\Admin\Promotion\UpdatePromotionRequest;
use App\Http\Resources\Promotion\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class PromotionController extends Controller
{
    public function __construct(
        private CreatePromotionAction $createAction,
        private UpdatePromotionAction $updateAction,
        private DeactivatePromotionAction $deactivateAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Promotion::class);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);
        $now = now();

        $promotions = Promotion::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where('code', 'like', $term)->orWhere('name', 'like', $term);
            })
            ->when($request->filled('status'), function ($query) use ($request, $now): void {
                $status = $request->string('status')->toString();
                match ($status) {
                    'active' => $query->where('is_active', true)
                        ->where(function ($q) use ($now): void {
                            $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                        }),
                    'inactive' => $query->where('is_active', false),
                    'expired' => $query->whereNotNull('ends_at')->where('ends_at', '<', $now),
                    default => null,
                };
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return PromotionResource::collection($promotions);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        Gate::authorize('view', $promotion);

        return new JsonResponse(['data' => (new PromotionResource($promotion))->resolve()]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = $this->createAction->handle($request->validated());

        return new JsonResponse(['data' => (new PromotionResource($promotion))->resolve()], 201);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion = $this->updateAction->handle($promotion, $request->validated());

        return new JsonResponse(['data' => (new PromotionResource($promotion))->resolve()]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        Gate::authorize('delete', $promotion);

        $this->deactivateAction->handle($promotion);

        return new JsonResponse(null, 204);
    }
}
