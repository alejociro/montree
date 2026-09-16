<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Logistics\DeleteHotelAction;
use App\Actions\Logistics\SaveHotelAction;
use App\Exceptions\LogisticsRecordInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreHotelRequest;
use App\Http\Requests\Admin\Logistics\UpdateHotelRequest;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;

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

    public function destroy(Hotel $hotel, DeleteHotelAction $deleteHotel): RedirectResponse
    {
        try {
            $deleteHotel->execute($hotel);
        } catch (LogisticsRecordInUseException $inUse) {
            return back()->withErrors(['hotel' => $inUse->getMessage()]);
        }

        return back()->with('success', __('Hotel eliminado.'));
    }
}
