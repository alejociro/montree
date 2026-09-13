<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Transaction;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\TransactionSearchField;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class TransactionIndexRequest extends FormRequest
{
    /** Ventana por defecto al entrar sin filtros. */
    public const DEFAULT_DAYS = 30;

    public function authorize(): bool
    {
        return $this->user()?->can('payments.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search_by' => ['sometimes', 'nullable', Rule::enum(TransactionSearchField::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:64', 'required_with:search_by'],
            'status' => ['sometimes', 'nullable', Rule::enum(PaymentStatus::class)],
            'gateway' => ['sometimes', 'nullable', Rule::enum(PaymentGateway::class)],
            'tour_date_id' => ['sometimes', 'nullable', 'integer', $this->tourDateRule()],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.now()->addDay()->toDateString()],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        $search = trim((string) ($this->validated('search') ?? ''));

        return $search === '' ? null : $search;
    }

    public function searchField(): ?TransactionSearchField
    {
        $field = (string) ($this->validated('search_by') ?? '');

        if ($field === '' || $this->search() === null) {
            return null;
        }

        return TransactionSearchField::from($field);
    }

    /**
     * Pegar un identificador exacto busca en toda la historia; el rango solo
     * rige cuando se está explorando. Ver [[TransactionSearchField]].
     */
    private function ignoresDateRange(): bool
    {
        return $this->searchField()?->ignoresDateRange() ?? false;
    }

    public function status(): ?PaymentStatus
    {
        $status = (string) ($this->validated('status') ?? '');

        return $status === '' ? null : PaymentStatus::from($status);
    }

    public function gateway(): ?PaymentGateway
    {
        $gateway = (string) ($this->validated('gateway') ?? '');

        return $gateway === '' ? null : PaymentGateway::from($gateway);
    }

    public function tourDateId(): ?int
    {
        $tourDateId = $this->validated('tour_date_id');

        return $tourDateId === null ? null : (int) $tourDateId;
    }

    /**
     * Sin rango elegido se muestran los últimos DEFAULT_DAYS días: entrar al
     * listado no puede significar barrer la tabla entera.
     */
    public function from(): ?CarbonInterface
    {
        if ($this->ignoresDateRange()) {
            return null;
        }

        $from = (string) ($this->validated('from') ?? '');

        if ($from !== '') {
            return Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
        }

        return $this->validated('to') ? null : now()->subDays(self::DEFAULT_DAYS)->startOfDay();
    }

    public function to(): ?CarbonInterface
    {
        if ($this->ignoresDateRange()) {
            return null;
        }

        $to = (string) ($this->validated('to') ?? '');

        return $to === '' ? null : Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
    }

    /**
     * Lo aplicado, tal cual vuelve a la page para repintar los controles.
     *
     * @return array{search: string|null, search_by: string|null, status: string|null, gateway: string|null, tour_date_id: int|null, from: string|null, to: string|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->search(),
            'search_by' => $this->searchField()?->value,
            'status' => $this->status()?->value,
            'gateway' => $this->gateway()?->value,
            'tour_date_id' => $this->tourDateId(),
            'from' => $this->from()?->toDateString(),
            'to' => $this->to()?->toDateString(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tour_date_id.exists' => __('Esa salida no existe en tu agencia.'),
            'to.after_or_equal' => __('La fecha final no puede ser anterior a la inicial.'),
            'to.before_or_equal' => __('La fecha final no puede estar en el futuro.'),
            'search.required_with' => __('Escribí qué querés buscar.'),
        ];
    }

    private function tourDateRule(): Exists
    {
        return Rule::exists('tour_dates', 'id')->where('tenant_id', Tenant::current()?->id);
    }
}
