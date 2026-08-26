<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

final class StoreProviderRequest extends LogisticsFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return LogisticsRules::provider();
    }
}
