<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TenantMembershipStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\FakeCheckout;
use Tests\TestCase;

final class PlaceToPayReturnTest extends TestCase
{
    use RefreshDatabase;

    private FakeCheckout $checkout;

    private Tenant $tenant;

    private User $user;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'placetopay.login' => 'platform-login',
            'placetopay.tran_key' => 'platform-tran-key',
            'placetopay.url' => 'https://checkout.test',
            'placetopay.retry.attempts' => 1,
        ]);

        $this->checkout = FakeCheckout::fake();

        // WHY: la returnUrl se firma durante una request al subdominio del tenant,
        // asi que la firma queda atada a ese host. Fuera de una request habria que
        // firmar contra app.url y ninguna vuelta validaria.
        URL::forceRootUrl('http://demo.montree.test');

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $this->tenant->makeCurrent();

        $tour = Tour::factory()->create();
        $tourDate = TourDate::factory()->for($tour)->create(['starts_at' => now()->addWeek()]);

        $this->user = User::factory()->create();
        $this->tenant->users()->attach($this->user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->booking = Booking::factory()
            ->for($this->user)
            ->for($tour)
            ->for($tourDate, 'tourDate')
            ->create([
                'status' => BookingStatus::PendingPayment,
                'total_amount' => '100000.00',
                'paid_amount' => '0.00',
                'currency' => 'COP',
            ]);
    }

    public function test_an_approved_return_confirms_the_booking_and_notifies_the_traveler(): void
    {
        Notification::fake();

        $payment = $this->payment('100000.00');
        $this->checkout->queryApproved('100000.00');

        $this->actingAs($this->user)
            ->get($this->returnUrl($payment))
            ->assertRedirect($this->url('/bookings/'.$this->booking->booking_number))
            ->assertSessionHas('success');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('1122334455', $payment->internal_reference);
        $this->assertSame('999999', $payment->authorization);
        $this->assertSame('170821222', $payment->receipt);
        $this->assertSame('CR_VS', $payment->franchise);
        $this->assertSame('visa', $payment->payment_method);
        $this->assertSame('Visa', $payment->payment_method_name);
        $this->assertSame('BANCOLOMBIA', $payment->issuer_name);
        $this->assertSame('Aprobada', $payment->status_message);
        $this->assertSame('APPROVED', $payment->gateway_status);
        $this->assertCount(2, $payment->processor_fields);

        // La fecha del cobro es la del autorizador (no la de la consulta) y queda
        // guardada en la zona de la app: el offset -05:00 no puede correr la hora.
        $this->assertSame(
            Carbon::parse(FakeCheckout::TRANSACTION_DATE)->utc()->toDateTimeString(),
            $payment->processed_at->toDateTimeString(),
        );

        $booking = $this->booking->fresh();

        $this->assertSame('100000.00', $booking->paid_amount);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->expires_at);
        Notification::assertSentTo($this->user, BookingConfirmedNotification::class);
    }

    /**
     * El abono que alcanza el mínimo de la salida asegura la plaza: la reserva
     * queda confirmada aunque siga debiendo el resto.
     */
    public function test_an_approved_partial_payment_at_or_above_the_minimum_confirms_the_booking_with_balance_due(): void
    {
        Notification::fake();

        $payment = $this->payment('30000.00', PaymentType::Partial);
        $this->checkout->queryApproved('30000.00');

        $this->actingAs($this->user)->get($this->returnUrl($payment));

        $booking = $this->booking->fresh();

        $this->assertSame('30000.00', $booking->paid_amount);
        $this->assertSame('70000.00', $booking->due_amount);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->expires_at);
        Notification::assertSentTo($this->user, BookingConfirmedNotification::class);
    }

    /**
     * Solo alcanzable cuando la pasarela aprueba menos de lo pedido: lo cobrado
     * no llega al mínimo, la reserva sigue esperando y su hold sigue corriendo.
     */
    public function test_an_approved_partial_payment_below_the_minimum_keeps_the_booking_pending(): void
    {
        Notification::fake();

        $this->booking->tourDate->update(['min_payment_pct' => 50]);
        $payment = $this->payment('50000.00', PaymentType::Partial);
        $this->checkout->queryApproved('20000.00');

        $this->actingAs($this->user)->get($this->returnUrl($payment));

        $booking = $this->booking->fresh();

        $this->assertSame('20000.00', $booking->paid_amount);
        $this->assertSame(BookingStatus::PendingPayment, $booking->status);
        $this->assertNull($booking->confirmed_at);
        Notification::assertNothingSent();
    }

    public function test_a_rejected_return_marks_the_payment_failed_and_leaves_the_balance_untouched(): void
    {
        $payment = $this->payment('100000.00');
        $this->checkout->queryRejected('Rechazada por el banco');

        $this->actingAs($this->user)
            ->get($this->returnUrl($payment))
            ->assertSessionHas('error', 'Rechazada por el banco');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('REJECTED', $payment->gateway_status);
        $this->assertSame('Rechazada por el banco', $payment->status_message);
        $this->assertSame('1122334455', $payment->internal_reference);
        $this->assertNull($payment->processed_at);
        $this->assertSame('0.00', $this->booking->fresh()->paid_amount);
        $this->assertSame(BookingStatus::PendingPayment, $this->booking->fresh()->status);
    }

    /**
     * `status` colapsa los tres finales malos en `failed`; `gateway_status`
     * guarda el original para poder separar «lo rechazó el banco» de «la sesión
     * expiró» cuando se saquen estadísticas.
     */
    public function test_an_expired_session_is_distinguishable_from_a_bank_rejection(): void
    {
        $payment = $this->payment('100000.00');
        $this->checkout->queryRejected('La sesión ha expirado', sessionStatus: 'EXPIRED');

        $this->actingAs($this->user)->get($this->returnUrl($payment));

        $payment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('EXPIRED', $payment->gateway_status);
    }

    public function test_a_pending_return_leaves_the_payment_in_progress(): void
    {
        $payment = $this->payment('100000.00');
        $this->checkout->queryPending();

        $this->actingAs($this->user)->get($this->returnUrl($payment));

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
        $this->assertSame('0.00', $this->booking->fresh()->paid_amount);
    }

    public function test_returning_twice_does_not_credit_the_balance_twice(): void
    {
        Notification::fake();

        $payment = $this->payment('100000.00');
        $this->checkout->queryApproved('100000.00');

        $this->actingAs($this->user)->get($this->returnUrl($payment));
        // La segunda vuelta no consulta: el pago ya esta resuelto, y la cola de
        // respuestas del doble quedaria vacia si lo intentara.
        $this->actingAs($this->user)->get($this->returnUrl($payment));

        $this->assertSame('100000.00', $this->booking->fresh()->paid_amount);
        $this->assertSame(1, Payment::query()->where('status', PaymentStatus::Completed)->count());
        Notification::assertSentTimes(BookingConfirmedNotification::class, 1);
    }

    public function test_an_unsigned_return_is_rejected(): void
    {
        $payment = $this->payment('100000.00');

        $this->get($this->url('/payments/'.$payment->id.'/return'))->assertForbidden();

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_an_unknown_payment_is_a_not_found(): void
    {
        $this->get(URL::signedRoute('payments.return', ['payment' => 999999], absolute: true))->assertNotFound();
    }

    /**
     * La pasarela puede aprobar menos de lo pedido. Lo que se acredita y lo que
     * suman los reportes es lo aprobado, no lo solicitado.
     */
    public function test_an_amount_approved_below_the_requested_one_credits_only_what_was_charged(): void
    {
        $payment = $this->payment('100000.00');
        $this->checkout->queryApproved('70000.00');

        $this->actingAs($this->user)->get($this->returnUrl($payment));

        $this->assertSame('70000.00', $payment->fresh()->amount);

        $booking = $this->booking->fresh();
        $this->assertSame('70000.00', $booking->paid_amount);
        // Cubre el mínimo del 30%, así que la plaza queda asegurada con saldo.
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame('30000.00', $booking->due_amount);
    }

    /**
     * La firma cubre el host, asi que la vuelta de un pago solo vale en el
     * subdominio de su agencia: el mismo enlace en otra agencia no resuelve nada.
     */
    public function test_the_return_link_does_not_work_on_another_tenant_host(): void
    {
        $payment = $this->payment('100000.00');
        $signedPath = str_replace('http://demo.montree.test', '', $this->returnUrl($payment));

        Tenant::factory()->create(['slug' => 'otra', 'domain' => 'otra.montree.test'])->makeCurrent();

        $this->get('http://otra.montree.test'.$signedPath)->assertForbidden();

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
        $this->assertSame([], $this->checkout->requests);
    }

    private function payment(string $amount, PaymentType $type = PaymentType::Full): Payment
    {
        return Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'booking_id' => $this->booking->id,
            'gateway' => PaymentGateway::PlaceToPay,
            'request_id' => '12345',
            'amount' => $amount,
            'currency' => 'COP',
            'type' => $type,
            'status' => PaymentStatus::Processing,
            'reference' => 'MTR-1',
            'process_url' => 'https://checkout.test/session/12345/xyz',
        ]);
    }

    private function returnUrl(Payment $payment): string
    {
        return URL::signedRoute('payments.return', ['payment' => $payment->id], absolute: true);
    }

    private function url(string $path): string
    {
        return 'http://demo.montree.test'.$path;
    }
}
