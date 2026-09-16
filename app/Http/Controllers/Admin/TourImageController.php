<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tour\AttachTourImageAction;
use App\Actions\Tour\DetachTourImageAction;
use App\Actions\Tour\UpdateTourImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tour\StoreTourImageRequest;
use App\Http\Requests\Admin\Tour\UpdateTourImageRequest;
use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TourImageController extends Controller
{
    public function store(StoreTourImageRequest $request, Tour $tour, AttachTourImageAction $attachImage): RedirectResponse
    {
        $attachImage->handle(
            $tour,
            $request->file('image'),
            $request->boolean('is_cover'),
            $request->input('alt_text'),
        );

        return back()->with('success', __('Imagen agregada.'));
    }

    public function update(UpdateTourImageRequest $request, Tour $tour, TourImage $image, UpdateTourImageAction $updateImage): RedirectResponse
    {
        abort_if($image->tour_id !== $tour->id, 404);

        $updateImage->handle($image, $request->validated());

        return back()->with('success', __('Imagen actualizada.'));
    }

    public function destroy(Tour $tour, TourImage $image, DetachTourImageAction $detachImage): RedirectResponse
    {
        Gate::authorize('manageImages', $tour);

        abort_if($image->tour_id !== $tour->id, 404);

        $detachImage->handle($image);

        return back()->with('success', __('Imagen eliminada.'));
    }
}
