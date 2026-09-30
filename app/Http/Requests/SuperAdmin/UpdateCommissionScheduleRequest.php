<?php

declare(strict_types=1);

namespace App\Http\Requests\SuperAdmin;

use App\Rules\ValidCommissionTiers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tiers' => ['required', 'array', new ValidCommissionTiers],
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

    /**
     * @return array<int, array{from: string, to: string|null, rate: string}>
     */
    public function tiers(): array
    {
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
        $maxCharge = $this->validated('max_charge');

        return $maxCharge === null ? null : number_format((float) $maxCharge, 2, '.', '');
    }
}
