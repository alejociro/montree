# F007 — Plan técnico

> Decisiones técnicas para cerrar la integración con PlacetoPay Checkout.
> Referencia de buenas prácticas: `../../projects/microsites` (mismo patrón:
> sesión por comercio, notificación firmada que re-consulta la sesión,
> retorno que consulta, comando de barrido). Este plan se ejecuta con los
> sub-agents de `.claude/agents/` en el orden de §8.

---

## 1. Resumen

La rama `feature/implement-placetopay-payment` ya trae la integración base
funcionando y testeada (48 tests en `tests/Feature/Payments/`): sesión por
tenant con `dnetix/redirection`, retorno firmado, notificación con firma
`sha1|sha256|sha512(requestId+status+date+tranKey)` que encola
`ResolvePaymentJob`, y `payments:check` programado. **No se reescribe.**

Lo que falta es cerrar tres cosas de negocio y limpiar el esquema:

1. **Mínimo de abono por salida** (`tour_dates.min_payment_pct`, fallback al
   `min_partial_payment_pct` de la agencia).
2. **Abono ≥ mínimo confirma la reserva** con saldo pendiente (hoy solo
   confirma cuando `paid >= total`). La regla se comparte entre el pago por
   pasarela y el pago en efectivo del guía → se extrae a un Service.
3. **Esquema aplanado y sin Stripe**: columnas de PlacetoPay dentro de las
   migraciones base, Cashier fuera del proyecto, reembolso fuera del scope.

## 2. Estado actual (qué se conserva tal cual)

| Pieza | Archivo | Equivalente en microsites |
|---|---|---|
| Cliente por comercio | `app/Services/PlaceToPay/TenantCheckoutClientFactory.php` + `CheckoutCredentials.php` | `CheckoutHelper::instance()` + `CheckoutServiceProvider` |
| Abrir sesión | `app/Actions/Payment/CreatePaymentSessionAction.php` | `PaymentServiceImpl::request()` + `requestSession()` |
| Consultar y resolver | `app/Actions/Payment/ResolvePaymentAction.php` | `PaymentServiceImpl::query()` + `fillFromCheckoutTransaction()` |
| Notificación | `PaymentNotificationController` + `NotificationRequest` + `NotificationSignature` | `NotificationController::receive()` + `isValidRequest()` |
| Job de resolución | `app/Jobs/ResolvePaymentJob.php` (`WithoutOverlapping`, `NotTenantAware`) | `ProcessPaymentNotification` |
| Retorno | `PaymentReturnController` (ruta `signed`, GET\|POST) | `PaymentController::returnUrl()` + `settlePayment()` |
| Barrido | `app/Console/Commands/CheckPendingPaymentsCommand.php` | `CheckPayments` (`payment:check`) |
| Fake en tests | `tests/Support/FakeCheckout.php` (SDK real sobre `MockHandler`) | `PlacetoPayFake` + `WithPlacetopayService` |
| Pago en efectivo (admin) | `RegisterManualPaymentAction` + `BookingPaymentController` + `PassengerDrawer.vue` | — |
| Credenciales en el panel | `UpdateTenantConfigurationAction` + `PaymentGatewayForm.vue` | `Site::getAuthentication()` |

Diferencias con microsites que se mantienen a propósito (son mejores o
exigidas por la constitución): Form Request para la notificación en vez de
validar en el controller; `hash_equals` en la firma; returnUrl con firma de
Laravel en vez de `code` derivado del id; una sola credencial por tenant (sin
rotación con `expireOn`).

## 3. Backend

### Modelos

- `TourDate` — nueva columna `min_payment_pct` (`?int`, fillable, cast
  `integer`).
- `Booking` — accessor `min_payment_amount` pasa a leer
  `tourDate->min_payment_pct ?? tenant->configuration->min_partial_payment_pct`.
  Nuevo accessor `deposit_amount` (pct × total, **sin** recortar al saldo): es
  el umbral que confirma la reserva. `min_payment_amount` sigue siendo
  `min(deposit_amount, due_amount)`: el piso del próximo abono.
- `Payment` — sin cambios de columnas respecto a la rama; solo se reubican en
  la migración base.
- `User` — quitar `use Billable`.
- `PaymentGateway` — quitar el case `Stripe`. Quedan `PlaceToPay` y `Manual`.

### Migrations (las hace `montree-db-architect`; ver §7)

Proyecto en desarrollo: se **editan las migraciones base** y se eliminan las
incrementales. Todo entorno hace `migrate:fresh --seed`.

