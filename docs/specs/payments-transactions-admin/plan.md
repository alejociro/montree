# Transacciones en el panel — Plan técnico

## 1. Resumen

Módulo de solo lectura sobre `payments` más una acción de reconsulta. Dos pages
Inertia servidas por rutas web (`/admin/transactions`), con filtros en la query
string y paginación del servidor. No hay schema nuevo, no hay API, y la
resolución del pago reusa `ResolvePaymentAction` tal cual: el panel no abre un
segundo camino para actualizar un pago.

## 2. Backend

### Permisos (RBAC)

Módulo nuevo `payments` con dos permisos. Tocar los tres lugares a la vez:

- `database/seeders/RolesAndPermissionsSeeder::PERMISSIONS` → `'payments' => ['payments.view', 'payments.query']`
- `RolesAndPermissionsSeeder::ROLE_PERMISSIONS` → `sales` suma `payments.view`
  (vende y concilia, pero no toca la pasarela). `operator` y `guide` no los reciben.
  `admin` recibe el catálogo completo sin listarse.
- `App\Services\Rbac\PermissionCatalog::MODULE_LABELS` → `'payments' => 'Pagos'`
- `PermissionCatalog::LABELS` → `'payments.view' => 'Ver transacciones'`,
  `'payments.query' => 'Consultar el estado en la pasarela'`

El catálogo pasa de **38 a 40** permisos: actualizar `CATALOG_SIZE` y la
enumeración de `tests/Feature/Rbac/PermissionCatalogSeederTest.php`, y revisar
`RoleManagementTest` e `InertiaAuthUserPropTest`, que heredan ese conteo.
`resources/js/config/permissions.ts` también lista los slugs.

### Controllers

Los controllers de páginas viven en la raíz de `app/Http/Controllers` con sufijo
`PagesController` (convención del repo: `TourPagesController`, `TeamPagesController`).

- `App\Http\Controllers\TransactionPagesController`
  - `index(TransactionIndexRequest $request): Response`
  - `show(Payment $payment): Response`
- `App\Http\Controllers\QueryTransactionController` — acción única `__invoke`.
  No es RESTful, así que va en su propio controller (constitución §3.2).

Máximo 10 líneas por método: el armado de la query va en un scope del modelo, no
en el controller.

### Form Requests

- `App\Http\Requests\Admin\Transaction\TransactionIndexRequest`
  - `authorize()`: `$this->user()?->can('payments.view')`
  - `rules()`: los de `contracts.md`. `tour_date_id` con
    `Rule::exists('tour_dates', 'id')->where('tenant_id', ...)`.
  - Accessores tipados: `search()`, `status()`, `gateway()`, `tourDateId()`,
    `from()`, `to()`, y `filters(): array` para devolver al front lo aplicado.

La reconsulta no recibe input: no lleva Form Request, la autorización va en la
ruta (`can:payments.query`) y la regla de negocio en la Action.

### Modelos

`Payment` suma scopes de consulta (son scopes, no lógica de negocio, así que
respetan §3.2):

- `scopeMatching(Builder $q, ?string $search)` — `reference`, `request_id`,
  `internal_reference`, `authorization` y `booking_number` de la reserva.
- `scopeSettledBetween(Builder $q, ?CarbonInterface $from, ?CarbonInterface $to)`
  — rango sobre `processed_at`, cayendo a `created_at` cuando es `null`, para que
  un pago colgado no se pierda del filtro.
- `scopeForTourDate(Builder $q, ?int $tourDateId)`.
- Accessor `last_digits` derivado de `processor_fields.lastDigits`.
- Método `isQueryable(): bool` — `gateway === PlaceToPay && request_id !== null`.

### Actions

- `App\Actions\Payment\ResolvePaymentAction` — **sin cambios**, se reusa.
- `App\Actions\Payment\QueryTransactionAction` — envuelve la anterior y traduce el
  resultado a algo que el controller pueda convertir en flash:
  `handle(Payment $payment): TransactionQueryResult` (readonly en `app/Data/`, con
  `Payment $payment` y `bool $wasAlreadyResolved`). Lanza
  `PaymentException::notQueryable()` cuando `! $payment->isQueryable()`.

### Resources

- `App\Http\Resources\Admin\TransactionResource` — fila del listado.
- `App\Http\Resources\Admin\TransactionDetailResource` — detalle.

**Nunca** exponen `process_url` (enlace de cobro vivo) ni `gateway_response`
(volcado crudo con datos del pagador). De `processor_fields` sale solo
`lastDigits`; el BIN no viaja.

### Excepciones

`PaymentException::notQueryable()` — 422, código `PAYMENT_NOT_QUERYABLE`. El
handler Inertia-aware que ya existe en `bootstrap/app.php` la convierte en
`back()->with('error', ...)`, así que no hay que tocar nada más.

### Rutas (`routes/web.php`, grupo admin existente)

```php
Route::get('transactions', [TransactionPagesController::class, 'index'])
    ->middleware('can:payments.view')->name('transactions.index');
Route::get('transactions/{payment}', [TransactionPagesController::class, 'show'])
    ->middleware('can:payments.view')->name('transactions.show');
Route::post('transactions/{payment}/query', QueryTransactionController::class)
    ->middleware('can:payments.query')->name('transactions.query');
```

URLs en inglés como el resto del panel (`departures`, `logistics`, `promotions`).
El aislamiento por tenant lo da el global scope: `{payment}` de otra agencia no
resuelve y sale 404 solo.

### Planilla de pasajeros

- `TourDatePassengerController` carga `booking.payments` **solo** si
  `$request->user()->can('payments.view')`.
