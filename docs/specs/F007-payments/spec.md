# F007 — Procesamiento de pagos

## Descripción

Integración con **PlacetoPay Checkout por redirección** para pagos completos o
parciales. El comprador sale al checkout de la pasarela y vuelve al comercio por
una URL firmada; la resolución del estado se hace consultando la sesión, y un
comando programado cierra los pagos cuyo comprador nunca volvió.

## User stories

- Como customer, quiero pagar mi reserva completa con tarjeta.
- Como customer, quiero hacer un pago parcial y completar después.
- Como customer, quiero saber que mi pago fue procesado.
- Como customer, quiero poder reintentar si mi pago falla.
- Como admin, quiero definir por salida el porcentaje mínimo de abono que asegura la plaza.
- Como guía/admin, quiero registrar en el panel un pago recibido en efectivo.

## Acceptance criteria

- **Given** booking en `pending_payment`, **when** inicia pago "full", **then** se abre una sesión en PlacetoPay y se redirige al `processUrl`.
- **Given** pago parcial, **when** el monto es ≥ el mínimo de la salida (`tour_dates.min_payment_pct` o, si es null, `min_partial_payment_pct` de la agencia, sobre el total y recortado al saldo), **then** se abre la sesión.
- **Given** un pago aprobado (pasarela o efectivo), **when** `paid_amount` alcanza el mínimo de la salida o el total, **then** booking → `confirmed`, `expires_at` se limpia y el saldo restante se cobra después.
- **Given** un pago aprobado por debajo del mínimo (solo posible si la pasarela aprobó menos de lo pedido), **then** el saldo se acredita y la reserva sigue `pending_payment`.
- **Given** una notificación de PlacetoPay con firma válida, **then** se encola la consulta de la sesión; el estado del body nunca se usa como verdad.
- **Given** vuelta al comercio con URL firmada, **when** la sesión está `APPROVED`, **then** payment → `completed`, booking → `confirmed`.
- **Given** sesión `REJECTED`/`FAILED`/`EXPIRED`, **then** payment → `failed` con el mensaje de la pasarela y el saldo no se toca.
- **Given** un aprobado cuyo comprador nunca volvió y sin notificación, **when** corre `payment:check`, **then** el pago queda resuelto igual.

## Edge cases

- `payment:check`, la notificación y la vuelta del comprador resuelven el mismo pago a la vez:
  la resolución es idempotente (relectura con `lockForUpdate` + corte por
  `isResolved()`), así que el saldo se acredita una sola vez.
- Doble clic en «Pagar»: se reusa la sesión viva por el mismo monto en vez de
  abrir otra, para no dejar dos sesiones cobrables por el mismo saldo.
- Comercios distintos por tenant pueden devolver el mismo `requestId`: el único
  de `payments` es `(tenant_id, gateway, request_id)`.
- La firma de la returnUrl cubre el host, así que la vuelta de un pago solo vale
  en el subdominio de su agencia.
- Pasarela caída: `retry()` solo sobre `PlacetoPayServiceException` (transporte);
  un payload mal armado no se reintenta.
- 3D Secure y medios de pago los maneja el checkout de PlacetoPay, no el frontend.

## Dependencias

- F006 (Booking debe existir).

## Endpoints involucrados

Rutas web (Inertia), no API: no se exponen a terceros.

```
POST      /bookings/{booking_number}/pay      (auth)   abre la sesión y redirige al checkout
GET|POST  /payments/{payment}/return          (signed) vuelta del comprador, resuelve el pago
POST      /payments/notification              (firma PlacetoPay) encola la consulta de la sesión
POST      /api/v1/admin/bookings/{booking}/payments     pago en efectivo desde el manifiesto
POST|PUT  /api/v1/admin/tours/{tour}/dates · /tour-dates/{id}   campo `min_payment_pct`
```

Comando: `php artisan payment:check [--date-from=] [--date-to=]`, programado cada
10 minutos. Shapes en `contracts.md`.

## Componentes UI

No hay formulario de tarjeta propio: los datos sensibles nunca pasan por Montree.

- Pages: `Booking/Show` (selector de monto + resultado por flash), `Booking/Create`
- Organisms: `PaymentGatewayForm` (credenciales del comercio en el panel),
  `TourDateFormDialog` (campo «Mínimo de abono (%)»), `PassengerDrawer`
  («Registrar pago» en efectivo, ya existente)

## Datos requeridos

Tablas: `payments`, `bookings`, `tenant_configurations`, `tour_dates`
(`min_payment_pct`, tinyint nullable: override del porcentaje de la agencia).

El esquema se escribe directo en las migraciones base (proyecto en desarrollo,
sin migraciones incrementales). Las tablas y columnas de Cashier/Stripe se
eliminan; `payments` no lleva columnas de reembolso.

`payments` guarda la transacción completa para auditarla y para que una tabla de
historial futura tenga de dónde copiar:

