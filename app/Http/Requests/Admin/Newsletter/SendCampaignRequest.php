<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

final class SendCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'body_html' => ['required', 'string', 'max:50000'],
            'preview_text' => ['nullable', 'string', 'max:200'],
        ];
    }
}
