# F007 — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Derivado de `plan.md` §3–§5. Orden de ejecución: DB → Backend → Frontend → Review.

---

## DB (`montree-db-architect`) — primero

- [x] Editar `2026_05_17_170011_create_payments_table.php` con el esquema final de `plan.md` §3 (sin columnas de refund, default `gateway=placetopay`, índices de conciliación)
- [x] Editar `2026_05_17_170001_create_tenant_configurations_table.php`: `placetopay_login`, `placetopay_tran_key`, `placetopay_url`
- [x] Editar `2026_05_17_170007_create_tour_dates_table.php`: `min_payment_pct` unsignedTinyInteger nullable después de `price_override`
- [x] Borrar `2026_08_26_212824_add_placetopay_columns_to_payments_table.php` y `2026_08_26_212824_add_placetopay_credentials_to_tenant_configurations_table.php`
- [x] Borrar las 4 migraciones de Cashier (`2026_05_17_155230` … `155233`)
- [x] `composer remove laravel/cashier`; quitar `use Billable` de `app/Models/User.php`
- [x] Quitar case `Stripe` de `App\Enums\PaymentGateway`; `php artisan enums:typescript`
- [x] `TourDate`: `min_payment_pct` en `$fillable` + cast `integer`
- [x] `Payment`: quitar `refunded_amount`, `refund_reason`, `refunded_at` de fillable/casts. `PaymentStatus::Refunded` se queda (la pasarela puede reportar `REFUNDED` y `ResolvePaymentAction` lo mapea); quitar `PartiallyRefunded` y su uso en `isResolved()` si el grep no muestra otro productor
- [x] `TourDateFactory`: state `withMinPaymentPct(int $pct)`
- [x] `PaymentFactory`: revisar que no use columnas eliminadas
- [x] Borrar `resources/js/actions/Laravel/Cashier/`
- [x] `php artisan migrate:fresh --seed` verde
- [x] `php artisan test --compact` — anotar en "Notas" qué tests rompen por el cambio de regla (los arregla backend)

## Backend (`montree-backend-dev`)

### Regla de asiento
- [x] Crear `App\Data\SettlementResult` (readonly: `Booking $booking`, `bool $wasJustConfirmed`)
- [x] Crear `App\Services\Payments\BookingSettlementService::apply(Booking, Payment): SettlementResult` según `contracts.md` "Regla de asiento"
- [x] `Booking`: accessor `deposit_amount` (pct efectivo × total); `min_payment_amount` = `min(deposit_amount, due_amount)`; pct efectivo = `tourDate->min_payment_pct ?? tenant->configuration->min_partial_payment_pct ?? 30`; usar `loadMissing`
- [x] `ResolvePaymentAction`: reemplazar `settleBooking()` por el service; notificar solo si `wasJustConfirmed`
- [x] Mover `App\Actions\Payments\RegisterManualPaymentAction` → `App\Actions\Payment\`; usar el service; actualizar `BookingPaymentController`
- [x] Tests unitarios `tests/Unit/Services/BookingSettlementServiceTest.php` (settled, cubre depósito, no cubre, no retrocede desde `completed`, `confirmed_at` no se pisa)

### Mínimo por salida
- [x] `StoreTourDateRequest` / `UpdateTourDateRequest`: `min_payment_pct` nullable integer 1..100
- [x] `CreateTourDateAction` / `UpdateTourDateAction`: persistir el campo (null limpia)
- [x] `TourDateDetailResource`: `min_payment_pct` + `effective_min_payment_pct`
- [x] `BookingPagesController::show()`: prop `min_payment_pct` (efectivo), quitar `min_partial_payment_pct`; `with('tourDate')` ya cargado
- [x] Tests en `TourDateControllerTest`: happy (50 → resource lo devuelve), failure (0 y 101 → 422), edge (null limpia el override)
- [x] Tests en `PlaceToPayCheckoutTest`: mínimo sale de la salida; fallback al de la agencia

### Reembolso fuera
- [x] Borrar `PaymentRefundController`, `RefundPaymentAction`, ruta `payments/{payment}/refund`, `PaymentResource` (si no tiene otro consumidor)
- [x] Quitar `payments.refund` de `PermissionCatalog`, `RolesAndPermissionsSeeder`, `PermissionCatalogSeederTest`

### Comando y nomenclatura
- [x] Renombrar signature `payments:check` → `payment:check`; actualizar `routes/console.php` y `CheckPendingPaymentsCommandTest`

### Tests que cambian de comportamiento
- [x] `PlaceToPayReturnTest`: dividir `test_an_approved_partial_payment_keeps_the_booking_pending` en «≥ mínimo confirma con saldo» y «< mínimo sigue pendiente»
- [x] `ManualPaymentTest`: mismo par para efectivo; verificar que no se envía `BookingConfirmedNotification` en efectivo
- [x] `CompleteJourneyTest`, `TenantConfigurationControllerTest`, tests de manifiesto: verdes

### Limpieza Stripe
- [x] `bootstrap/app.php`: comentario de webhooks
- [x] `docs/constitution.md` §1, §3.2, §6; `docs/testing-policy.md` sección webhook; `docs/api-conventions.md:85`
- [x] `lang/en.json`: entradas huérfanas
- [x] `grep -rn "Stripe\|stripe\|Cashier\|Billable\|refund" app config routes database tests lang docs` sin restos

### Cierre
- [x] `php artisan wayfinder:generate --with-form` (vite tiene `formVariants: true`; sin el flag el `types:check` rompe con `Property 'form' does not exist`)
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact` completo
- [x] Marcar sección + notas + self-review (3 preguntas)

