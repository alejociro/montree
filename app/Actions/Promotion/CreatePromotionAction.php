<?php

declare(strict_types=1);

namespace App\Actions\Promotion;

use App\Exceptions\PromotionCodeTakenException;
use App\Models\Promotion;
use Illuminate\Support\Str;

final class CreatePromotionAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Promotion
    {
        $payload = $this->normalize($data);

        if (Promotion::query()->where('code', $payload['code'])->exists()) {
            throw new PromotionCodeTakenException;
        }

        return Promotion::query()->create($payload);
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
