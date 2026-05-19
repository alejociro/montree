# F006 — Flujo de reserva

## Descripción

Proceso de creación de reserva: selección de fecha, ingreso de viajeros, aplicación de promociones y generación en estado `pending_payment`. Inicio del funnel de conversión.

## User stories

- Como customer, quiero seleccionar fecha y número de viajeros.
- Como customer, quiero aplicar un código promocional.
- Como customer, quiero ingresar datos de cada viajero.
- Como customer, quiero ver el desglose de precios antes de pagar.
- Como customer, quiero saber cuánto tiempo tengo para completar el pago.

## Acceptance criteria

- **Given** fecha con cupos, **when** customer selecciona 3 viajeros y crea reserva, **then** se genera booking en `pending_payment` con `expires_at = now + 30 min`.
- **Given** código promocional válido, **when** se aplica, **then** descuento reflejado en total y promoción asociada.
- **Given** código expirado/agotado/inválido, **when** se aplica, **then** error específico sin crear reserva.
- **Given** menos de `booking_advance_hours` para el tour, **then** error "Reserva fuera de plazo".
- **Given** customer con 3 reservas `pending_payment`, **then** error de límite.
- **Given** `require_traveler_details = true` y faltan datos, **then** error validación.

## Edge cases

- Dos usuarios reservan los últimos cupos simultáneamente: primero en persistir gana, segundo recibe `409`.
- Promoción con `min_amount` > subtotal: error claro.
- Viajeros = capacidad restante exacta: permitir.
- Viajeros > capacidad restante: error informando cupos.
- Fecha que se cierra mientras el usuario llena form: validar al submit.

## Dependencias

- F001 (Auth), F005 (Detalle del tour).

## Endpoints involucrados

```
POST   /api/v1/bookings
GET    /api/v1/bookings/{booking_number}
```

## Componentes UI

- Pages: `BookingPage` (multi-step)
- Organisms: `BookingForm`, `TravelerForm`, `PriceSummary`, `PromoCodeInput`, `BookingTimer`
- Molecules: `TravelerCard`, `DateConfirmation`, `StepIndicator`
- Atoms: `BaseInput`, `BaseSelect`, `BaseButton`, `CounterInput`, `Timer`

## Datos requeridos

Tablas: `bookings`, `booking_travelers`, `tour_dates`, `promotions`

---

## Out of scope

- Multi-tour en una sola reserva (futuro).
- Lista de espera al agotarse cupos (futuro).

## Changelog

- `2026-05-17` — Creación inicial.
- `2026-05-19` — Review Playwright detectó (P0-2 sistémico para F006/F008/F009/F010/F011/F012/F013/F014) que `router.post('/api/v1/...')` no dispara request. Ver `docs/review-2026-05-19/findings.md`.
