<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Newsletter;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mismos campos y mismas reglas que la campaña real: una prueba que acepte lo
 * que el envío rechaza no sirve como prueba.
 */
final class SendCampaignTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('newsletter.send') ?? false;
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
