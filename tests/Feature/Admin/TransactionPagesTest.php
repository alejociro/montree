<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El listado y el detalle de transacciones del panel. Lo que se prueba acá,
 * además del filtrado, es lo que NO viaja: el enlace de cobro vivo, el volcado
 * crudo de la pasarela y el BIN de la tarjeta.
 */
final class TransactionPagesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tour $tour;

    private TourDate $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'demo', 'domain' => 'demo.montree.test']);
        TenantConfiguration::factory()->for($this->tenant)->create(['currency' => 'COP']);
        $this->tenant->makeCurrent();

        $this->tour = Tour::factory()->create(['name' => 'Valle de Cocora']);
        $this->departure = TourDate::factory()->for($this->tour)->create(['starts_at' => now()->addWeek()]);

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        setPermissionsTeamId(0);

        parent::tearDown();
    }

    public function test_the_index_lists_the_transactions_of_the_tenant_most_recent_first(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $older = $this->payment(['reference' => 'MTR-1'], completed: true, processedAt: now()->subDays(3));
        $newer = $this->payment(['reference' => 'MTR-2'], completed: true, processedAt: now()->subDay());

        $response = $this->actingAs($admin)->get($this->url());

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Transactions/Index', false)
            ->has('transactions.data', 2)
            ->where('transactions.data.0.id', $newer->id)
            ->where('transactions.data.1.id', $older->id)
            ->where('transactions.data.0.gateway_label', 'PlacetoPay')
            ->where('transactions.data.0.booking.tour_name', 'Valle de Cocora')
            ->where('transactions.data.0.booking.tour_date_id', $this->departure->id)
            ->where('transactions.meta.total', 2)
            ->where('filters.search', null)
            ->has('statuses', 5)
            ->has('gateways', 2)
            ->where('can.query', true)
        );
    }

    public function test_the_index_filters_by_status_gateway_and_departure(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $approved = $this->payment(['reference' => 'MTR-10'], completed: true);
        $this->payment(['reference' => 'MTR-11', 'status' => PaymentStatus::Failed]);
        $this->payment([
            'reference' => 'caja-3',
            'gateway' => PaymentGateway::Manual,
            'request_id' => null,
            'status' => PaymentStatus::Completed,
        ]);

        $other = TourDate::factory()->for($this->tour)->create(['starts_at' => now()->addMonth()]);
        $this->payment(['reference' => 'MTR-12'], completed: true, departure: $other);

        $this->actingAs($admin)->get($this->url('?status=completed&gateway=placetopay&tour_date_id='.$this->departure->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $approved->id)
                ->where('filters.status', 'completed')
                ->where('filters.gateway', 'placetopay')
                ->where('filters.tour_date_id', $this->departure->id)
            );
    }

    public function test_each_search_field_matches_only_its_own_column(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $payment = $this->payment([
            'reference' => 'MTR-17',
            'request_id' => '4242',
            'authorization' => '000111',
            'receipt' => '5551234',
        ], completed: true);
        $this->payment(['reference' => 'MTR-18', 'request_id' => '9999']);

        $cases = [
            'reference' => 'MTR-17',
            'request_id' => '4242',
            'authorization' => '000111',
            'receipt' => '5551234',
            'booking_number' => (string) $payment->booking->booking_number,
        ];

        foreach ($cases as $field => $term) {
            $this->actingAs($admin)
                ->get($this->url('?search_by='.$field.'&search='.urlencode($term)))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('transactions.data', 1)
                    ->where('transactions.data.0.id', $payment->id)
                );
        }
    }

    /**
     * El identificador se compara entero: un pedazo no devuelve la transacción.
     * Es lo que permite que la consulta use los índices en vez de barrer.
     */
    public function test_an_identifier_search_is_exact_and_not_a_partial_match(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $this->payment(['reference' => 'MTR-17', 'request_id' => '4242'], completed: true);

        $this->actingAs($admin)->get($this->url('?search_by=request_id&search=424'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('transactions.data', 0));
    }

    public function test_searching_by_payer_name_is_partial_and_case_insensitive(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $payment = $this->payment(['reference' => 'MTR-20'], completed: true);
        $payment->booking->user->update(['name' => 'Ana María Pérez']);
        $this->payment(['reference' => 'MTR-21']);

        $this->actingAs($admin)->get($this->url('?search_by=payer&search='.urlencode('ana maría')))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $payment->id)
            );
    }

    /**
     * Pegar un requestId busca en toda la historia: el pago viejo tiene que
     * aparecer aunque caiga fuera de la ventana por defecto.
     */
    public function test_an_exact_search_reaches_past_the_default_window(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $old = $this->payment(['request_id' => '7777'], completed: true, processedAt: now()->subYear());

        $this->actingAs($admin)->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('transactions.data', 0));

        $this->actingAs($admin)->get($this->url('?search_by=request_id&search=7777'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $old->id)
                ->where('filters.from', null)
            );
    }

    public function test_the_listing_defaults_to_the_last_thirty_days(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);
        $recent = $this->payment(['reference' => 'MTR-30'], completed: true, processedAt: now()->subDays(3));
        $this->payment(['reference' => 'MTR-31'], completed: true, processedAt: now()->subMonths(4));

        $this->actingAs($admin)->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $recent->id)
                ->where('filters.from', now()->subDays(30)->toDateString())
            );
    }

    /**
     * El pago colgado no tiene fecha del autorizador. Si el rango mirara solo
     * `processed_at` desaparecería justo del listado donde se lo busca.
     */
    public function test_a_hung_payment_without_processed_at_still_falls_inside_the_date_range(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $hung = $this->payment(['status' => PaymentStatus::Processing, 'created_at' => now()->subDays(2)]);
        $this->payment([], completed: true, processedAt: now()->subMonths(2));

        $from = now()->subDays(4)->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin)->get($this->url("?from={$from}&to={$to}"))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $hung->id)
            );
    }

    public function test_a_member_without_the_permission_is_rejected_on_every_route(): void
    {
        $operator = $this->memberWithRole(UserRole::Operator);
        $payment = $this->payment([], completed: true);

        $this->actingAs($operator)->get($this->url())->assertForbidden();
        $this->actingAs($operator)->get($this->url('/'.$payment->id))->assertForbidden();
    }

    public function test_sales_can_list_but_cannot_query_the_gateway(): void
    {
        $sales = $this->memberWithRole(UserRole::Sales);
        $this->payment([], completed: true);

        $this->actingAs($sales)->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.query', false));
    }

    public function test_a_transaction_of_another_tenant_is_not_found(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $other = Tenant::factory()->create(['slug' => 'other', 'domain' => 'other.montree.test']);
        TenantConfiguration::factory()->for($other)->create();
        $other->makeCurrent();
        $foreignTour = Tour::factory()->create();
        $foreignDeparture = TourDate::factory()->for($foreignTour)->create(['starts_at' => now()->addWeek()]);
        $foreign = $this->payment([], completed: true, departure: $foreignDeparture);
        $this->tenant->makeCurrent();

        $this->actingAs($admin)->get($this->url('/'.$foreign->id))->assertNotFound();
        $this->actingAs($admin)->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('transactions.data', 0));
    }

    public function test_the_detail_ships_the_authorizer_data_of_the_transaction(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $payment = $this->payment([
            'reference' => 'MTR-17',
            'request_id' => '4242',
            'internal_reference' => '1234567',
            'authorization' => '000000',
            'receipt' => '5551234',
            'franchise' => 'CR_VS',
            'payment_method' => 'visa',
            'payment_method_name' => 'Visa',
            'issuer_name' => 'BANCOLOMBIA',
            'processor_fields' => [
                ['keyword' => 'bin', 'value' => '424242'],
                ['keyword' => 'lastDigits', 'value' => '4242'],
            ],
        ], completed: true);

        $this->actingAs($admin)->get($this->url('/'.$payment->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Transactions/Show', false)
                ->where('transaction.reference', 'MTR-17')
                ->where('transaction.request_id', '4242')
                ->where('transaction.internal_reference', '1234567')
                ->where('transaction.authorization', '000000')
                ->where('transaction.receipt', '5551234')
                ->where('transaction.franchise', 'CR_VS')
                ->where('transaction.payment_method_name', 'Visa')
                ->where('transaction.issuer_name', 'BANCOLOMBIA')
                ->where('transaction.last_digits', '4242')
                ->where('transaction.is_queryable', true)
                ->where('transaction.booking.booking_number', $payment->booking->booking_number)
                ->where('transaction.booking.due_amount', '0.00')
            );
    }

    public function test_a_manual_payment_shows_its_reference_and_no_gateway_fields(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $payment = $this->payment([
            'gateway' => PaymentGateway::Manual,
            'request_id' => null,
            'reference' => 'Consignación 4471',
            'status' => PaymentStatus::Completed,
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)->get($this->url('/'.$payment->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('transaction.reference', 'Consignación 4471')
                ->where('transaction.request_id', null)
                ->where('transaction.authorization', null)
                ->where('transaction.is_queryable', false)
            );
    }

    /**
     * `process_url` es un enlace de cobro vivo y `gateway_response` un volcado
     * con datos del pagador. Ninguno de los dos —ni el BIN— sale al navegador.
     */
    public function test_the_live_checkout_link_the_raw_dump_and_the_card_bin_never_reach_the_client(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $payment = $this->payment([
            'process_url' => 'https://checkout.test/session/4242/secret',
            'gateway_response' => ['payer' => ['document' => '1020304050'], 'requestId' => 4242],
            'processor_fields' => [
                ['keyword' => 'bin', 'value' => '424242'],
                ['keyword' => 'lastDigits', 'value' => '4242'],
            ],
        ], completed: true);

        foreach ([$this->url(), $this->url('/'.$payment->id)] as $url) {
            $response = $this->actingAs($admin)->get($url);

            $response->assertOk();
            // Control positivo: sin esto el test seguiría en verde con 0 filas
            // o con un Resource que dejó de emitir la transacción.
            $response->assertSee($payment->reference);
            $response->assertDontSee('process_url');
            $response->assertDontSee('gateway_response');
            $response->assertDontSee('checkout.test');
            $response->assertDontSee('1020304050');
            $response->assertDontSee('424242');
        }
    }

    /**
     * Una página de 25 filas necesita la reserva, el titular, el tour y la
     * salida de cada una: si eso no va por eager loading son 25 + N consultas.
     */
    public function test_a_full_page_of_transactions_costs_the_same_queries_as_a_short_one(): void
    {
        $admin = $this->memberWithRole(UserRole::Admin);

        $this->payment(['reference' => 'MTR-1'], completed: true);

        // La primera petición paga sesión y permisos, que no son del listado.
        $this->countQueriesListing($admin, expected: 1);
        $shortPage = $this->countQueriesListing($admin, expected: 1);

        foreach (range(2, 25) as $index) {
            $this->payment(['reference' => "MTR-{$index}"], completed: true);
        }

        $fullPage = $this->countQueriesListing($admin, expected: 25);

        // El número exacto depende de sesión y permisos, que no son del listado.
        // Lo que fija la ausencia de N+1 es que no crezca con las filas.
        $this->assertSame(
            $shortPage,
            $fullPage,
            "Una fila costó {$shortPage} consultas y 25 costaron {$fullPage}: el listado escala con las filas.",
        );
    }

    private function countQueriesListing(User $admin, int $expected): int
    {
        $queries = 0;
        $count = function () use (&$queries): void {
            $queries++;
        };

        DB::listen($count);

        $this->actingAs($admin)->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('transactions.data', $expected));

        DB::flushQueryLog();

        return $queries;
    }

    private function url(string $path = ''): string
    {
        return 'http://demo.montree.test/admin/transactions'.$path;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(
        array $attributes = [],
        bool $completed = false,
        ?CarbonInterface $processedAt = null,
        ?TourDate $departure = null,
    ): Payment {
        $departure ??= $this->departure;

        $booking = Booking::factory()
            ->for(User::factory())
            ->for($departure->tour)
            ->for($departure, 'tourDate')
            ->confirmed()
            ->create(['total_amount' => '900000.00', 'paid_amount' => '900000.00', 'currency' => 'COP']);

        $factory = Payment::factory()->for($booking);

        if ($completed) {
            $factory = $factory->completed();
        }

        if ($processedAt !== null) {
            $attributes['processed_at'] = $processedAt;
        }

        return $factory->create($attributes);
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
