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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TourDatePagesController extends Controller
{
    public function store(StoreTourDateRequest $request, Tour $tour, CreateTourDateAction $createTourDate): RedirectResponse
    {
        $createTourDate->handle($tour, $request->validated());

        return back()->with('success', __('Salida creada.'));
    }

    public function update(UpdateTourDateRequest $request, TourDate $tourDate, UpdateTourDateAction $updateTourDate): RedirectResponse
    {
        try {
            $updateTourDate->handle($tourDate, $request->validated());
        } catch (TourDateException $blocked) {
            return back()->withErrors(['tour_date' => $blocked->getMessage()]);
        }

        return back()->with('success', __('Salida actualizada.'));
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
