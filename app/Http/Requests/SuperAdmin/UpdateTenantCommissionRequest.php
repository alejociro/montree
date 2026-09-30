<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Models\Tenant;
use App\Rules\ValidCommissionTiers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantCommissionRequest extends FormRequest
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
            'use_global' => ['required', 'boolean'],
            'tiers' => ['required_if:use_global,false', 'array', new ValidCommissionTiers],
            // WHY: máximo 2 decimales ANTES de redondear: si no, dos bordes que la
            // regla ve contiguos (499999.994 / 500000.006) quedan con un hueco de
            // un centavo tras `number_format` y esas reservas no generan cobro.
            'tiers.*.from' => ['numeric', 'min:0', 'decimal:0,2'],
            'tiers.*.to' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'tiers.*.rate' => ['numeric', 'min:0', 'max:100'],
            // WHY: tope único del ESQUEMA (no del rango). Si el porcentaje del
            // rango que aplica supera este valor, se cobra solo el tope.
            'max_charge' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }

    public function usesGlobal(): bool
    {
        return (bool) $this->validated('use_global');
    }

    /**
     * @return array<int, array{from: string, to: string|null, rate: string}>|null
     */
    public function tiers(): ?array
    {
        if ($this->usesGlobal()) {
            return null;
        }

        return array_map(
            static fn (array $tier): array => [
                'from' => number_format((float) $tier['from'], 2, '.', ''),
                'to' => $tier['to'] === null ? null : number_format((float) $tier['to'], 2, '.', ''),
                'rate' => number_format((float) $tier['rate'], 2, '.', ''),
            ],
            $this->validated('tiers'),
        );
    }

    public function maxCharge(): ?string
    {
        if ($this->usesGlobal()) {
            return null;
        }

        $maxCharge = $this->validated('max_charge');

        return $maxCharge === null ? null : number_format((float) $maxCharge, 2, '.', '');
    }
}
