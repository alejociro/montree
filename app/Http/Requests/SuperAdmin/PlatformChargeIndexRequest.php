<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class PlatformChargeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $this->user()?->can('manage-platform-tenant', $tenant) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function from(): ?CarbonImmutable
    {
        $from = $this->validated('from');

        return $from === null ? null : CarbonImmutable::parse((string) $from)->startOfDay();
    }

    public function to(): ?CarbonImmutable
    {
        $to = $this->validated('to');

        return $to === null ? null : CarbonImmutable::parse((string) $to)->endOfDay();
    }

    /**
     * @return array{from: string|null, to: string|null}
     */
    public function filters(): array
    {
        return [
            'from' => $this->from()?->toDateString(),
            'to' => $this->to()?->toDateString(),
        ];
    }
}
