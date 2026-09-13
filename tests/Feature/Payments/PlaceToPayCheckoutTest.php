<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Contracts\CheckoutClientFactory;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeCheckout;
use Tests\TestCase;

final class PlaceToPayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Copia del patrón con el que PlacetoPay valida `description`
     * (`BaseValidator::PATTERN_DESCRIPTION`). Cualquier otro caracter devuelve
     * `request_not_valid` y la sesión no se abre.
     */
    private const GATEWAY_DESCRIPTION_PATTERN = '/^[a-zñáéíóúäëïöüàèìòùÑÁÉÍÓÚÄËÏÖÜÀÈÌÒÙÇçÃã\s\d\[\]\.,\$#\&\-\_()\/\%\+\\\\\':;\<\>\|\=@]{2,250}$/i';

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

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        $this->tenant->makeCurrent();

        $tour = Tour::factory()->create(['name' => 'Cerro Quitasol']);
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
                'contact_snapshot' => ['name' => 'Ana', 'email' => 'ana@example.test', 'phone' => '3001234567'],
            ]);
    }

    public function test_full_payment_creates_session_and_redirects_to_the_checkout(): void
    {
        $this->checkout->sessionCreated(requestId: 98765, processUrl: 'https://checkout.test/session/98765/xyz');

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full'])
            ->assertRedirect('https://checkout.test/session/98765/xyz');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentGateway::PlaceToPay, $payment->gateway);
        $this->assertSame(PaymentStatus::Processing, $payment->status);
        $this->assertSame(PaymentType::Full, $payment->type);
        $this->assertSame('100000.00', $payment->amount);
        $this->assertSame('98765', $payment->request_id);
        $this->assertSame('OK', $payment->gateway_status);
        $this->assertSame('MTR-'.$payment->id, $payment->reference);
        $this->assertNotNull($payment->session_expires_at);

        // La reserva no se toca hasta que la pasarela confirme.
        $this->assertSame('0.00', $this->booking->fresh()->paid_amount);
    }

    public function test_session_payload_carries_the_signed_return_url_and_the_buyer(): void
    {
        $this->checkout->sessionCreated();

        $this->actingAs($this->user)
            ->withHeader('User-Agent', 'MontreeTest/1.0')
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full']);

        $payload = $this->checkout->lastPayload();
        $payment = Payment::query()->firstOrFail();

        $this->assertSame('MTR-'.$payment->id, $payload['payment']['reference']);
        $this->assertEquals(100000.0, $payload['payment']['amount']['total']);
        $this->assertSame('COP', $payload['payment']['amount']['currency']);
        $this->assertStringContainsString('/payments/'.$payment->id.'/return', $payload['returnUrl']);
        $this->assertStringContainsString('signature=', $payload['returnUrl']);
        $this->assertLessThanOrEqual(255, mb_strlen($payload['returnUrl']));
        $this->assertSame('Ana', $payload['buyer']['name']);
        $this->assertSame('MontreeTest/1.0', $payload['userAgent']);
        $this->assertStringContainsString((string) $this->booking->booking_number, $payload['payment']['description']);
        $this->assertSame('platform-login', $payload['auth']['login']);
    }

    public function test_the_description_drops_characters_the_gateway_rejects(): void
    {
        $this->booking->tour->update(['name' => '¡Aventura! Cocora — 100% "extremo" ¿vamos?']);
        $this->checkout->sessionCreated();

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full']);

        $description = $this->checkout->lastPayload()['payment']['description'];

        $this->assertMatchesRegularExpression(self::GATEWAY_DESCRIPTION_PATTERN, $description);
        $this->assertStringContainsString('Aventura', $description);
        $this->assertStringContainsString((string) $this->booking->booking_number, $description);
    }

    public function test_partial_payment_below_the_tenant_minimum_is_rejected_without_calling_the_gateway(): void
    {
        $this->tenant->configuration()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['min_partial_payment_pct' => 30],
        );
        $this->booking->refresh();

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), [
                'type' => 'partial',
                'amount' => '10000.00',
            ])
            // Error de campo y no flash: el mensaje sale junto al input del monto.
            ->assertSessionHasErrors(['amount' => 'El abono mínimo es 30000.00 COP.']);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], $this->checkout->requests);
    }

    /**
     * El mínimo lo fija la salida: el porcentaje de la agencia solo aplica
     * cuando la salida no define el suyo.
     */
    public function test_the_minimum_deposit_comes_from_the_departure_when_it_is_set(): void
    {
        $this->tenant->configuration()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['min_partial_payment_pct' => 30],
        );
        $this->booking->tourDate->update(['min_payment_pct' => 60]);

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), [
                'type' => 'partial',
                'amount' => '50000.00',
            ])
            ->assertSessionHasErrors(['amount' => 'El abono mínimo es 60000.00 COP.']);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], $this->checkout->requests);
    }

    public function test_the_minimum_deposit_falls_back_to_the_agency_percentage(): void
    {
        $this->tenant->configuration()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['min_partial_payment_pct' => 40],
        );
        $this->booking->tourDate->update(['min_payment_pct' => null]);
        $this->checkout->sessionCreated();

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), [
                'type' => 'partial',
                'amount' => '40000.00',
            ])
            ->assertRedirect();

        $this->assertSame('40000.00', Payment::query()->firstOrFail()->amount);
    }

    public function test_partial_payment_above_the_pending_balance_is_rejected(): void
    {
        $this->booking->update(['paid_amount' => '60000.00']);

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), [
                'type' => 'partial',
                'amount' => '50000.00',
            ])
            ->assertSessionHasErrors(['amount' => 'El monto supera el saldo pendiente de 40000.00 COP.']);

        $this->assertSame(0, Payment::query()->count());
    }

    /**
     * El piso del abono se mide sobre el total, pero se recorta al saldo: si no,
     * una reserva a la que le faltan menos que el porcentaje exigido no se
     * podría terminar de pagar por abono.
     */
    public function test_the_minimum_deposit_never_exceeds_the_pending_balance(): void
    {
        $this->tenant->configuration()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['min_partial_payment_pct' => 50],
        );
        $this->booking->update(['paid_amount' => '90000.00']);
        $this->booking->refresh();

        $this->assertSame('10000.00', $this->booking->min_payment_amount);

        $this->checkout->sessionCreated();

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), [
                'type' => 'partial',
                'amount' => '10000.00',
            ])
            ->assertRedirect();

        $this->assertSame('10000.00', Payment::query()->firstOrFail()->amount);
    }

    public function test_full_payment_on_a_partially_paid_booking_charges_only_the_balance(): void
    {
        $this->booking->update(['paid_amount' => '40000.00']);
        $this->checkout->sessionCreated();

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full']);

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('60000.00', $payment->amount);
        $this->assertSame(PaymentType::Remainder, $payment->type);
    }

    public function test_a_settled_booking_cannot_start_another_payment(): void
    {
        $this->booking->update(['paid_amount' => '100000.00', 'status' => BookingStatus::Confirmed]);

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full'])
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_cancelled_booking_returns_to_the_page_with_the_error_instead_of_json(): void
    {
        $this->booking->update(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()]);

        $this->actingAs($this->user)
            ->from($this->url('/bookings/'.$this->booking->booking_number))
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full'])
            ->assertRedirect($this->url('/bookings/'.$this->booking->booking_number))
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame([], $this->checkout->requests);
    }

    public function test_a_rejected_session_marks_the_payment_as_failed(): void
    {
        $this->checkout->sessionRejected('Credenciales inválidas');

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full'])
            ->assertSessionHas('error', 'Credenciales inválidas');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('Credenciales inválidas', $payment->status_message);
        $this->assertNull($payment->process_url);
    }

    public function test_a_gateway_outage_leaves_the_payment_failed_and_reports_a_service_error(): void
    {
        $this->checkout->serviceDown();

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full'])
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Failed, Payment::query()->firstOrFail()->status);
    }

    /**
     * Doble clic en «Pagar»: dos sesiones vivas por el mismo saldo acreditarian
     * el pago dos veces si las dos se aprobaran.
     */
    public function test_a_second_attempt_reuses_the_live_session_instead_of_opening_another(): void
    {
        $this->checkout->sessionCreated(requestId: 55, processUrl: 'https://checkout.test/session/55/a');

        $url = $this->url('/bookings/'.$this->booking->booking_number.'/pay');

        $this->actingAs($this->user)->post($url, ['type' => 'full'])
            ->assertRedirect('https://checkout.test/session/55/a');

        // La cola del doble esta vacia: si abriera otra sesion, la peticion moriria.
        $this->actingAs($this->user)->post($url, ['type' => 'full'])
            ->assertRedirect('https://checkout.test/session/55/a');

        $this->assertSame(1, Payment::query()->count());
        $this->assertCount(1, $this->checkout->requests);
    }

    public function test_an_expired_session_does_not_block_a_new_attempt(): void
    {
        $this->checkout->sessionCreated(requestId: 55, processUrl: 'https://checkout.test/session/55/a');

        $url = $this->url('/bookings/'.$this->booking->booking_number.'/pay');
        $this->actingAs($this->user)->post($url, ['type' => 'full']);

        Payment::query()->firstOrFail()->update(['session_expires_at' => now()->subMinute()]);

        $this->checkout->sessionCreated(requestId: 66, processUrl: 'https://checkout.test/session/66/b');

        $this->actingAs($this->user)->post($url, ['type' => 'full'])
            ->assertRedirect('https://checkout.test/session/66/b');

        $this->assertSame(2, Payment::query()->count());
    }

    /**
     * El selector de abono no recalcula nada: el porcentaje efectivo y el piso en
     * plata salen del servidor. Si el front los derivara, el checkout rebotaría
     * por un monto que el viajero vio como válido.
     */
    public function test_the_booking_page_ships_the_effective_minimum_of_the_departure(): void
    {
        $this->tenant->configuration()->updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['min_partial_payment_pct' => 30],
        );
        $this->booking->tourDate->update(['min_payment_pct' => 60]);

        $this->actingAs($this->user)
            ->get($this->url('/bookings/'.$this->booking->booking_number))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('booking.min_payment_pct', 60)
                ->where('booking.min_payment_amount', '60000.00')
                ->missing('booking.min_partial_payment_pct'));
    }

    public function test_a_booking_from_another_user_is_not_payable(): void
    {
        $other = User::factory()->create();
        $this->tenant->users()->attach($other->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->actingAs($other)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full'])
            ->assertNotFound();

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_tenant_without_credentials_and_no_platform_fallback_cannot_pay(): void
    {
        config(['placetopay.login' => null, 'placetopay.tran_key' => null]);
        app()->forgetInstance(CheckoutClientFactory::class);

        $this->actingAs($this->user)
            ->post($this->url('/bookings/'.$this->booking->booking_number.'/pay'), ['type' => 'full'])
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Failed, Payment::query()->firstOrFail()->status);
    }

    private function url(string $path): string
    {
        return 'http://demo.montree.test'.$path;
    }
}
