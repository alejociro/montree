<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Dashboard;

use App\Policies\DashboardPolicy;
use Illuminate\Foundation\Http\FormRequest;

class AdminBookingIndexRequest extends FormRequest
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
            'attention_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function attentionOnly(): bool
    {
        return (bool) $this->boolean('attention_only');
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 10);
    }
}