| Columna | Qué guarda |
|---|---|
| `request_id` | id de la sesión de PlacetoPay (string: en producción hay ids no numéricos del MVP) |
| `internal_reference` | referencia de la transacción en la pasarela |
| `reference` | referencia que envía el comercio (`MTR-{payment id}`) |
| `gateway_status` | estado **crudo** de la pasarela (`APPROVED`, `REJECTED`, `EXPIRED`, …) |
| `status` | estado de dominio (colapsa los tres finales malos en `failed`) |
| `status_message` | mensaje de la pasarela, para cualquier estado |
| `process_url`, `session_expires_at` | sesión abierta: a dónde se redirige y hasta cuándo vale |
| `authorization`, `receipt`, `franchise`, `payment_method`, `payment_method_name`, `issuer_name` | datos del autorizador (recibo, soporte al viajero) |
| `processor_fields` | campos del procesador, tal como llegan |
| `amount` | monto **aprobado** cuando la pasarela resuelve, no el solicitado |
| `processed_at` | fecha del autorizador, en la zona de la app; es la columna por la que agregan los reportes de ingresos |

Índices para conciliación y estadísticas: único `(tenant_id, gateway, request_id)`,
más `(tenant_id, gateway, status)`, `(tenant_id, gateway_status)`,
`(tenant_id, processed_at)` y `reference`.

La returnUrl lleva el **id del pago** firmado y no el `request_id`: se arma dentro
de la misma petición que crea la sesión, cuando la pasarela todavía no devolvió
ningún `requestId`. Es la razón por la que microsites usa un `code` derivado del
id; acá la firma de Laravel cumple ese papel sin columna extra.

---

## Out of scope

- Pagos vía PayPal, transferencia, criptomonedas (futuro).
- Split payments entre múltiples customers (futuro).
- Reembolsos desde el panel: se implementarán con `reverse(internalReference)`
  cuando el negocio lo pida. Mientras tanto no existe endpoint ni permiso.
- Recordatorio del saldo antes de la salida: va con F008.

## De dónde sale el monto

El monto **no lo calcula la capa de pagos**, igual que en microsites (donde llega
ya configurado en la factura y `PaymentServiceImpl` solo toma
`$payment->getAmount()`):

```
tour_dates.price_override ?? tours.base_price   ← precio configurado de la salida
        │  CreateBookingAction (× viajeros − descuento)
        ▼
bookings.total_amount / paid_amount
        │  accessors del modelo
        ▼
Booking::$due_amount           ← techo del pago  (maxAmountToPay)
Booking::$min_payment_amount   ← piso del abono  (minAmountToPay)
        │
        ├─► StartPaymentRequest  → reglas `min:` / `max:` (validación en el borde)
        ├─► BookingPagesController → props del formulario
        └─► CreatePaymentSessionAction → cobra, no recalcula
```

`min_payment_amount` es el porcentaje efectivo de la salida
(`tour_dates.min_payment_pct ?? min_partial_payment_pct` de la agencia) sobre el
total, recortado al saldo: sin ese recorte, una reserva a la que le falta menos
que el porcentaje exigido no se podría terminar de pagar por abono.
`deposit_amount` es el mismo porcentaje **sin** recortar: el umbral que confirma
la reserva. La regla de asiento (sumar el pago, decidir estado) vive en
`BookingSettlementService` y la comparten pasarela y efectivo.

## Decisiones tomadas

- **Pasarela**: PlacetoPay Checkout por redirección con `dnetix/redirection`.
  Cashier/Stripe queda descartado para el cobro de reservas.
- **Credenciales**: por tenant en `tenant_configurations`
  (`placetopay_login`, `placetopay_tran_key` cifrado, `placetopay_url`), con
  fallback al comercio de plataforma en `config/placetopay.php`.
- **Resolución**: tres caminos que convergen en la misma consulta idempotente,
  como en microsites: vuelta firmada, notificación servidor a servidor (valida
  firma y encola `ResolvePaymentJob`) y `payment:check`. La consulta de la
  sesión es la única fuente de verdad; el body de la notificación no se usa.
- **Mínimo por salida**: porcentaje nullable en `tour_dates` con fallback al de
  la agencia. Alcanzar el mínimo confirma la reserva con saldo pendiente.
- **Efectivo**: solo desde el panel (`RegisterManualPaymentAction`), mismo
  asiento que la pasarela, sin notificación al viajero.
- **Sin Stripe/Cashier**: dependencia, trait `Billable`, tablas y case del enum
  se eliminan.
- **Capa**: rutas web con Inertia. No hay API de pagos para terceros.
- **Montos**: una sola fuente por número (ver arriba). La validación del monto es
  del Form Request y sale como error del campo `amount`, no como flash.

## Changelog

- `2026-05-17` — Creación inicial.
- `2026-08-26` — Se completa el esquema de `payments` con la foto entera de la
  transacción (`request_id`, `gateway_status`, `status_message`, autorización,
  recibo, franquicia, medio, emisor, campos del procesador) y se documentan los
  índices de conciliación. `processed_at` pasa a llevar la fecha del autorizador
  y `amount` el monto aprobado.
- `2026-08-26` — Se reemplaza Stripe por PlacetoPay Checkout por redirección.
  Se cierran las decisiones abiertas (credenciales por tenant con fallback,
  resolución por retorno firmado + `payment:check`, rutas web en vez de API) y
  se retira el flujo mock `gateway=manual` del camino del cliente; el pago manual
  queda solo en el panel.
- `2026-09-12` — Cierre de decisiones con el usuario: mínimo de abono por salida
  (`tour_dates.min_payment_pct`), abono ≥ mínimo confirma la reserva, notificación
  de PlacetoPay se mantiene (la nota «sin webhook» estaba desactualizada),
  comando pasa a `payment:check`, esquema aplanado en migraciones base, Cashier
  eliminado, reembolso fuera de scope. Se crean `contracts.md`, `plan.md` y
  `tasks.md`.
