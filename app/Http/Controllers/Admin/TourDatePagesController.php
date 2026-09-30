<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\TourDate\CreateTourDateAction;
use App\Actions\TourDate\DeleteTourDateAction;
use App\Actions\TourDate\UpdateTourDateAction;
use App\Exceptions\TourDateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\StoreTourDateRequest;
use App\Http\Requests\Admin\TourDate\UpdateTourDateRequest;
use App\Models\Tour;
use App\Models\TourDate;
use App\Support\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TourDatePagesController extends Controller
{
    public function store(StoreTourDateRequest $request, Tour $tour, CreateTourDateAction $createTourDate): RedirectResponse
    {
        $createTourDate->handle($tour, $request->validated());

        return $this->redirectAfterSave($request, __('Salida creada.'));
    }

    public function update(UpdateTourDateRequest $request, TourDate $tourDate, UpdateTourDateAction $updateTourDate): RedirectResponse
    {
        try {
            $updateTourDate->handle($tourDate, $request->validated());
        } catch (TourDateException $blocked) {
            return back()->withErrors(['tour_date' => $blocked->getMessage()]);
        }

        return $this->redirectAfterSave($request, __('Salida actualizada.'));
    }

    /**
     * T9: la vista paso a paso ya no es la página desde la que se vino —el
     * `Referer` que usaría `back()` apunta al propio formulario— así que
     * manda a dónde volver en `return`. Solo se acepta una ruta interna; sin
     * ese dato (por ejemplo, si algo más sigue llamando este endpoint) se cae
     * al comportamiento de siempre.
     */
    private function redirectAfterSave(StoreTourDateRequest|UpdateTourDateRequest $request, string $message): RedirectResponse
    {
        $return = SafeRedirect::internalPath($request->input('return'));

        if ($return !== null) {
            return redirect($return)->with('success', $message);
        }

        return back()->with('success', $message);
    }

    public function destroy(TourDate $tourDate, DeleteTourDateAction $deleteTourDate): RedirectResponse
    {
        Gate::authorize('delete', $tourDate);

        try {
            $deleteTourDate->handle($tourDate);
        } catch (TourDateException $blocked) {
            return back()->withErrors(['tour_date' => $blocked->getMessage()]);
        }

        return back()->with('success', __('Salida eliminada.'));
    }
}
