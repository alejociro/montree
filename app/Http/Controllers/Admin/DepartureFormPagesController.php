<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Models\Tour;
use App\Models\TourDate;
use App\Queries\DepartureOptionsQuery;
use App\Queries\DepartureTourOptionsQuery;
use App\Support\SafeRedirect;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * T9: la vista paso a paso que reemplaza el modal `TourDateFormDialog`.
 *
 * Sirve las tres entradas (producto preseleccionado desde el tour, elegir
 * producto desde el tablero, editar una salida existente) con la misma
 * página Inertia (`Admin/Departures/Form`); el guardado sigue yendo contra
 * `TourDatePagesController@store`/`@update`, que ya validan y persisten.
 */
final class DepartureFormPagesController extends Controller
{
    public function __construct(
        private DepartureTourOptionsQuery $tourOptions,
        private DepartureOptionsQuery $options,
    ) {}

    public function createForTour(Request $request, Tour $tour): Response
    {
        return Inertia::render('Admin/Departures/Form', [
            'mode' => 'create',
            'tourDate' => null,
            'tours' => [$this->tourOptions->forTour($tour)],
            'preselectedTourId' => $tour->id,
            'departureOptions' => $this->options->all(),
            'returnUrl' => $this->safeReturn($request, "/admin/tours/{$tour->id}/edit?tab=departures"),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Departures/Form', [
            'mode' => 'create',
            'tourDate' => null,
            'tours' => $this->tourOptions->all(),
            'preselectedTourId' => null,
            'departureOptions' => $this->options->all(),
            'returnUrl' => $this->safeReturn($request, '/admin/departures'),
        ]);
    }

    public function edit(Request $request, TourDate $tourDate): Response
    {
        $tourDate->load(['tour', 'guide', 'route', 'provider', 'hotels']);

        return Inertia::render('Admin/Departures/Form', [
            'mode' => 'edit',
            'tourDate' => TourDateDetailResource::make($tourDate)->resolve(),
            'tours' => [$this->tourOptions->forTour($tourDate->tour)],
            'preselectedTourId' => $tourDate->tour_id,
            'departureOptions' => $this->options->all(),
            'returnUrl' => $this->safeReturn($request, '/admin/departures'),
        ]);
    }

    /**
     * `return`/`from` solo se acepta cuando es una ruta interna (empieza por
     * `/` y no es protocol-relative ni absoluta): un valor externo aquí sería
     * una redirección abierta tras guardar.
     */
    private function safeReturn(Request $request, string $default): string
    {
        return SafeRedirect::internalPath($request->query('return', $request->query('from'))) ?? $default;
    }
}
