<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Passengers\ExportPassengerManifestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\PassengerManifestRequest;
use App\Http\Resources\Passenger\PassengerResource;
use App\Models\Tour;
use App\Queries\PassengerManifestQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TourPassengerExportController extends Controller
{
    public function __construct(
        private PassengerManifestQuery $query,
        private ExportPassengerManifestAction $export,
    ) {}

    public function __invoke(PassengerManifestRequest $request, Tour $tour): StreamedResponse
    {
        $departures = $tour->dates()->orderBy('starts_at')->get();
        $manifest = $this->query->handle($departures, $request->tourDateId(), $request->filters($tour->currency));

        return $this->export->handle($manifest->rows, $tour->slug, PassengerResource::canViewMedical($request));
    }
}
