<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\PassengerManifestScenario;
use Tests\TestCase;

/**
 * Pago recibido fuera de pasarela desde el drawer de la planilla.
 */
final class ManualPaymentTest extends TestCase
{
    use PassengerManifestScenario, RefreshDatabase;

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_a_manual_payment_settles_the_booking(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2, '400000.00', '340000.00');
        $booking->update(['status' => BookingStatus::PendingPayment]);
        Tenant::forgetCurrent();

        $response = $this->actingAs($admin)->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", [
            'method' => PaymentGateway::Transfer->value,
            'amount' => '60000.00',
            'reference' => 'Transferencia Bancolombia 4412',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.paid_amount', '400000.00')
            ->assertJsonPath('data.due_amount', '0.00')
            ->assertJsonPath('data.status', BookingStatus::Confirmed->value);

        // La referencia va en su columna: es por donde el módulo de transacciones
        // busca y muestra un pago recibido fuera de pasarela.
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::Transfer->value,
            'amount' => '60000.00',
            'reference' => 'Transferencia Bancolombia 4412',
        ]);
    }

    public function test_a_cash_payment_is_stored_with_its_own_method(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2, '400000.00', '0.00');
        Tenant::forgetCurrent();

        $this->actingAs($admin)->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", [
            'method' => PaymentGateway::Cash->value,
            'amount' => '50000.00',
        ])->assertCreated();

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'gateway' => PaymentGateway::Cash->value,
            'amount' => '50000.00',
        ]);
    }

    public function test_the_payment_method_is_required(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2, '400000.00', '0.00');
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", ['amount' => '50000.00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', __('Elegí si el pago fue en efectivo o por transferencia.'));

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * La pasarela no se registra a mano: ese pago lo asienta el retorno firmado
     * de PlacetoPay, no un humano en la planilla.
     */
    public function test_the_gateway_cannot_be_chosen_as_a_manual_method(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2, '400000.00', '0.00');
        Tenant::forgetCurrent();

        $this->actingAs($admin)->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", [
            'method' => PaymentGateway::PlaceToPay->value,
            'amount' => '50000.00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', __('Elegí si el pago fue en efectivo o por transferencia.'));

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * El efectivo asienta con la misma regla que la pasarela: llegado el mínimo
     * de la salida la reserva queda confirmada con saldo. Lo que no hace es
     * notificar —el guía tiene al viajero delante—.
     */
    public function test_a_cash_deposit_at_or_above_the_minimum_confirms_the_booking_with_balance_due(): void
    {
        Notification::fake();

        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $departure->update(['min_payment_pct' => 50]);
        $booking = $this->bookingOn($departure, 2, '400000.00', '0.00');
        $booking->update(['status' => BookingStatus::PendingPayment, 'expires_at' => now()->addMinutes(30)]);
        Tenant::forgetCurrent();

        $this->actingAs($admin)->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", [
            'method' => PaymentGateway::Cash->value,
            'amount' => '200000.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.due_amount', '200000.00')
            ->assertJsonPath('data.status', BookingStatus::Confirmed->value);

        $this->assertNull($booking->fresh()->expires_at);
        Notification::assertNothingSent();
    }

    public function test_a_cash_deposit_below_the_minimum_keeps_the_booking_pending(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $departure->update(['min_payment_pct' => 50]);
        $booking = $this->bookingOn($departure, 2, '400000.00', '0.00');
        $booking->update(['status' => BookingStatus::PendingPayment]);
        Tenant::forgetCurrent();

        $this->actingAs($admin)->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", [
            'method' => PaymentGateway::Cash->value,
            'amount' => '199999.99',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', BookingStatus::PendingPayment->value);

        $this->assertNull($booking->fresh()->confirmed_at);
    }

    public function test_the_amount_cannot_exceed_the_outstanding_balance(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2, '400000.00', '340000.00');
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", ['method' => PaymentGateway::Cash->value, 'amount' => '60000.01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_a_cancelled_booking_takes_no_payment(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $booking = $this->bookingOn($this->departureFor($this->memberOf($tenant, UserRole::Guide)), 2, '400000.00', '0.00');
        $booking->update(['status' => BookingStatus::Cancelled]);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", ['method' => PaymentGateway::Cash->value, 'amount' => '10000.00'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'BOOKING_PAYMENTS_LOCKED');
    }

    public function test_the_partial_payment_moves_the_share_of_every_passenger(): void
    {
        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        $departure = $this->departureFor($this->memberOf($tenant, UserRole::Guide));
        $booking = $this->bookingOn($departure, 2, '400000.00', '0.00');
        $this->passengerOn($booking, ['full_name' => 'Ana Gomez']);
        $this->passengerOn($booking, ['full_name' => 'Beto Ruiz']);
        Tenant::forgetCurrent();

        $this->actingAs($admin)->postJson(
            $this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments",
            ['method' => PaymentGateway::Cash->value, 'amount' => '100000.00'],
        )->assertCreated();

        $this->actingAs($admin)
            ->getJson($this->host($tenant)."/api/v1/admin/tours/{$departure->tour_id}/passengers")
            ->assertOk()
            ->assertJsonPath('data.0.payment.paid_amount', '50000.00')
            ->assertJsonPath('data.0.payment.due_amount', '150000.00')
            ->assertJsonPath('data.1.payment.paid_amount', '50000.00');
    }

    public function test_a_booking_of_another_tenant_takes_no_payment(): void
    {
        $other = $this->tenantAt('otra');
        $foreign = $this->bookingOn($this->departureFor($this->memberOf($other, UserRole::Guide)), 1, '400000.00', '0.00');

        $tenant = $this->tenantAt();
        $admin = $this->memberOf($tenant, UserRole::Admin);
        Tenant::forgetCurrent();

        $this->actingAs($admin)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$foreign->booking_number}/payments", ['method' => PaymentGateway::Cash->value, 'amount' => '10000.00'])
            ->assertNotFound();
    }

    public function test_sales_can_register_a_payment_but_a_guide_cannot(): void
    {
        $tenant = $this->tenantAt();
        $sales = $this->memberOf($tenant, UserRole::Sales);
        $guide = $this->memberOf($tenant, UserRole::Guide);
        $booking = $this->bookingOn($this->departureFor($guide), 1, '400000.00', '0.00');
        Tenant::forgetCurrent();

        $this->actingAs($sales)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", ['method' => PaymentGateway::Cash->value, 'amount' => '10000.00'])
            ->assertCreated();

        $this->actingAs($guide)
            ->postJson($this->host($tenant)."/api/v1/admin/bookings/{$booking->booking_number}/payments", ['method' => PaymentGateway::Cash->value, 'amount' => '10000.00'])
            ->assertForbidden();
    }
}