## Frontend (`montree-frontend-dev`)

- [x] `types/tour-detail.ts`, `types/logistics.ts`: `min_payment_pct: number | null`, `effective_min_payment_pct: number`; payload del form con `min_payment_pct?: number | null`
- [x] `TourDateFormDialog.vue`: campo «Mínimo de abono (%)» opcional, placeholder con el pct de la agencia, vacío → `null`, error `errors.min_payment_pct`
- [x] Verificar que el page que abre el diálogo recibe el pct de la agencia (agregar prop si no)
- [x] Tipo de props de `Booking/Show`: `min_partial_payment_pct` → `min_payment_pct`
- [x] `Booking/Show.vue`: texto «Mínimo {pct}% para esta salida»; mensaje de éxito con saldo pendiente si `due_amount > 0` tras confirmar
- [x] Confirmar que ningún archivo importa `PaymentRefundController` ni `Laravel/Cashier` de `@/actions`
- [x] `npm run types:check`
- [x] `npm run lint && npm run format`
- [ ] Navegador: crear salida con mínimo 50%, reservar, abonar 50% con `FakeCheckout`/sandbox → reserva `confirmed` con saldo; registrar efectivo del resto desde el manifiesto
- [x] Marcar sección + self-review (3 preguntas)

## Review (`montree-reviewer`)

- [x] Tests pasan
- [x] Pint pasa
- [x] Types check pasa
- [x] ESLint pasa
- [x] Spec cubierta 100% (criterios de `spec.md`)
- [x] Constitución respetada (Form Request, Action, Service con 2 usos, sin Repository)
- [x] Sin código muerto: grep de Stripe/Cashier/refund vacío
- [x] N+1: `Booking::$min_payment_amount` en listados (manifiesto, admin)
- [x] Reporte final con go/no-go

---

## Bloqueos / Decisiones pendientes

- (ninguna: las 4 decisiones se tomaron el 2026-09-12, ver `plan.md` §6)

## Notas durante implementación

