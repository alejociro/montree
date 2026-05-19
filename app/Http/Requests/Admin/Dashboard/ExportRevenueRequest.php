<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Dashboard;

use App\Policies\DashboardPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExportRevenueRequest extends FormRequest
{
    public const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return (new DashboardPolicy)->exportReports($user);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'group_by' => ['nullable', 'string', Rule::in(['day', 'week', 'month'])],
            'format' => ['nullable', 'string', Rule::in(['json', 'csv'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateRange($validator);
        });
    }

    public function fromDate(): Carbon
    {
        return Carbon::parse((string) $this->validated('from'))->startOfDay();
    }

    public function toDate(): Carbon
    {
        return Carbon::parse((string) $this->validated('to'))->endOfDay();
    }

    public function groupBy(): string
    {
        return (string) ($this->validated('group_by') ?? 'day');
    }

    public function exportFormat(): string
    {
        return (string) ($this->validated('format') ?? 'json');
    }

    private function validateRange(Validator $validator): void
    {
        $from = $this->input('from');
        $to = $this->input('to');

        if (! is_string($from) || ! is_string($to)) {
            return;
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($start->greaterThan($end)) {
            $validator->errors()->add('from', 'The from date must be before the to date.');

            return;
        }

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $validator->errors()->add('to', sprintf('The range cannot exceed %d days.', self::MAX_RANGE_DAYS));
        }
    }
}
