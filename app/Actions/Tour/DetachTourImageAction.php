<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Models\TourImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DetachTourImageAction
{
    public function handle(TourImage $image): void
    {
        DB::transaction(function () use ($image): void {
            $wasCover = $image->is_cover;
            $tour = $image->tour;
            $path = $image->path;

            $image->delete();

            if ($wasCover) {
                $next = $tour->images()->orderBy('display_order')->first();

                if ($next !== null) {
                    $next->update(['is_cover' => true]);
                }
            }

            Storage::disk('public')->delete($path);
        });
    }
}