- `2026_05_17_170011_create_payments_table.php` — reemplazar el bloque de
  columnas por el esquema final que hoy arma
  `2026_08_26_212824_add_placetopay_columns_to_payments_table.php`
  (`request_id`, `internal_reference`, `reference`, `gateway_status`,
  `process_url`, `session_expires_at`, `authorization`, `receipt`,
  `franchise`, `payment_method`, `payment_method_name`, `issuer_name`,
  `processor_fields`, `status_message`; default de `gateway` = `placetopay`;
  índices único `(tenant_id, gateway, request_id)`, `reference`,
  `(tenant_id, gateway_status)`, `(tenant_id, gateway, status)`,
  `(tenant_id, processed_at)`, `(booking_id, status)`). Quitar
  `refunded_amount`, `refund_reason`, `refunded_at` (reembolso fuera de scope).
- `2026_05_17_170001_create_tenant_configurations_table.php` — agregar
  `placetopay_login` (string nullable), `placetopay_tran_key` (text nullable),
  `placetopay_url` (string nullable) después de `min_partial_payment_pct`.
- `2026_05_17_170007_create_tour_dates_table.php` — agregar
  `min_payment_pct` `unsignedTinyInteger()->nullable()` después de
  `price_override`.
- **Eliminar**: las dos `2026_08_26_212824_*` y las cuatro de Cashier
  (`2026_05_17_155230` a `155233`).

### Dependencias

- `composer remove laravel/cashier` (aprobado por el usuario en esta sesión).
  Borrar `resources/js/actions/Laravel/Cashier/` (Wayfinder lo regenera sin
  esa carpeta).

### Services

- `App\Services\Payments\BookingSettlementService` — **nuevo**. Regla del 3
  cumplida por 2 actions: `ResolvePaymentAction::settleBooking()` y
  `RegisterManualPaymentAction` tienen hoy la misma lógica duplicada.
  Método único `apply(Booking $booking, Payment $payment): Booking`. Recibe
  la reserva ya bloqueada con `lockForUpdate()` (lo hace el caller dentro de
  su transacción) y:
  1. `paid = bcadd(paid_amount, payment.amount)`.
  2. `isSettled = paid >= total_amount`.
  3. `coversDeposit = paid >= deposit_amount`.
  4. `status`: si estaba `PendingPayment` y (`isSettled || coversDeposit`) →
     `Confirmed`; si no, no cambia. Una reserva `Completed`/`Confirmed` nunca
     retrocede.
  5. `confirmed_at` se fija la primera vez que pasa a `Confirmed`.
  6. `expires_at` → `null` al confirmar (el hold de 30 min ya cumplió).
  7. `payment_type` = tipo del pago aplicado.
  8. Devuelve `bool` `wasJustConfirmed` vía un DTO chico
     `App\Data\SettlementResult { readonly Booking $booking; readonly bool $wasJustConfirmed; }`
     para que `ResolvePaymentAction` dispare `BookingConfirmedNotification`
     solo esa vez (el pago manual no notifica; ver contrato de
     `tours-admin-passengers`).

### Actions

- `App\Actions\Payment\CreatePaymentSessionAction` — sin cambios de lógica.
  Verificar que el `min:` de `StartPaymentRequest` ya sale del accessor (sí).
- `App\Actions\Payment\ResolvePaymentAction` — reemplazar `settleBooking()`
  por `BookingSettlementService::apply()`. Notificar cuando
  `wasJustConfirmed`.
