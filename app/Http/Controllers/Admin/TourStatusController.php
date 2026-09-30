<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tour\ChangeTourStatusAction;
use App\Exceptions\InvalidTourStatusTransitionException;
use App\Exceptions\TourHasActiveBookingsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tour\ChangeTourStatusRequest;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;

final class TourStatusController extends Controller
{
    public function __invoke(ChangeTourStatusRequest $request, Tour $tour, ChangeTourStatusAction $changeStatus): RedirectResponse
    {
        try {
            $changeStatus->handle($tour, $request->nextStatus());
        } catch (InvalidTourStatusTransitionException|TourHasActiveBookingsException $blocked) {
            // WHY: el 409/403 JSON del dominio sería una página de error en una
            // visita Inertia. El motivo del rechazo tiene que volver a la misma
            // pantalla, que es donde está el botón.
            return back()->withErrors(['status' => $blocked->getMessage()]);
        }

        return back()->with('success', __('Estado del tour actualizado.'));
    }
}
