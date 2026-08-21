<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\PassengerManifestRequest;
use App\Http\Resources\Passenger\PassengerManifestResource;
use App\Models\Tour;
use App\Queries\PassengerManifestQuery;

final class TourPassengerController extends Controller
{
    public function __construct(private PassengerManifestQuery $query) {}

    public function index(PassengerManifestRequest $request, Tour $tour): PassengerManifestResource
    {
        $departures = $tour->dates()->with('guide:id,name')->orderBy('starts_at')->get();
        $filters = $request->filters($tour->currency);
        $manifest = $this->query->handle($departures, $request->tourDateId(), $filters);

        return new PassengerManifestResource($manifest, $departures, $filters->perPage, $request->page());
    }
}
