<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\TourDate\RestoreTourDateAction;
use App\Exceptions\TourDateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\RestoreTourDateRequest;
use App\Models\TourDate;
use Illuminate\Http\RedirectResponse;

final class RestoreTourDateController extends Controller
{
    public function __invoke(RestoreTourDateRequest $request, TourDate $tourDate, RestoreTourDateAction $restore): RedirectResponse
    {
        try {
            $restore->handle($tourDate);
        } catch (TourDateException $blocked) {
            return back()->withErrors(['tour_date' => $blocked->getMessage()]);
        }

        return back()->with('success', __('Salida rehabilitada.'));
    }
}
