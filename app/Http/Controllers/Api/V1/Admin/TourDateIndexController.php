<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\TourDateIndexRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Models\TourDate;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TourDateIndexController extends Controller
{
    private const RELATIONS = ['tour', 'guide', 'route', 'provider', 'hotels'];

    public function __invoke(TourDateIndexRequest $request): AnonymousResourceCollection
    {
        $dates = TourDate::query()
            ->with(self::RELATIONS)
            ->when($request->displayStatus(), fn (Builder $query, $status) => $query->withDisplayStatus($status))
            ->when($request->integer('tour_id'), fn (Builder $query, int $tourId) => $query->where('tour_id', $tourId))
            ->when($request->date('from'), fn (Builder $query, $from) => $query->where('starts_at', '>=', $from))
            ->when($request->date('to'), fn (Builder $query, $to) => $query->where('starts_at', '<=', $to))
            ->orderBy('starts_at', $request->sortDirection())
            ->paginate($request->perPage())
            ->withQueryString();

        return TourDateDetailResource::collection($dates);
    }
}
