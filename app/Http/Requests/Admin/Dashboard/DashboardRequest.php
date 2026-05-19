<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Dashboard;

use App\Policies\DashboardPolicy;
use App\Services\Dashboard\PeriodFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return (new DashboardPolicy)->view($user);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(PeriodFilter::SUPPORTED_KEYS)],
            'tz' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }

    public function periodKey(): string
    {
        return (string) ($this->validated('period') ?? PeriodFilter::KEY_LAST_30_DAYS);
    }

    public function timezone(string $fallback): string
    {
        return (string) ($this->validated('tz') ?? $fallback);
    }
}
