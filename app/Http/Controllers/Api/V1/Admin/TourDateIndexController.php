<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\TourDateIndexRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Models\TourDate;
use App\Queries\DepartureBoardQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TourDateIndexController extends Controller
{
    private const RELATIONS = ['tour', 'guide', 'route', 'provider', 'hotels'];

    public function __construct(private DepartureBoardQuery $board) {}

    public function __invoke(TourDateIndexRequest $request): AnonymousResourceCollection
    {
        $search = $request->searchTerm();
        $tourId = $request->tourId();

        $filtered = TourDate::query()
            ->when($request->displayStatus(), fn (Builder $query, $status) => $query->withDisplayStatus($status))
            ->inScope($request->scope())
            ->when($search !== null, fn (Builder $query) => $query->matchingSearch((string) $search))
            ->when($tourId !== null, fn (Builder $query) => $query->where('tour_id', $tourId))
            ->when($request->date('from'), fn (Builder $query, $from) => $query->where('starts_at', '>=', $from))
            ->when($request->date('to'), fn (Builder $query, $to) => $query->where('starts_at', '<=', $to));

        // WHY: los totales del pie salen del MISMO corte que la tabla, pero de
        // toda la selección y no de la página visible; se calculan antes de
        // paginar para no arrastrar el `limit`.
        $totals = $this->board->totalsFor($filtered);

        $dates = (clone $filtered)
            ->with(self::RELATIONS)
            ->orderBy('starts_at', $request->sortDirection())
            ->paginate($request->perPage())
            ->withQueryString();

        return TourDateDetailResource::collection($dates)->additional([
            'stats' => $this->board->stats(),
            'counts' => $this->board->counts($search, $tourId),
            'totals' => $totals,
        ]);
    }
}
