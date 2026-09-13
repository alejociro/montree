<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

final class UpdateProviderRequest extends LogisticsFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return LogisticsRules::forUpdate(LogisticsRules::provider());
    }
}
