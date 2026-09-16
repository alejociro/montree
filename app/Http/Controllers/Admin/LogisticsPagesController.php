<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\LogisticsIndexRequest;
use App\Http\Resources\Admin\HotelResource;
use App\Http\Resources\Admin\ProviderResource;
use App\Http\Resources\Admin\RouteResource;
use App\Models\Hotel;
use App\Models\Provider;
use App\Models\Route;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los tres catálogos viajan juntos aunque solo uno esté visible: la pestaña
 * lleva su conteo, y un contador que solo aparece al abrir la bandeja no sirve
 * para decidir a cuál ir.
 */
final class LogisticsPagesController extends Controller
{
    private const PER_PAGE = 12;

    public function index(LogisticsIndexRequest $request): Response
    {
        return Inertia::render('Admin/Logistics/Index', $this->props($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function props(LogisticsIndexRequest $request): array
    {
        $search = $request->search();

        return [
            'routes' => $this->paginate(
                Route::query()->with('stops')->withCount(['tourDates', 'tours'])->matching($search),
                RouteResource::class,
                'routes_page',
            ),
            'providers' => $this->paginate(
                Provider::query()->with(['rates', 'documents'])->withCount('tourDates')->matching($search),
                ProviderResource::class,
                'providers_page',
            ),
            'hotels' => $this->paginate(
                Hotel::query()->with('rooms')->withCount('tourDates')->matching($search),
                HotelResource::class,
                'hotels_page',
            ),
            'filters' => $request->filters(),
        ];
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  class-string<JsonResource>  $resource
     * @return array<string, mixed>
     */
    private function paginate(Builder $query, string $resource, string $pageName): array
    {
        $records = $query
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], $pageName)
            ->withQueryString();

        return $resource::collection($records)->response()->getData(assoc: true);
    }
}
