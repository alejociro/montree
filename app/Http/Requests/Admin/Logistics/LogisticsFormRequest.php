<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Logistics;

use Illuminate\Foundation\Http\FormRequest;

abstract class LogisticsFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('logistics.manage') ?? false;
    }
}
