<?php

declare(strict_types=1);

namespace App\Actions\Logistics;

use App\Models\Hotel;
use Illuminate\Support\Facades\DB;

/**
 * Guarda un hotel y, si vinieron, sus tipos de habitación con la tarifa
 * negociada por noche.
 */
final class SaveHotelAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Hotel $hotel, array $data): Hotel
    {
        $rooms = $data['rooms'] ?? null;
        unset($data['rooms']);

        return DB::transaction(function () use ($hotel, $data, $rooms): Hotel {
            $hotel = $hotel === null ? Hotel::create($data) : tap($hotel)->update($data);

            if (is_array($rooms)) {
                $hotel->rooms()->delete();

                foreach (array_values($rooms) as $index => $room) {
                    $hotel->rooms()->create([
                        'position' => $index + 1,
                        'name' => (string) $room['name'],
                        'quantity' => $room['quantity'] ?? null,
                        'nightly_rate' => $room['nightly_rate'] ?? null,
                    ]);
                }
            }

            return $hotel->fresh(['rooms']) ?? $hotel;
        });
    }
}
