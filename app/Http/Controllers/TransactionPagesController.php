<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\TransactionSearchField;
use App\Http\Requests\Admin\Transaction\TransactionIndexRequest;
use App\Http\Resources\Admin\TransactionDetailResource;
use App\Http\Resources\Admin\TransactionResource;
use App\Models\Payment;
use App\Models\TourDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las transacciones de la agencia. El aislamiento por tenant lo da el global
 * scope de `Payment`: una transacción de otra agencia no resuelve y sale 404.
 */
final class TransactionPagesController extends Controller
{
    private const PER_PAGE = 25;

    private const DEPARTURE_OPTIONS = 100;

    public function index(TransactionIndexRequest $request): Response
    {
        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $this->transactions($request),
            'filters' => $request->filters(),
            'statuses' => $this->options(PaymentStatus::cases()),
            'gateways' => $this->options(PaymentGateway::cases()),
            'search_fields' => $this->options(TransactionSearchField::cases()),
            'departures' => $this->departures(),
            'default_days' => TransactionIndexRequest::DEFAULT_DAYS,
            'can' => ['query' => $request->user()?->can('payments.query') ?? false],
        ]);
    }

    public function show(Request $request, Payment $payment): Response
    {
        $payment->load(['booking.user', 'booking.tour', 'booking.tourDate']);

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => (new TransactionDetailResource($payment))->resolve(),
            'can' => ['query' => $request->user()?->can('payments.query') ?? false],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactions(TransactionIndexRequest $request): array
    {
        return TransactionResource::collection($this->paginate($request))
            ->response()
            ->getData(assoc: true);
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    private function paginate(TransactionIndexRequest $request): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['booking.user', 'booking.tour', 'booking.tourDate'])
            ->searchBy($request->searchField(), $request->search())
            ->when($request->status() !== null, fn ($query) => $query->where('status', $request->status()))
            ->when($request->gateway() !== null, fn ($query) => $query->where('gateway', $request->gateway()))
            ->forTourDate($request->tourDateId())
            ->settledBetween($request->from(), $request->to())
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @param  array<int, PaymentGateway|PaymentStatus|TransactionSearchField>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(
            fn (PaymentGateway|PaymentStatus|TransactionSearchField $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }

    /**
     * Solo las salidas que tienen algo cobrado: el desplegable sirve para
     * conciliar, no para listar el catálogo entero.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function departures(): array
    {
        return TourDate::query()
            ->whereHas('bookings.payments')
            ->with('tour:id,name')
            ->orderByDesc('starts_at')
            ->limit(self::DEPARTURE_OPTIONS)
            ->get(['id', 'tour_id', 'starts_at'])
            ->map(fn (TourDate $date): array => [
                'value' => $date->id,
                'label' => $date->tour->name.' · '.$date->starts_at->translatedFormat('d M Y'),
            ])
            ->all();
    }
}
