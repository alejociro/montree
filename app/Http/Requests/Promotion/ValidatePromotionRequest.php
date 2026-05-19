<?php

declare(strict_types=1);

namespace App\Http\Requests\Promotion;

use App\Models\TourDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'tour_date_id' => ['required', 'integer', Rule::exists((new TourDate)->getTable(), 'id')],
            'subtotal' => ['required', 'numeric', 'gt:0', 'max:9999999.99'],
        ];
    }
}
