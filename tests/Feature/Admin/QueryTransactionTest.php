<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeCheckout;
use Tests\TestCase;

/**
 * La reconsulta desde el panel. No abre un segundo camino de resolución: usa
 * `ResolvePaymentAction`, así que hereda su idempotencia y su regla de asiento.
 */
final class QueryTransactionTest extends TestCase
{
    use RefreshDatabase;

    private FakeCheckout $checkout;

    private Tenant $tenant;

    private TourDate $departure;

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
        TenantConfiguration::factory()->for($this->tenant)->create(['currency' => 'COP']);
        $this->tenant->makeCurrent();

        $tour = Tour::factory()->create();
        $this->departure = TourDate::factory()->for($tour)->create(['starts_at' => now()->addWeek()]);

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_querying_a_hung_payment_settles_it_and_credits_the_booking(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $booking = $this->booking();
        $payment = $this->payment($booking, ['status' => PaymentStatus::Processing, 'request_id' => '12345']);

        $this->checkout->queryApproved('100000.00');

        $this->actingAs($admin)
            ->from($this->url('/'.$payment->id))
            ->post($this->url('/'.$payment->id.'/query'))
            ->assertRedirect($this->url('/'.$payment->id))
            ->assertSessionHas('success');

        $payment->refresh();
        $booking->refresh();

        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('APPROVED', $payment->gateway_status);
        $this->assertSame('100000.00', $booking->paid_amount);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_a_member_without_the_query_permission_is_rejected(): void
    {
        $sales = $this->memberWithRole(UserRole::Sales);
        $payment = $this->payment($this->booking(), ['status' => PaymentStatus::Processing]);

        $this->actingAs($sales)
            ->post($this->url('/'.$payment->id.'/query'))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Processing, $payment->refresh()->status);
        $this->assertSame([], $this->checkout->requests);
    }

    public function test_a_manual_payment_is_rejected_without_calling_the_gateway(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $payment = $this->payment($this->booking(), [
            'gateway' => PaymentGateway::Transfer,
            'request_id' => null,
            'status' => PaymentStatus::Completed,
            'reference' => 'Consignación 4471',
        ]);

        $this->actingAs($admin)
            ->from($this->url('/'.$payment->id))
            ->post($this->url('/'.$payment->id.'/query'))
            ->assertRedirect($this->url('/'.$payment->id))
            ->assertSessionHas('error', 'Este pago no se puede consultar en la pasarela.');

        $this->assertSame([], $this->checkout->requests);
    }

    /**
     * Reconsultar refresca los datos del autorizador pero no vuelve a acreditar:
     * `ResolvePaymentAction` corta por `isResolved()`.
     */
    public function test_querying_a_settled_payment_does_not_credit_the_booking_twice(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $booking = $this->booking(paid: '100000.00', status: BookingStatus::Confirmed);
        $payment = $this->payment($booking, [
            'status' => PaymentStatus::Completed,
            'gateway_status' => 'APPROVED',
            'request_id' => '12345',
            'processed_at' => now()->subDay(),
        ]);

        $this->checkout->queryApproved('100000.00');

        $this->actingAs($admin)
            ->from($this->url('/'.$payment->id))
            ->post($this->url('/'.$payment->id.'/query'))
            ->assertSessionHas('success', 'El pago ya estaba resuelto como Completado.');

        $this->assertSame('100000.00', $booking->refresh()->paid_amount);
        $this->assertSame(PaymentStatus::Completed, $payment->refresh()->status);
    }

    public function test_a_gateway_outage_reports_the_error_and_leaves_the_payment_untouched(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $booking = $this->booking();
        $payment = $this->payment($booking, ['status' => PaymentStatus::Processing, 'request_id' => '12345']);

        $this->checkout->serviceDown();

        $this->actingAs($admin)
            ->from($this->url('/'.$payment->id))
            ->post($this->url('/'.$payment->id.'/query'))
            ->assertRedirect($this->url('/'.$payment->id))
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Processing, $payment->refresh()->status);
        $this->assertSame('0.00', $booking->refresh()->paid_amount);
    }

    public function test_a_transaction_of_another_tenant_is_not_found(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $other = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        TenantConfiguration::factory()->for($other)->create();
        $other->makeCurrent();
        $foreignTour = Tour::factory()->create();
        $foreignDeparture = TourDate::factory()->for($foreignTour)->create(['starts_at' => now()->addWeek()]);
        $foreign = $this->payment($this->booking($foreignDeparture), ['status' => PaymentStatus::Processing]);
        $this->tenant->makeCurrent();

        $this->actingAs($admin)->post($this->url('/'.$foreign->id.'/query'))->assertNotFound();
    }

    private function url(string $path = ''): string
    {
        return 'http://demo.montree.test/admin/transactions'.$path;
    }

    private function booking(
        ?TourDate $departure = null,
        string $paid = '0.00',
        BookingStatus $status = BookingStatus::PendingPayment,
    ): Booking {
        $departure ??= $this->departure;

        return Booking::factory()
            ->for(User::factory())
            ->for($departure->tour)
            ->for($departure, 'tourDate')
            ->create([
                'status' => $status,
                'total_amount' => '100000.00',
                'paid_amount' => $paid,
                'currency' => 'COP',
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(Booking $booking, array $attributes): Payment
    {
        return Payment::factory()->for($booking)->create([
            'amount' => '100000.00',
            'currency' => 'COP',
            'type' => PaymentType::Full,
            'reference' => 'MTR-'.fake()->unique()->numberBetween(1, 99999),
            ...$attributes,
        ]);
    }

    private function memberWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->tenant->users()->attach($user->id, [
            'status' => TenantMembershipStatus::Active->value,
            'joined_at' => now(),
        ]);
        Role::findOrCreate($role->value, 'web');
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role->value);

        return $user;
    }
}
