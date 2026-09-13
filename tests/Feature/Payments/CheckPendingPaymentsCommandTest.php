<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeCheckout;
use Tests\TestCase;

final class CheckPendingPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeCheckout $checkout;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        config([
            'placetopay.login' => 'platform-login',
            'placetopay.tran_key' => 'platform-tran-key',
            'placetopay.url' => 'https://checkout.test',
            'placetopay.check.settle_margin_minutes' => 15,
            'placetopay.check.lookback_hours' => 72,
        ]);

        $this->checkout = FakeCheckout::fake();
    }

    public function test_it_resolves_an_approved_payment_whose_buyer_never_came_back(): void
    {
        [$tenant, $booking] = $this->scenario('demo');
        $payment = $this->payment($tenant, $booking, '100000.00', now()->subHour());

        $this->checkout->queryApproved('100000.00');

        $this->artisan('payment:check')->assertSuccessful();

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);

        $booking->refresh();
        $this->assertSame('100000.00', $booking->paid_amount);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_it_sweeps_every_tenant_and_leaves_no_tenant_current(): void
    {
        [$tenantA, $bookingA] = $this->scenario('uno');
        [$tenantB, $bookingB] = $this->scenario('dos');

        $paymentA = $this->payment($tenantA, $bookingA, '100000.00', now()->subHour(), 111);
        $paymentB = $this->payment($tenantB, $bookingB, '100000.00', now()->subHour(), 222);

        Tenant::forgetCurrent();

        $this->checkout->queryApproved('100000.00', requestId: 111)->queryRejected('Fondos insuficientes', requestId: 222);

        $this->artisan('payment:check')->assertSuccessful();

        $this->assertSame(PaymentStatus::Completed, $paymentA->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, $paymentB->fresh()->status);
        $this->assertSame('100000.00', $bookingA->fresh()->paid_amount);
        $this->assertSame('0.00', $bookingB->fresh()->paid_amount);
        $this->assertNull(Tenant::current());
    }

    public function test_it_skips_sessions_still_inside_the_settle_margin(): void
    {
        [$tenant, $booking] = $this->scenario('demo');
        $payment = $this->payment($tenant, $booking, '100000.00', now()->subMinutes(2));

        $this->artisan('payment:check')->assertSuccessful();

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
        $this->assertSame([], $this->checkout->requests);
    }

    public function test_it_ignores_payments_that_are_already_resolved(): void
    {
        [$tenant, $booking] = $this->scenario('demo');
        $payment = $this->payment($tenant, $booking, '100000.00', now()->subHour());
        $payment->update(['status' => PaymentStatus::Completed]);

        $this->artisan('payment:check')->assertSuccessful();

        $this->assertSame([], $this->checkout->requests);
    }

    public function test_a_failing_tenant_does_not_stop_the_sweep(): void
    {
        [$tenantA, $bookingA] = $this->scenario('uno');
        [$tenantB, $bookingB] = $this->scenario('dos');

        $paymentA = $this->payment($tenantA, $bookingA, '100000.00', now()->subHour(), 111);
        $paymentB = $this->payment($tenantB, $bookingB, '100000.00', now()->subHour(), 222);

        // La cola solo tiene una respuesta: el primer pago se resuelve y el
        // segundo revienta al quedarse sin respuesta del transporte.
        $this->checkout->queryApproved('100000.00', requestId: 111);

        $this->artisan('payment:check')->assertSuccessful();

        $this->assertSame(PaymentStatus::Completed, $paymentA->fresh()->status);
        $this->assertSame(PaymentStatus::Processing, $paymentB->fresh()->status);
    }

    /**
     * @return array{0: Tenant, 1: Booking}
     */
    private function scenario(string $slug): array
    {
        $tenant = Tenant::factory()->create(['slug' => $slug, 'domain' => $slug.'.montree.test']);
        $tenant->makeCurrent();

        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['starts_at' => now()->addWeek()]);
        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->for($tour)
            ->for($tourDate, 'tourDate')
            ->create([
                'status' => BookingStatus::PendingPayment,
                'total_amount' => '100000.00',
                'paid_amount' => '0.00',
                'currency' => 'COP',
            ]);

        return [$tenant, $booking];
    }

    private function payment(Tenant $tenant, Booking $booking, string $amount, \DateTimeInterface $createdAt, int $requestId = 12345): Payment
    {
        $payment = Payment::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::PlaceToPay,
            'request_id' => (string) $requestId,
            'amount' => $amount,
            'currency' => 'COP',
            'type' => PaymentType::Full,
            'status' => PaymentStatus::Processing,
            'reference' => 'MTR-'.$requestId,
        ]);

        $payment->forceFill(['created_at' => $createdAt])->save();

        return $payment;
    }
}
