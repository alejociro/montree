<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\TourDate\CancelTourDateAction;
use App\Exceptions\TourDateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\CancelTourDateRequest;
use App\Models\TourDate;
use Illuminate\Http\RedirectResponse;

final class CancelTourDateController extends Controller
{
    public function __invoke(CancelTourDateRequest $request, TourDate $tourDate, CancelTourDateAction $cancel): RedirectResponse
    {
        try {
            $cancel->handle($tourDate, $request->validated('reason'));
        } catch (TourDateException $blocked) {
            return back()->withErrors(['tour_date' => $blocked->getMessage()]);
        }

        return back()->with('success', __('Salida cancelada.'));
    }
}
