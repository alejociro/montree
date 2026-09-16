<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Team\AssignGuideAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\AssignGuideRequest;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class AssignGuideController extends Controller
{
    public function __invoke(AssignGuideRequest $request, TourDate $tourDate, AssignGuideAction $assign): RedirectResponse
    {
        $assign->handle($tourDate, User::query()->findOrFail((int) $request->validated('guide_id')));

        return back()->with('success', __('Guía asignado.'));
    }
}
