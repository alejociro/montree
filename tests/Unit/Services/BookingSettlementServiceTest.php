<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use App\Services\Payments\BookingSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regla de asiento: qué le pasa a la reserva cuando entra un pago cobrado.
 * Es la misma para la pasarela y para el efectivo del guía.
 */
final class BookingSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private BookingSettlementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $this->tenant->makeCurrent();
        $this->tenant->configuration()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['min_partial_payment_pct' => 30],
        );

        $this->service = app(BookingSettlementService::class);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_a_payment_that_settles_the_total_confirms_the_booking(): void
    {
        $booking = $this->booking(paid: '0.00');

        $result = $this->service->apply($booking, $this->payment($booking, '100000.00'));

        $this->assertTrue($result->wasJustConfirmed);
        $this->assertSame('100000.00', $result->booking->paid_amount);
        $this->assertSame(BookingStatus::Confirmed, $result->booking->status);
        $this->assertNull($result->booking->expires_at);
        $this->assertNotNull($result->booking->confirmed_at);
    }

    public function test_a_deposit_at_the_minimum_confirms_the_booking_with_balance_due(): void
    {
        $booking = $this->booking(paid: '0.00', minPaymentPct: 50);

        $result = $this->service->apply($booking, $this->payment($booking, '50000.00', PaymentType::Partial));

        $this->assertTrue($result->wasJustConfirmed);
        $this->assertSame(BookingStatus::Confirmed, $result->booking->status);
        $this->assertSame('50000.00', $result->booking->due_amount);
        $this->assertNull($result->booking->expires_at);
    }

    public function test_a_deposit_below_the_minimum_leaves_the_booking_pending(): void
    {
        $booking = $this->booking(paid: '0.00', minPaymentPct: 50);
        $expiresAt = $booking->expires_at;

        $result = $this->service->apply($booking, $this->payment($booking, '49999.99', PaymentType::Partial));

        $this->assertFalse($result->wasJustConfirmed);
        $this->assertSame(BookingStatus::PendingPayment, $result->booking->status);
        $this->assertNull($result->booking->confirmed_at);
        $this->assertTrue($expiresAt->equalTo($result->booking->expires_at));
    }

    /** Sin override de la salida rige el porcentaje de la agencia. */
    public function test_the_threshold_falls_back_to_the_agency_percentage(): void
    {
        $booking = $this->booking(paid: '0.00');

        $result = $this->service->apply($booking, $this->payment($booking, '30000.00', PaymentType::Partial));

        $this->assertTrue($result->wasJustConfirmed);
        $this->assertSame(BookingStatus::Confirmed, $result->booking->status);
    }

    public function test_a_completed_booking_never_goes_back_to_confirmed(): void
    {
        $booking = $this->booking(paid: '50000.00', status: BookingStatus::Completed);

        $result = $this->service->apply($booking, $this->payment($booking, '50000.00'));

        $this->assertFalse($result->wasJustConfirmed);
        $this->assertSame(BookingStatus::Completed, $result->booking->status);
        $this->assertSame('100000.00', $result->booking->paid_amount);
    }

    public function test_the_confirmation_date_of_an_already_confirmed_booking_is_not_overwritten(): void
    {
        $confirmedAt = Carbon::parse('2026-01-01 08:00:00');
        $booking = $this->booking(paid: '50000.00', status: BookingStatus::Confirmed);
        $booking->update(['confirmed_at' => $confirmedAt]);

        $result = $this->service->apply($booking, $this->payment($booking, '50000.00'));

        $this->assertFalse($result->wasJustConfirmed);
        $this->assertTrue($confirmedAt->equalTo($result->booking->confirmed_at));
    }

    private function booking(
        string $paid,
        ?int $minPaymentPct = null,
        BookingStatus $status = BookingStatus::PendingPayment,
    ): Booking {
        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create([
            'starts_at' => now()->addWeek(),
            'min_payment_pct' => $minPaymentPct,
        ]);

        return Booking::factory()
            ->for(User::factory())
            ->for($tour)
            ->for($tourDate, 'tourDate')
            ->create([
                'status' => $status,
                'total_amount' => '100000.00',
                'paid_amount' => $paid,
                'currency' => 'COP',
                'confirmed_at' => null,
                'expires_at' => now()->addMinutes(30),
            ]);
    }

    private function payment(Booking $booking, string $amount, PaymentType $type = PaymentType::Full): Payment
    {
        return Payment::query()->create([
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::Cash,
            'amount' => $amount,
            'currency' => 'COP',
            'type' => $type,
            'status' => PaymentStatus::Completed,
            'processed_at' => now(),
        ]);
    }
}
