# F007 — Procesamiento de pagos

## Descripción

Integración con Stripe para pagos (completos o parciales). Webhooks para confirmar transacciones y actualizar estados.

## User stories

- Como customer, quiero pagar mi reserva completa con tarjeta.
- Como customer, quiero hacer un pago parcial y completar después.
- Como customer, quiero saber que mi pago fue procesado.
- Como customer, quiero poder reintentar si mi pago falla.
- Como admin, quiero procesar reembolsos.

## Acceptance criteria

- **Given** booking en `pending_payment`, **when** inicia pago "full", **then** recibe `client_secret` de Stripe.
- **Given** pago parcial, **when** monto ≥ `min_partial_payment_pct%` del total, **then** se procesa y booking → `confirmed`.
- **Given** pago parcial confirmado, **when** llega deadline del remainder (48h antes), **then** customer recibe recordatorio.
- **Given** webhook `payment_intent.succeeded`, **when** Stripe confirma, **then** payment → `completed`, booking → `confirmed`.
- **Given** webhook `payment_intent.payment_failed`, **then** payment → `failed`, notificar al usuario.
- **Given** admin procesando reembolso, **when** ejecuta, **then** se procesa en Stripe y booking → `refunded`.

## Edge cases

- Webhook llega antes que frontend muestre confirmación: estado ya correcto.
- Webhook duplicado: idempotencia por `gateway_payment_id`.
- Pago exitoso pero reserva ya expiró: reembolso automático.
- Stripe en mantenimiento: mostrar error y sugerir reintentar.
- Monto con decimales en moneda sin decimales (COP): redondear correctamente.
- 3D Secure: frontend maneja con Stripe.js.

## Dependencias

- F006 (Booking debe existir).

## Endpoints involucrados

```
POST   /api/v1/bookings/{booking_number}/payments
POST   /api/v1/payments/webhook
POST   /api/v1/admin/payments/{id}/refund
```

## Componentes UI

- Pages: `PaymentPage`, `PaymentSuccessPage`, `PaymentFailedPage`
- Organisms: `StripePaymentForm`, `PaymentSummary`, `PaymentTypeSelector`
- Molecules: `CardElement` (Stripe), `PaymentOption`, `TransactionReceipt`
- Atoms: `BaseButton`, `LoadingSpinner`, `StatusIcon`, `PriceDisplay`

## Datos requeridos

Tablas: `payments`, `bookings`

---

## Out of scope

- Pagos vía PayPal, transferencia, criptomonedas (futuro).
- Split payments entre múltiples customers (futuro).

## Decisiones abiertas

- [ ] ¿laravel/cashier o integración Stripe pelada?

## Changelog

- `2026-05-17` — Creación inicial.
