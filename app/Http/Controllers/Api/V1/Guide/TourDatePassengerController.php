<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guide;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Passenger\PassengerManifestRequest;
use App\Http\Resources\Passenger\PassengerManifestResource;
use App\Models\TourDate;
use App\Queries\PassengerManifestQuery;

/**
 * La planilla de una salida, para el guía que la lleva.
 *
 * WHY: el alcance es pertenencia, no permiso —`guide.travelers.view` lo tiene
 * también el admin, que se lleva el catálogo completo—, así que la puerta es
 * `guide_id === auth()->id()` y vive aquí, no en una Policy (plan.md §D1).
 */
final class TourDatePassengerController extends Controller
{
    public function __construct(private PassengerManifestQuery $query) {}

    public function index(PassengerManifestRequest $request, TourDate $tourDate): PassengerManifestResource
    {
        abort_if($tourDate->guide_id !== $request->user()->id, 403);

        $departures = collect([$tourDate->load(['guide:id,name', 'tour:id,currency'])]);
        $filters = $request->filters($tourDate->tour->currency, [BookingStatus::Confirmed, BookingStatus::Completed]);
        $manifest = $this->query->handle($departures, null, $filters);

        return new PassengerManifestResource($manifest, $departures, $filters->perPage, $request->page());
    }
}
