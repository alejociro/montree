<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\DepartureDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\DepartureIndexRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Http\Resources\Admin\TourRouteResource;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Queries\DepartureBoardQuery;
use App\Queries\DepartureOptionsQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Inertia\Inertia;
use Inertia\Response;

final class DeparturePagesController extends Controller
{
    private const PER_PAGE = 15;

    private const RELATIONS = ['tour', 'guide', 'route', 'provider', 'hotels'];

    public function __construct(private DepartureBoardQuery $board) {}

    public function index(DepartureIndexRequest $request, DepartureOptionsQuery $options): Response
    {
        $filtered = $this->filtered($request);

        // WHY: los totales del pie salen del MISMO corte que la tabla pero de
        // toda la selección, así que se calculan antes de paginar.
        $totals = $this->board->totalsFor($filtered);

        $departures = (clone $filtered)
            ->with(self::RELATIONS)
            ->orderBy('starts_at', $request->sortDirection())
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Departures/Index', [
            'departures' => TourDateDetailResource::collection($departures)->response()->getData(assoc: true),
            'filters' => $request->filters(),
            'stats' => $this->board->stats(),
            'counts' => $this->board->counts($request->searchTerm(), $request->tourId()),
            'totals' => $totals,
            'tours' => $this->tours(),
            'departureOptions' => $options->all(),
        ]);
    }

    /**
     * @return Builder<TourDate>
     */
    private function filtered(DepartureIndexRequest $request): Builder
    {
        $search = $request->searchTerm();
        $tourId = $request->tourId();

        return TourDate::query()
            ->when($request->displayStatus(), fn (Builder $query, $status) => $query->withDisplayStatus($status))
            ->inScope($request->scope())
            ->when($search !== null, fn (Builder $query) => $query->matchingSearch((string) $search))
            ->when($tourId !== null, fn (Builder $query) => $query->where('tour_id', $tourId))
            ->when($request->from(), fn (Builder $query, $from) => $query->where('starts_at', '>=', $from))
            ->when($request->to(), fn (Builder $query, $to) => $query->where('starts_at', '<=', $to));
    }

    /**
     * Productos del selector, con lo que el diálogo de salida necesita heredar.
     *
     * WHY: los valores por defecto viajan embebidos en cada producto en vez de
     * resolverse al abrir el diálogo. Son seis escalares y la lista de rutas
     * —que el selector necesita igual— así que un `router.reload` por apertura
     * sería un viaje por nada.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tours(): array
    {
        $configuration = Tenant::current()?->configuration;

        return Tour::query()
            ->with(['routes' => fn (BelongsToMany $query) => $query->withCount('stops')])
            ->orderBy('name')
            ->get()
            ->map(fn (Tour $tour) => [
                'id' => $tour->id,
                'name' => $tour->name,
                'currency' => $tour->currency,
                // El diálogo deriva el fin de la salida con la duración del
                // producto; al crear desde el tablero no hay otra fuente.
                'duration_hours' => $tour->duration_hours,
                'routes' => TourRouteResource::collection($tour->routes)->resolve(),
                'departure_defaults' => $this->defaultsFor($tour, $configuration),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsFor(Tour $tour, ?TenantConfiguration $configuration): array
    {
        return DepartureDefaults::fromTour($tour, $configuration)->toArray();
    }
}
