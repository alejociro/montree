<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

final class UpdateHotelRequest extends LogisticsFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return LogisticsRules::forUpdate(LogisticsRules::hotel());
    }
}
