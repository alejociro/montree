<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guide;

use App\Actions\Passengers\ExportPassengerManifestAction;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\PassengerManifestRequest;
use App\Http\Resources\Passenger\PassengerResource;
use App\Models\TourDate;
use App\Queries\PassengerManifestQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TourDatePassengerExportController extends Controller
{
    public function __construct(
        private PassengerManifestQuery $query,
        private ExportPassengerManifestAction $export,
    ) {}

    public function __invoke(PassengerManifestRequest $request, TourDate $tourDate): StreamedResponse
    {
        abort_if($tourDate->guide_id !== $request->user()->id, 403);

        $tourDate->load('tour:id,slug,currency');
        $filters = $request->filters($tourDate->tour->currency, [BookingStatus::Confirmed, BookingStatus::Completed]);
        $manifest = $this->query->handle(collect([$tourDate]), null, $filters);

        return $this->export->handle($manifest->rows, $tourDate->tour->slug, PassengerResource::canViewMedical($request));
    }
}