- `PassengerResource` expone `payments` con `whenLoaded`, así el Resource no filtra
  por rol.

### N+1

El listado carga `booking.tour` y `booking.tourDate` con `with()`. Verificar con
query log sobre una página de 25 filas: debe ser constante, no 25 + N.

## 3. Frontend

### Pages

- `resources/js/pages/Admin/Transactions/Index.vue` — filtros + tabla + paginación.
  Los filtros navegan con `router.get(index().url, filtros, { preserveState: true,
  replace: true, only: ['transactions', 'filters'] })`. Búsqueda con debounce de
  300 ms. **No** se usa `useApi()`: esto es Inertia, no `/api/v1`.
- `resources/js/pages/Admin/Transactions/Show.vue` — detalle en dos columnas
  (transacción / reserva y salida) y la acción de reconsulta con `router.post`.

### Componentes

- `molecules/CopyableValue.vue` — **nuevo**. Valor monoespaciado + botón copiar con
  `navigator.clipboard`, fallback silencioso y confirmación por toast. Se usa cinco
  veces en el detalle, así que nace justificado.
- `organisms/TransactionFilters.vue` — **nuevo**.
- `organisms/TransactionsTable.vue` — **nuevo**. Fila clickeable al detalle.
- `molecules/PaymentStatusChip.vue` — **ya existe**, se reutiliza.
- Paginación: reutilizar el componente que ya usan Departures/Reviews. Si no hay
  uno común, extraerlo NO es parte de este feature: usar el mismo patrón local.

### Menú

Ítem «Transacciones» en el sidebar del admin, visible con `payments.view`,
siguiendo cómo los demás ítems consultan permisos.

### Salida

- `PassengerDrawer.vue` — bloque «Transacciones» con referencia, medio, estado,
  monto y fecha, cada una enlazando al detalle. Solo si `payments` viene en el
  payload.
- Encima de `PassengerManifest.vue`, enlace «Ver transacciones de esta salida» a
  `index({ tour_date_id })` con Wayfinder.

### Types

`resources/js/types/transaction.ts` con `TransactionRow`, `TransactionDetail`,
`TransactionFilters`. Nada de `any`.

## 4. Tests

### Feature (backend)

`tests/Feature/Admin/TransactionPagesTest.php`
- happy: el listado devuelve las transacciones del tenant, más recientes primero.
- filtros: por estado, por medio, por salida, por rango de fechas, y búsqueda por
  referencia y por `requestId`.
- failure: sin `payments.view` → 403; el ítem no se ofrece.
- edge: pago colgado sin `processed_at` entra igual en el filtro por fechas.
- tenant isolation: una transacción de otra agencia → 404.
- seguridad: la respuesta **no** contiene `process_url` ni `gateway_response`.

`tests/Feature/Admin/QueryTransactionTest.php`
- happy: reconsultar un `processing` aprobado lo deja `completed` y acredita el saldo.
- failure: sin `payments.query` → 403.
- edge: reconsultar un pago manual → error, y no se llama a la pasarela.
- edge: reconsultar un pago ya resuelto no lo cambia ni duplica el saldo.
- edge: pasarela caída → mensaje de error, pago intacto.

`tests/Feature/Rbac/PermissionCatalogSeederTest.php` — catálogo en 40.

Planilla: sumar al test existente que el guía **no** recibe `payments` y el admin sí.

### Fake de pasarela

`tests/Support/FakeCheckout.php` tal cual: `queryApproved()`, `queryPending()`,
`serviceDown()` ya cubren lo que hace falta.

## 5. Decisiones tomadas

- **Inertia con props, sin API.** Razón: panel interno, sin terceros; y evita el
  error sistémico de `router.*` contra `/api/v1` que documenta la constitución §4.2.
- **Detalle en página propia.** Razón: URL compartible con el soporte de la pasarela.
- **Reconsulta reusa `ResolvePaymentAction`.** Razón: un solo camino de resolución,
  ya idempotente y probado; el panel no puede divergir del retorno ni del comando.
- **`process_url` y `gateway_response` no viajan al front.** Razón: uno es un
  enlace de cobro vivo y el otro un volcado con datos del pagador.
- **`payments.view` para `sales`, `payments.query` solo admin.** Razón: conciliar es
  tarea de ventas; tocar la pasarela, no.
- **Sin índices nuevos.** Razón: los cinco que dejó F007 cubren los filtros.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| La búsqueda por `internal_reference`/`authorization` no tiene índice | media | Volumen chico por tenant; si aparece lentitud, índice compuesto en un PR aparte. Documentado, no ignorado |
| Filtrar por fechas con `processed_at` nulo esconde pagos colgados | alta si se hace ingenuo | Scope con fallback a `created_at` + test dedicado |
| Reconsultar desde el panel duplica el saldo | baja | `ResolvePaymentAction` corta por `isResolved()` bajo `lockForUpdate`; hay test |
| Filtrar en el front con `useApi()` por costumbre del panel | media | Está escrito arriba y en tasks: acá es `router.get` con `only` |
| Exponer datos de tarjeta | baja | Solo `lastDigits`; test que verifica que el BIN y el volcado no salen |

## 7. Orden de ejecución

1. `montree-backend-dev` — §2 y §4 (no hay schema nuevo, el db-architect no corre).
2. `montree-frontend-dev` — §3, una vez que `wayfinder:generate --with-form` esté corrido.
3. `montree-reviewer` — go/no-go.

## 8. Out of scope explícito

Ver `spec.md`. Además: no se extrae un componente de paginación común, no se
agregan índices y no se toca el dashboard.

## Changelog

- `2026-09-12` — Creación.
