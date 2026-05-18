<?php

declare(strict_types=1);

namespace App\Actions\Promotion;

use App\Exceptions\PromotionCodeLockedException;
use App\Exceptions\PromotionCodeTakenException;
use App\Models\Promotion;
use Illuminate\Support\Str;

final class UpdatePromotionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Promotion $promotion, array $data): Promotion
    {
        $payload = $this->normalize($data);

        if (array_key_exists('code', $payload) && $payload['code'] !== $promotion->code) {
            if ($promotion->uses_count > 0) {
                throw new PromotionCodeLockedException;
            }

            $taken = Promotion::query()
                ->where('code', $payload['code'])
                ->where('id', '!=', $promotion->id)
                ->exists();

            if ($taken) {
                throw new PromotionCodeTakenException;
            }
        }

        $promotion->fill($payload);
        $promotion->save();

        return $promotion->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = Str::upper(trim($data['code']));
        }

        return $data;
    }
}
