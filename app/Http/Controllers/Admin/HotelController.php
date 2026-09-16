<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Logistics\SaveHotelAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreHotelRequest;
use App\Http\Requests\Admin\Logistics\UpdateHotelRequest;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class HotelController extends Controller
{
    public function store(StoreHotelRequest $request, SaveHotelAction $saveHotel): RedirectResponse
    {
        $saveHotel->handle(null, $request->validated());

        return back()->with('success', __('Hotel creado.'));
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel, SaveHotelAction $saveHotel): RedirectResponse
    {
        $saveHotel->handle($hotel, $request->validated());

        return back()->with('success', __('Hotel actualizado.'));
    }

    public function destroy(Hotel $hotel): RedirectResponse
    {
        Gate::authorize('logistics.manage');

        $usage = $hotel->tourDates()->count();

        if ($usage > 0) {
            return back()->withErrors(['hotel' => trans_choice(
                '{1}No se puede eliminar: el hotel está en uso por :count salida.|[2,*]No se puede eliminar: el hotel está en uso por :count salidas.',
                $usage,
                ['count' => $usage],
            )]);
        }

        $hotel->delete();

        return back()->with('success', __('Hotel eliminado.'));
    }
}
