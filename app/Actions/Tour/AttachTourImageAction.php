<?php

declare(strict_types=1);

namespace App\Actions\Tour;

use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AttachTourImageAction
{
    public function handle(Tour $tour, UploadedFile $file, bool $isCover = false, ?string $altText = null): TourImage
    {
        $path = $this->storeFile($tour, $file);

        return DB::transaction(function () use ($tour, $path, $isCover, $altText): TourImage {
            $hasExisting = $tour->images()->exists();
            $shouldBeCover = $isCover || ! $hasExisting;

            if ($shouldBeCover) {
                $tour->images()->where('is_cover', true)->update(['is_cover' => false]);
            }

            $maxOrder = (int) $tour->images()->max('display_order');

            return $tour->images()->create([
                'path' => $path,
                'alt_text' => $altText,
                'display_order' => $maxOrder + 1,
                'is_cover' => $shouldBeCover,
            ]);
        });
    }

    private function storeFile(Tour $tour, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename = (string) Str::ulid().'.'.$extension;

        return $file->storeAs('tours/'.$tour->id, $filename, 'public');
    }

    public static function deleteStoredFile(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
