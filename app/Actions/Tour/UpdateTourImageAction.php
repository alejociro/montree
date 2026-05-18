<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Models\TourImage;
use Illuminate\Support\Facades\DB;

final class UpdateTourImageAction
{
    /**
     * @param  array{is_cover?: bool, display_order?: int, alt_text?: ?string}  $data
     */
    public function handle(TourImage $image, array $data): TourImage
    {
        return DB::transaction(function () use ($image, $data): TourImage {
            if (array_key_exists('is_cover', $data) && (bool) $data['is_cover'] === true) {
                $image->tour->images()
                    ->where('id', '!=', $image->id)
                    ->where('is_cover', true)
                    ->update(['is_cover' => false]);
            }

            $image->fill($data);
            $image->save();

            return $image->fresh() ?? $image;
        });
    }
}