- `App\Actions\Payment\RegisterManualPaymentAction` — **mover** desde
  `App\Actions\Payments\` (namespace inconsistente) y usar el service.
- `App\Actions\Payment\RefundPaymentAction` — **eliminar**.

### Form Requests

- `StoreTourDateRequest` / `UpdateTourDateRequest` — agregar
  `min_payment_pct => ['nullable', 'integer', 'min:1', 'max:100']`.
- `StartPaymentRequest`, `NotificationRequest`, `RegisterManualPaymentRequest`
  — sin cambios (el mínimo ya sale de `Booking::$min_payment_amount`).

### Controllers

- `PaymentRefundController` — **eliminar** junto con su ruta y el permiso
  `payments.refund` (`PermissionCatalog`, `RolesAndPermissionsSeeder`,
  `PermissionCatalogSeederTest`).
- `BookingPagesController::show()` — el prop `min_partial_payment_pct` pasa
  a ser el porcentaje **efectivo** de la salida (`tourDate->min_payment_pct
  ?? config pct`). Renombrar el prop a `min_payment_pct` para que el front no
  lo confunda con el de la agencia.
- `TourDateController` (admin) — sin cambios: el campo entra por el request
  y sale por el resource.

### Resources

- `TourDateDetailResource` — agregar `min_payment_pct` (crudo, nullable) y
  `effective_min_payment_pct`.
- `PaymentResource` (`app/Http/Resources/Payment/PaymentResource.php`) —
  solo lo usaba el refund. **Eliminar** si no queda otro consumidor.
- `TenantConfigurationResource` — sin cambios.

### Comando

- Renombrar `payments:check` → `payment:check` (nomenclatura de microsites).
  Actualizar `routes/console.php`, el test y la spec. Mantener
  `--date-from` / `--date-to`, ventana por defecto
  (`lookback_hours`, `settle_margin_minutes`) y el `try/catch` por pago.

### Jobs / Notifications

- `ResolvePaymentJob` — sin cambios.
- `BookingConfirmedNotification` — se dispara desde `ResolvePaymentAction`
  cuando `wasJustConfirmed`, incluido el caso de abono parcial.

### Limpieza de restos de Stripe

- `bootstrap/app.php`: comentario "External webhooks (Stripe etc.)".
- `docs/constitution.md` §1 (paquetes: quitar Cashier), §3.2 (ejemplo
  `StripePaymentGateway` → `PlaceToPayCheckoutClient`), §6.
- `docs/testing-policy.md` §"Tests de webhook de Stripe" → notificación de
  PlacetoPay.
- `docs/api-conventions.md:85` → idempotencia por `(tenant_id, gateway,
  request_id)`.
- `lang/en.json`: entradas de Stripe si existen.

## 4. Frontend

### Pages

- `resources/js/pages/Booking/Show.vue` — leer `booking.min_payment_pct`
  (antes `min_partial_payment_pct`). El texto del selector de abono dice
  «Mínimo {pct}% para esta salida». Sin cambios de flujo: sigue `router.post`
  a `startPayment(bookingNumber)` y el servidor responde `Inertia::location`.
- `resources/js/pages/Booking/Create.vue` — sin cambios.

### Organisms

- `TourDateFormDialog.vue` — nuevo campo numérico opcional «Mínimo de abono
  (%)» con placeholder «Por defecto: {pct de la agencia}%». Vacío → `null`.
  El pct de la agencia llega como prop del page que abre el diálogo (ya
  recibe la configuración del tenant en `Tour/Show.vue`; si no, agregarlo a
  los props de la página).
- `PassengerDrawer.vue` — sin cambios funcionales. Después de registrar
  efectivo, el chip de estado de la reserva refleja `confirmed` cuando el
  abono cubre el mínimo (viene en `BookingBalanceResource.status`).

### Types

- `resources/js/types/tour-detail.ts` y `logistics.ts` — `min_payment_pct:
  number | null` y `effective_min_payment_pct: number` en el shape del
  tour date; `min_payment_pct?: number | null` en el payload del form.
- `resources/js/types/booking.ts` (o donde viva el shape de `Booking/Show`)
  — `min_partial_payment_pct` → `min_payment_pct`.
- `enums.generated.ts` — regenerar con `php artisan enums:typescript` (sale
  `stripe` de `PAYMENT_GATEWAY_VALUES`).

### Wayfinder

- Tras backend: `php artisan wayfinder:generate`. Debe desaparecer
  `@/actions/App/Http/Controllers/Api/V1/Admin/PaymentRefundController` y
  `@/actions/Laravel/Cashier`.

## 5. Tests

### Ajustar (cambian de comportamiento)

- `tests/Feature/Payments/PlaceToPayReturnTest.php` —
  `test_an_approved_partial_payment_keeps_the_booking_pending` se divide en:
  - `test_an_approved_partial_payment_at_or_above_the_minimum_confirms_the_booking_with_balance_due`
  - `test_an_approved_partial_payment_below_the_minimum_keeps_the_booking_pending`
    (solo alcanzable si la pasarela aprobó menos de lo pedido; usar
    `queryApproved()` con un total menor).
- `tests/Feature/Payments/ManualPaymentTest.php` — mismo par para efectivo.
- `tests/Feature/Payments/CheckPendingPaymentsCommandTest.php` — nuevo
  nombre `payment:check`.
- `tests/Feature/Rbac/PermissionCatalogSeederTest.php` — sin
  `payments.refund`.

### Nuevos

- `tests/Feature/Payments/PlaceToPayCheckoutTest.php`:
  - `test_the_minimum_deposit_comes_from_the_departure_when_it_is_set`
  - `test_the_minimum_deposit_falls_back_to_the_agency_percentage`
- `tests/Feature/Api/V1/Admin/TourDateControllerTest.php` (existente):
  - happy: crea salida con `min_payment_pct = 50` y el resource lo devuelve
    junto a `effective_min_payment_pct`.
  - failure: `min_payment_pct = 0` y `= 101` → 422.
  - edge: `null` explícito limpia el override y `effective_min_payment_pct`
    vuelve al de la agencia.
- `tests/Feature/BookingPagesTest.php` (o el que cubra `booking.show`):
  el prop `min_payment_pct` refleja el de la salida.
- `tests/Unit/Services/BookingSettlementServiceTest.php` — clase pura de
  reglas de dinero, vale test unitario: settled, cubre depósito, no cubre,
  no retrocede desde `Completed`, `confirmed_at` no se pisa.

### Regresión

- `php artisan test --compact` completo al cerrar (migraciones base
  cambiaron; `CompleteJourneyTest` y `TenantConfigurationControllerTest`
  deben seguir verdes).

## 6. Decisiones tomadas

- **Mínimo por salida como porcentaje** con fallback al de la agencia.
  Razón: una sola unidad de medida (`%`) en toda la app y una sola columna
  nullable; el monto se deriva del total de la reserva como hoy.
- **Abono ≥ mínimo → `confirmed`**. Razón: «mínimo necesario» significa que
  la plaza queda asegurada; el saldo se cobra por web o en efectivo con el
  guía. Es lo que decía la spec original y el código no lo cumplía.
- **Service para el asiento del pago**. Razón: dos actions (pasarela y
  efectivo) aplicaban la misma regla con copias divergentes; la constitución
  pide Service a partir de 2 usos.
- **Esquema aplanado + `composer remove laravel/cashier`**. Razón: proyecto
  en desarrollo; menos migraciones que leer y cero código muerto de Stripe.
- **Reembolso fuera de scope**. Razón: no lo pide el negocio, no tiene UI y
  la versión actual mentía (marcaba BD sin devolver plata). Cuando haga
  falta se implementa con `reverse(internalReference)` como en microsites.
- **Notificación se mantiene** (la spec decía «sin webhook», estaba
  desactualizada). Razón: es lo que hace microsites y cierra los pagos sin
  esperar al barrido.
- **Sin cambios al fake de tests**: `FakeCheckout` ejercita el SDK real
  sobre `MockHandler`, más fiel que el `PlacetoPayFake` de microsites.

## 7. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| Editar migraciones base rompe un entorno con datos | baja (desarrollo) | Anunciar `migrate:fresh --seed` en el PR; seeders deben cubrir credenciales de prueba |
| Quitar `Billable` rompe algo que usa Cashier sin que lo sepamos | baja | `grep -rn "Cashier\|Billable\|stripe"` antes y después; suite completa |
| Confirmar con abono cambia expectativas de tests de reservas/manifiesto | media | Correr `tests/Feature/Payments`, `CompleteJourneyTest` y los de manifiesto tras el service |
| `Booking::$min_payment_amount` genera N+1 al listar reservas | media | Accessor usa `loadMissing('tourDate', 'tenant.configuration')`; `BookingPagesController` y el manifiesto cargan las relaciones con `with()` |
| Pasarela aprueba menos de lo pedido (`APPROVED_PARTIAL`) y no cubre el mínimo | baja | La reserva queda `pending_payment` con el `expires_at` intacto; el flujo de expiración existente la libera |

## 8. Orden de ejecución (para Opus)

1. **`montree-db-architect`** — §3 Migrations + Dependencias + `User`/enum.
   Entrega: `migrate:fresh --seed` verde, `enums:typescript` regenerado,
   factories actualizadas (`TourDateFactory::withMinPaymentPct()`).
2. **`montree-backend-dev`** — §3 resto + §5 tests + limpieza Stripe.
   Entrega: `php artisan test --compact` verde, Pint verde,
   `wayfinder:generate` corrido.
3. **`montree-frontend-dev`** — §4. Entrega: `types:check`, `lint`, `format`
   verdes; probado en navegador el abono desde `Booking/Show` y el campo
   nuevo en `TourDateFormDialog`.
4. **`montree-reviewer`** — go/no-go contra spec + constitución.
5. Commit único por capa o squash al final; PR a `main`.

## 9. Out of scope explícito

- Reembolsos (endpoint, action, permiso): eliminados hasta que el negocio
  los pida.
- Recordatorio del saldo 48h antes de la salida (criterio viejo de la spec):
  va con F008 (notificaciones), no acá.
- Rotación de credenciales con `expireOn` como microsites: una sola
  credencial por tenant.
- Historial de transacciones múltiples por sesión (`processHistory` de
  microsites): `gateway_response` guarda la foto completa; una tabla de
  historial se hace cuando exista un reporte que la necesite.
- Reintento de sesión desde el admin: el cliente reintenta desde
  `Booking/Show`.

## Changelog

- `2026-09-12` — Creación. Plan de cierre sobre la integración ya presente
  en la rama; decisiones tomadas con el usuario en sesión (mínimo por salida
  en %, abono confirma, esquema aplanado sin Cashier, reembolso fuera).
