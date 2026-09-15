# Ingresos por medio de pago — Plan técnico

## 1. Resumen

Tres frentes: partir el enum de medio de pago, sumar el desglose al cálculo de
ingresos, y mover el dashboard de API a Inertia. No hay columnas nuevas: cambian
los valores de `payments.gateway` y se agrupa por esa columna, que ya está
indexada por `(tenant_id, gateway, status)`.

## 2. Backend

### Enum

`App\Enums\PaymentGateway`: `PlaceToPay`, `Cash`, `Transfer`. Se elimina
`Manual`. Se agrega `isGateway(): bool` (true solo para `PlaceToPay`) para que
nadie repita la comparación por string.

**Deuda anotada, no se paga acá:** el enum se llama `PaymentGateway` y la columna
`gateway`, pero dos de los tres valores no son pasarelas. El nombre honesto sería
`PaymentSource` / `source`. Renombrarlo toca ~30 archivos entre backend, tests,
tipos de TS y el módulo de transacciones, y no aporta a lo pedido. Si algún día
se renombra, es un PR mecánico y aislado.

Consumidores a actualizar (grep `PaymentGateway::Manual` y `'manual'`):
`RegisterManualPaymentAction`, `PaymentFactory`, tests de pagos y transacciones,
seeders si crean pagos manuales, y `Admin/Transactions/Show.vue`.

### Cálculo de ingresos

`App\Services\Dashboard\RevenueCalculator`:

- Nuevo método privado `byMethod(Carbon $start, Carbon $end, string $gross): array`
  que agrupa `collectedPayments()` por `gateway` con `selectRaw('gateway, SUM(amount) as total')`
  y **rellena los medios sin movimientos en cero**, recorriendo `PaymentGateway::cases()`
  para fijar el orden. Devuelve `list<array{method, label, amount, share_pct}>`.
- `share_pct`: `(int) round(amount / gross * 100)` con corte por `gross === '0.00'`.
- `RevenueBreakdown` (en `app/Data/Dashboard/`) suma `public array $byMethod`.

Una sola consulta agregada más por carga del dashboard. No hay N+1.

### El dashboard pasa a Inertia

- **Nuevo** `App\Http\Controllers\DashboardPagesController` (`__invoke`), en la
  raíz de `app/Http/Controllers` como el resto de los `*PagesController`.
  Recibe `DashboardRequest` (se mueve a `App\Http\Requests\Admin\Dashboard\`, ya
  vive ahí) y devuelve `Inertia::render('Admin/Dashboard', [...])`.
- `routes/web.php`: `Route::inertia('dashboard', 'Admin/Dashboard')` pasa a
  `Route::get('dashboard', DashboardPagesController::class)`. Mantiene
  `can:dashboard.view` heredado del grupo.
- **Se elimina** `Api\V1\Admin\DashboardController`, su ruta y
  `DashboardResource` si no queda otro consumidor. El snapshot se serializa con
  el mismo Resource si se reutiliza; si no, se arma en el controller.
- `Api\V1\Admin\RevenueReportController` **se queda**: es una descarga.

### Pago manual con medio

- `RegisterManualPaymentRequest`: `method` required, `Rule::enum` acotado a
  `Cash` y `Transfer` (no puede llegar `placetopay` por acá). Accessor
  `method(): PaymentGateway`.
- `RegisterManualPaymentAction::handle()` recibe el medio y lo usa como
  `gateway`. El resto no cambia.
- `BookingPaymentController` pasa el nuevo argumento.

## 3. Frontend

- `resources/js/pages/Admin/Dashboard.vue`: deja de hacer fetch en `onMounted`.
  Recibe `snapshot`, `periods` y `filters` por props. Se eliminan el estado de
  carga inicial y el manejo de error de red; queda el estado de refresco durante
  la visita de Inertia.
- `molecules/PeriodSelector.vue`: sus opciones salen de la prop `periods`, no de
  una lista duplicada a mano.
- **Nuevo** `organisms/RevenueByMethod.vue`: una fila por medio con etiqueta,
  monto y porcentaje, más una barra proporcional. Estado vacío cuando el bruto
  es cero.
- `molecules/PassengerDrawer.vue`: selector de medio (efectivo / transferencia)
  sin valor por defecto, antes del monto. La etiqueta del campo de referencia
  cambia según el medio.
- `types/dashboard.ts`: `RevenueByMethodRow` y `by_method` en `DashboardRevenue`;
  props de la página. `types/passenger.ts`: `method` en `ManualPaymentInput`.

## 4. Tests

- `tests/Unit/Services/Dashboard/RevenueCalculatorTest.php`: el desglose suma el
  bruto; un medio sin movimientos viene en cero; con bruto cero los porcentajes
  son cero y no se divide; un reembolsado cuenta en su medio.
- **Reescribir** `tests/Feature/Api/V1/Admin/DashboardControllerTest.php` como
  `tests/Feature/Admin/DashboardPageTest.php` con `assertInertia`: payload
  completo, periodo inválido 422, 403 sin permiso, aislamiento por tenant.
- `tests/Feature/DashboardTest.php`: sigue cubriendo el guard de la ruta.
- `tests/Feature/Payments/ManualPaymentTest.php`: se registra un pago en efectivo
  y otro por transferencia y cada uno queda con su `gateway`; sin `method` → 422.
- `tests/Feature/Admin/TransactionPagesTest.php`: el filtro por `cash` no trae
  los de `transfer`.

## 5. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| Filas con `gateway = manual` rompen el cast | alta en entornos ya montados | `migrate:fresh --seed`; el proyecto está en desarrollo y es lo que venimos haciendo |
| Quitar el endpoint de API rompe un consumidor oculto | baja | `git grep` de la ruta y de la acción de Wayfinder antes de borrar |
| El desglose y el bruto se desincronizan | media | Test que exige que la suma de los medios iguale `gross` |
| Los porcentajes no suman 100 | alta por redondeo | Es aceptable y está en la spec: el monto manda |

## 6. Orden de ejecución

1. `montree-backend-dev` — §2 y §4. No hay schema nuevo, el db-architect no corre.
2. `montree-frontend-dev` — §3.
3. `montree-reviewer`.

## Changelog

- `2026-09-13` — Creación.
