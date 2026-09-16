<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

abstract class LogisticsFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('logistics.manage') ?? false;
    }

    /**
     * La ficha se tarifa en la moneda de la agencia, que es la única que la
     * agencia opera (spec §H). El formulario ya la propone; esto cubre a quien
     * manda la ficha sin ella.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('currency') && filled($this->input('currency'))) {
            return;
        }

        $currency = Tenant::current()?->configuration?->currency;

        if ($currency !== null) {
            $this->merge(['currency' => $currency]);
        }
    }
}
