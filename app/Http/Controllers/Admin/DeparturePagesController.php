<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\DepartureIndexRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Models\TourDate;
use App\Queries\DepartureBoardQuery;
use App\Queries\DepartureOptionsQuery;
use App\Queries\DepartureTourOptionsQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final class DeparturePagesController extends Controller
{
    private const PER_PAGE = 15;

    private const RELATIONS = ['tour', 'guide', 'route', 'provider', 'hotels'];

    public function __construct(
        private DepartureBoardQuery $board,
        private DepartureTourOptionsQuery $tourOptions,
    ) {}

    public function index(DepartureIndexRequest $request, DepartureOptionsQuery $options): Response
    {
        return Inertia::render('Admin/Departures/Index', $this->props($request, $options));
    }

    /**
     * @return array<string, mixed>
     */
    private function props(DepartureIndexRequest $request, DepartureOptionsQuery $options): array
    {
        $filtered = $this->filtered($request);

        // WHY: los totales del pie salen del MISMO corte que la tabla pero de
        // toda la selección, así que se calculan antes de paginar.
        $totals = $this->board->totalsFor($filtered);

        return [
            'departures' => $this->paginated($filtered, $request),
            'filters' => $request->filters(),
            'stats' => $this->board->stats(),
            'counts' => $this->board->counts($request->searchTerm(), $request->tourId()),
            'totals' => $totals,
            'tours' => $this->tourOptions->all(),
            'departureOptions' => $options->all(),
        ];
    }

    /**
     * @param  Builder<TourDate>  $filtered
     * @return array<string, mixed>
     */
    private function paginated(Builder $filtered, DepartureIndexRequest $request): array
    {
        $departures = (clone $filtered)
            ->with(self::RELATIONS)
            ->orderBy('starts_at', $request->sortDirection())
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return TourDateDetailResource::collection($departures)->response()->getData(assoc: true);
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
}
