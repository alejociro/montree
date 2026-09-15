<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Actions\Payment\ResolvePaymentAction;
use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Jobs\ResolvePaymentJob;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeCheckout;
use Tests\TestCase;

final class PlaceToPayNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const NOTIFICATION_URL = 'http://demo.montree.test/payments/notification';

    private FakeCheckout $checkout;

    private Tenant $tenant;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        config([
            'placetopay.login' => 'platform-login',
            'placetopay.tran_key' => 'platform-tran-key',
            'placetopay.url' => 'https://checkout.test',
            'placetopay.retry.attempts' => 1,
        ]);

        $this->checkout = FakeCheckout::fake();

        [$this->tenant, $this->booking] = $this->scenario('demo');
    }

    public function test_a_signed_notification_resolves_the_payment(): void
    {
        $payment = $this->payment($this->tenant, $this->booking);
        $this->checkout->queryApproved('100000.00');

        $this->postJson(self::NOTIFICATION_URL, $this->payload())->assertOk();

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);

        $booking = $this->booking->fresh();
        $this->assertSame('100000.00', $booking->paid_amount);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_it_accepts_a_signature_prefixed_with_its_algorithm(): void
    {
        $payment = $this->payment($this->tenant, $this->booking);
        $this->checkout->queryApproved('100000.00');

        $payload = $this->payload();
        $payload['signature'] = 'sha256:'.hash('sha256', '12345APPROVED'.FakeCheckout::TRANSACTION_DATE.'platform-tran-key');

        $this->postJson(self::NOTIFICATION_URL, $payload)->assertOk();

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
    }

    public function test_it_rejects_a_notification_with_an_invalid_signature(): void
    {
        Bus::fake();

        $payment = $this->payment($this->tenant, $this->booking);

        $payload = $this->payload();
        $payload['signature'] = sha1('firmada-con-otra-llave');

        $this->postJson(self::NOTIFICATION_URL, $payload)->assertStatus(422);

        Bus::assertNothingDispatched();
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_it_rejects_a_notification_for_an_unknown_request(): void
    {
        Bus::fake();

        $this->postJson(self::NOTIFICATION_URL, $this->payload(requestId: '99999'))->assertStatus(422);

        Bus::assertNothingDispatched();
    }

    public function test_it_rejects_an_incomplete_payload(): void
    {
        $this->postJson(self::NOTIFICATION_URL, ['requestId' => '12345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference', 'signature', 'status']);
    }

    public function test_it_resolves_the_payment_under_its_own_tenant_whatever_the_host(): void
    {
        [$otherTenant, $otherBooking] = $this->scenario('otra');
        $payment = $this->payment($otherTenant, $otherBooking);

        $this->tenant->makeCurrent();
        $this->checkout->queryApproved('100000.00');

        $this->postJson(self::NOTIFICATION_URL, $this->payload())->assertOk();

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $otherBooking->fresh()->status);
        $this->assertNotSame($otherTenant->id, Tenant::current()?->id);
    }

    public function test_it_rejects_a_notification_whose_reference_does_not_match_the_request(): void
    {
        Bus::fake();

        $payment = $this->payment($this->tenant, $this->booking);

        $payload = $this->payload();
        $payload['reference'] = 'MTR-9999';

        $this->postJson(self::NOTIFICATION_URL, $payload)->assertStatus(422);

        Bus::assertNothingDispatched();
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_the_job_ignores_a_payment_that_no_longer_exists(): void
    {
        (new ResolvePaymentJob(404))->handle(app(ResolvePaymentAction::class));

        $this->assertSame([], $this->checkout->requests);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $requestId = '12345'): array
    {
        return [
            'requestId' => $requestId,
            'reference' => 'MTR-1',
            'signature' => sha1($requestId.'APPROVED'.FakeCheckout::TRANSACTION_DATE.'platform-tran-key'),
            'status' => [
                'status' => 'APPROVED',
                'reason' => '00',
                'message' => 'Aprobada',
                'date' => FakeCheckout::TRANSACTION_DATE,
            ],
        ];
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

    private function payment(Tenant $tenant, Booking $booking): Payment
    {
        return Payment::query()->create([
            'tenant_id' => $tenant->id,
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::PlaceToPay,
            'request_id' => '12345',
            'amount' => '100000.00',
            'currency' => 'COP',
            'type' => PaymentType::Full,
            'status' => PaymentStatus::Processing,
            'reference' => 'MTR-1',
            'process_url' => 'https://checkout.test/session/12345/xyz',
        ]);
    }
}
