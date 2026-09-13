<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Logistics\SaveProviderAction;
use App\Exceptions\LogisticsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreProviderRequest;
use App\Http\Requests\Admin\Logistics\UpdateProviderRequest;
use App\Http\Resources\Admin\ProviderResource;
use App\Models\Provider;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ProviderController extends Controller
{
    public function __construct(private SaveProviderAction $saveProvider) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('logistics.view');

        $providers = Provider::query()
            ->with(['rates', 'documents'])
            ->withCount('tourDates')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';

                $query->where(function (Builder $scoped) use ($term): void {
                    $scoped->where('name', 'like', $term)
                        ->orWhere('legal_name', 'like', $term)
                        ->orWhere('tax_id', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('contact_phone', 'like', $term)
                        ->orWhereHas('rates', fn (Builder $rates) => $rates->where('concept', 'like', $term));
                });
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 12), 1), 100))
            ->withQueryString();

        return ProviderResource::collection($providers);
    }

    public function store(StoreProviderRequest $request): JsonResponse
    {
        $provider = $this->saveProvider->handle(null, $request->validated());

        return new JsonResponse(['data' => (new ProviderResource($provider->loadCount('tourDates')))->resolve()], 201);
    }

    public function update(UpdateProviderRequest $request, Provider $provider): JsonResponse
    {
        $provider = $this->saveProvider->handle($provider, $request->validated());

        return new JsonResponse(['data' => (new ProviderResource($provider->loadCount('tourDates')))->resolve()]);
    }

    public function destroy(Provider $provider): JsonResponse
    {
        Gate::authorize('logistics.manage');

        $usage = $provider->tourDates()->count();

        if ($usage > 0) {
            throw LogisticsException::inUse('Proveedor', $usage);
        }

        $provider->delete();

        return new JsonResponse(null, 204);
    }
}