- `2026-09-12` (principal): la integración base ya existía en la rama sin commits; este checklist parte de ese estado, no de cero.
- `2026-09-12` (db): Cashier traía **cinco** migraciones, no cuatro (`2026_05_17_155234_add_meter_event_name_to_subscription_items_table.php`). Se borraron las cinco y también `config/cashier.php` (el `package:discover` fallaba tras `composer remove`).
- `2026-09-12` (db): `resources/js/actions/Laravel/index.ts` importaba `./Cashier`; se editó a mano para que el build no rompa. `wayfinder:generate` (tarea de backend) lo dejará canónico.
- `2026-09-12` (db): consumidores de las columnas de reembolso que **no** estaban en el plan: `App\Services\Dashboard\RevenueCalculator::sumRefunds()` y `App\Actions\Dashboard\ExportRevenueReportAction` (F011). Se repararon con el mínimo cambio de esquema: los reembolsos se suman con `amount` + `processed_at` de los pagos en estado `refunded`. Backend/reviewer deben confirmar que es la semántica deseada ahora que no hay reembolsos parciales.
- `2026-09-12` (db): `PaymentStatus::PartiallyRefunded` se eliminó (no había productor; los únicos consumidores eran las dos queries de revenue y `Payment::isResolved()`).
- `2026-09-12` (db): quedan referenciando columnas inexistentes `App\Actions\Payment\RefundPaymentAction` y `App\Http\Resources\Payment\PaymentResource` — son borrados de la sección Backend, no se tocaron. El permiso `payments.refund` sigue en `RolesAndPermissionsSeeder` por lo mismo.
- `2026-09-12` (db): `migrate:fresh --seed` verde. `php artisan test --compact`: 765 tests, 3 fallos **preexistentes** en `tests/Unit/Http/Requests/TeamRequestMessagesTest` (mensajes en español, sin relación con el esquema). Ningún test falló por la regla de negocio nueva porque todavía no se implementó (la hace backend).
- `2026-09-12` (backend): la regla de asiento vive en `App\Services\Payments\BookingSettlementService` y devuelve `App\Data\SettlementResult`. La reserva llega bloqueada por el caller: `ResolvePaymentAction::settleBooking()` ya la tomaba con `lockForUpdate()` y `RegisterManualPaymentAction` **no lo hacía** — se agregó el bloqueo ahí dentro de su transacción, si no el service recibía una reserva sin proteger.
- `2026-09-12` (backend): `Booking::minPaymentPercentage()` es método público (no accessor) porque `BookingPagesController` y el resource lo necesitan como `int`; `deposit_amount` y `min_payment_amount` son los accessors de plata.
- `2026-09-12` (backend): `TourDateDetailResource::effective_min_payment_pct` usa `Tenant::current()?->configuration` y no `$this->tenant`: en el listado de salidas lo segundo era una consulta por fila.
- `2026-09-12` (backend): `StartPaymentRequest` ahora eager-loadea `tourDate` (el mínimo sale de la salida) y `BookingPagesController::show()` agrega `tenant.configuration`. Conteo medido en `booking.show`: 14 consultas, ninguna repetida por fila. El manifiesto de pasajeros no toca los accessors nuevos (usa `passengerShare()`), así que no cambió.
- `2026-09-12` (backend): tests que cambiaron de expectativa por la regla nueva, además de los previstos: `PlaceToPayReturnTest::test_an_amount_approved_below_the_requested_one_credits_only_what_was_charged` (70k sobre 100k cubre el 30% → ahora confirma con saldo).
- `2026-09-12` (backend): quitar `payments.refund` bajó el catálogo de permisos de 39 a 38 → se ajustaron `PermissionCatalogSeederTest::CATALOG_SIZE`, `RoleManagementTest` y (por herencia) `InertiaAuthUserPropTest`. También se borró la entrada de `resources/js/config/permissions.ts` y las dos traducciones huérfanas de `lang/en.json` (`TranslationCatalogTest` las detectó).
- `2026-09-12` (backend): `docs/specs/F016-tenant-onboarding/plan.md:169` menciona Cashier en una decisión histórica (D3). No se tocó: editar el plan de otro feature es trabajo de `montree-spec-updater`.
- `2026-09-12` (backend): suite completa 780 tests, 776 verdes. Fallan los 3 preexistentes de `TeamRequestMessagesTest` (idioma, ajenos) y `TeamDirectoryTest::test_exposes_the_last_access_and_every_role_of_a_member`, que es flaky por un segundo de diferencia y pasa aislado.
- `2026-09-12` (frontend): `min_payment_pct`/`effective_min_payment_pct` se agregaron **solo** a `types/logistics.ts` (`TourDateAdmin`, que es lo que devuelve `Admin\TourDateDetailResource`). `types/tour-detail.ts` describe `PublicTourResource::future_dates`, que no expone esos campos: agregarlos ahí sería un tipo mintiendo. Si el selector público llega a necesitar el mínimo por salida, hay que cambiar `contracts.md` + el resource público primero.
- `2026-09-12` (frontend): el pct de la agencia para el placeholder del diálogo sale de la prop compartida `tenantConfiguration` vía `useTenant()` (fallback 30). Ninguna de las dos páginas que abren `TourDateFormDialog` (`Admin/Tour/Edit.vue`, `Admin/Departures/Index.vue`) necesitó un prop nuevo del backend.
- `2026-09-12` (frontend): `npm run types:check` fallaba con 16 errores `Property 'form' does not exist` porque el `wayfinder:generate` del cierre de backend corrió sin `--with-form` y `vite.config.ts` usa `formVariants: true`. Se regeneró con `php artisan wayfinder:generate --with-form`. Vale la pena fijar el flag en el comando del checklist.
- `2026-09-12` (frontend): copy nuevo agregado a `lang/en.json` y clave vieja (`Paga al menos el :percent%…`) eliminada; `TranslationCatalogTest` verde (4 tests).
- `2026-09-12` (frontend): **sin verificación en navegador**. El entorno no está levantado (`:8000` y Vite `:5173` no responden; solo Herd sirve `montree.test` con assets construidos) y esta sesión no tiene las tools de Playwright MCP. Queda pendiente el paso «crear salida con mínimo 50% → reservar → abonar 50%».
- `2026-09-12` (reviewer): GO con condiciones. P1 corregidos por el principal: bloques `WHY` restaurados en `routes/web.php`; `BookingException` pasa por el mismo handler Inertia-aware que `PaymentException` (+ test `test_a_cancelled_booking_returns_to_the_page_with_the_error_instead_of_json`); ingresos F011: el bruto incluye pagos `refunded` (se cobraron) y `ResolvePaymentAction` conserva `processed_at` al mapear `REFUNDED`, así el neto no resta dos veces. P2 cerrados: mensaje de la notificación alineado al contrato, `Create.vue` sin URL hardcodeada, `spec.md` con `request_id`, contrato del flash de retorno describe el banner. P2 abiertos (no bloqueantes): `StartPaymentRequest::authorize()` sin Gate (ownership vía 404), aprobado tardío sobre reserva cancelada acredita saldo sin test, `with('booking')` desperdiciado en `PaymentReturnController`.
- `2026-09-12` (principal): pendiente para el usuario: prueba en navegador (item sin marcar en Frontend). Fallos ajenos conocidos: 3 de `TeamRequestMessagesTest` (idioma).
- `2026-09-12` (principal, prueba en navegador): PlacetoPay rechazó la primera sesión real con `request_not_valid` / «La información del pago es incorrecta (description)». Causa: la descripción llevaba una raya larga (`—`) y el patrón `BaseValidator::PATTERN_DESCRIPTION` de la pasarela no la acepta. `CreatePaymentSessionAction::description()` ahora reemplaza por espacio todo lo que quede fuera de ese set (los nombres de tour los escribe la agencia) y la clave de idioma usa guion simple. Test: `test_the_description_drops_characters_the_gateway_rejects`. Riesgo abierto del mismo tipo: `buyer.name` sale del `contact_snapshot` y no se sanea; ni el SDK ni `placetopay/base` publican un patrón para nombres, así que se deja hasta que aparezca un rechazo real.
- `2026-09-12` (frontend): rediseño de `Booking/Create.vue` a dos columnas (formulario + resumen sticky) con el selector «¿Cuánto querés pagar ahora?» (Total / Abono mínimo editable). El monto que viaja a `startPayment` se recorta contra `data.min_payment_amount` y `data.due_amount` de la respuesta de `storeBooking`, no contra la aritmética del formulario: el `BookingResource` ya expone los tres campos y un centavo de diferencia sería un 422. `BookingCreateResponse` los tipa.
- `2026-09-12` (frontend): `ui/input` lleva `useVModel` interno y no declara `modelModifiers`; `v-model.number` no aplica el modificador. Los montos parciales de `Create.vue` y `Show.vue` se guardan como string y se convierten con `Number()` al usarlos (mismo patrón que `price_override` en `TourDateFormDialog.vue`).
- `2026-09-12` (principal, rediseño): el monto a pagar se elige ahora en `Booking/Create` (antes saltaba a la pasarela por el total y el abono mínimo era inalcanzable desde el funnel público). `BookingResource` devuelve `due_amount`, `min_payment_amount` y `min_payment_pct` para que el pago se arme con los números del servidor y no con la aritmética del formulario. El fallback del porcentaje se unificó en `TourDate::minPaymentPercentage()` (tercer uso, regla del 3), consumido por el resource admin y por la página de reserva; `Booking::minPaymentPercentage()` conserva su camino por relaciones porque corre dentro de jobs.
- `2026-09-12` (principal, bug del admin): el campo «Mínimo de abono (%)» exigía escribir el número dos veces. Causa: usaba `:value` + `@input` manual mientras el componente `Input` lleva su propio `v-model` interno, y las dos ataduras al mismo `value` se pisaban. Pasó a `v-model`. Segundo fallo encontrado solo en navegador (el type-check no lo ve): con `type="number"` el `v-model` nativo escribe un **number**, así que `String(...)` antes de `trim()` en el payload. Verificado end-to-end: escribir 45 una vez, guardar sin error de consola, reabrir y seguir en 45.
- `2026-09-12` (principal, campo de monto): los dos campos de monto del viajero (`Booking/Create` y `Booking/Show`) pasan de `type="number"` a `type="text"` + `inputmode="decimal"`, que es el patrón que ya usaba `PassengerDrawer`. `inputmode` es lo que abre el teclado numérico en móvil; `type="number"` además dibujaba flechas, cambiaba el valor al hacer scroll encima y mostraba el monto con la coma del idioma (`55,64`). Como el campo ya no filtra solo, un watch deja lo escrito en dígitos con un punto y máximo dos decimales, acepta la coma como separador y bloquea notación científica (`1e5`, que `type="number"` aceptaba como 100000). Verificado en navegador.
