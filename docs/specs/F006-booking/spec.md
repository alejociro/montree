# F006 — Flujo de reserva

## Descripción

Proceso de creación de reserva en dos fases. **Fase 1 (checkout):** selección de fecha, cantidad de viajeros (adultos + menores), datos del comprador y contacto de emergencia, aplicación de promociones y generación en estado `pending_payment`. **Fase 2 (post-reserva):** ingreso de los datos individuales de cada viajero desde el detalle de la reserva. Inicio del funnel de conversión.

## User stories

- Como customer, quiero seleccionar fecha y número de viajeros (adultos y menores).
- Como customer, quiero aplicar un código promocional.
- Como customer, quiero ingresar los datos de cada viajero **después de crear la reserva**, desde el detalle, para no fricción en el checkout.
- Como customer, quiero ver el desglose de precios antes de pagar.
- Como customer, quiero saber cuánto tiempo tengo para completar el pago.

## Acceptance criteria

### Fase 1 — creación de la reserva (checkout)

- **Given** fecha con cupos, **when** customer indica `adults_count` (mín 1) y `minors_count` (mín 0, suma máx 50) más datos del comprador y contacto de emergencia y crea reserva, **then** se genera booking en `pending_payment` con `expires_at = now + 30 min` y `travelers_count = adults_count + minors_count`.
- **Given** el checkout, **when** se crea la reserva, **then** NO se pide la lista de viajeros (solo cantidades). Precio único por asiento, sin precio diferenciado para menores.
- **Given** código promocional válido, **when** se aplica, **then** descuento reflejado en total y promoción asociada.
- **Given** código expirado/agotado/inválido, **when** se aplica, **then** error específico sin crear reserva.
- **Given** menos de `booking_advance_hours` para el tour, **then** error "Reserva fuera de plazo".
- **Given** customer con 3 reservas `pending_payment`, **then** error de límite.

### Fase 2 — datos de viajeros (post-reserva)

- **Given** una reserva propia activa, **when** el dueño hace `PUT /api/v1/bookings/{booking_number}/travelers` con la lista de viajeros, **then** se hace upsert por `id`; los viajeros con `is_minor=false` deben ser ≤ `adults_count` y los `is_minor=true` ≤ `minors_count`, sino error de validación.
- **Given** una reserva de otro dueño u otro tenant, **when** se intenta el PUT, **then** `404`.
- **Given** una reserva cancelada o expirada, **when** se intenta el PUT, **then** `409`.
- **Given** `require_traveler_details = true` (config del tenant), **then** la sección "Viajeros" del detalle se marca como "Requerido antes del tour"; **given** `false`, se muestra como opcional. En ningún caso bloquea la creación de la reserva.

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
PUT    /api/v1/bookings/{booking_number}/travelers   (auth, solo dueño; 404 si no dueño/otro tenant; 409 si cancelada/expirada)
```

### Campos del request `POST /api/v1/bookings`

- `tour_date_id`
- `adults_count` (int, mín 1)
- `minors_count` (int, mín 0; `adults_count + minors_count` ≤ 50)
- datos del comprador
- contacto de emergencia
- `promo_code` (opcional)

> Nota: la lista de viajeros ya NO se envía en el checkout. `traveler_mode` (`complete_now`/`share_link`) fue **eliminado** (código muerto — el flujo share_link nunca se implementó).

### Campos del request `PUT /api/v1/bookings/{booking_number}/travelers`

- `travelers[]`: `{ id?, first_name, last_name, phone, document, birth_date, is_minor }`
  - upsert por `id`
  - viajeros con `is_minor=false` ≤ `adults_count`; con `is_minor=true` ≤ `minors_count`

## Componentes UI

- Pages: `BookingPage` (checkout, sin lista de viajeros), `Booking/Show` (detalle con sección "Viajeros" — slots por adulto/menor para completar nombre, teléfono, documento, fecha de nacimiento)
- Organisms: `BookingForm`, `TravelerForm`, `PriceSummary`, `PromoCodeInput`, `BookingTimer`
- Molecules: `TravelerCard`, `DateConfirmation`, `StepIndicator`
- Atoms: `BaseInput`, `BaseSelect`, `BaseButton`, `CounterInput`, `Timer`

> La tarjeta "Compartir enlace de registro" (share_link) fue **retirada** de la UI junto con `traveler_mode`.

## Datos requeridos

Tablas: `bookings`, `booking_travelers`, `tour_dates`, `promotions`

- `booking_travelers.is_minor` (columna nueva): distingue adulto/menor y valida contra `adults_count`/`minors_count`.
- `bookings.travelers_count`: se conserva como total (`adults_count + minors_count`).

---

## Out of scope

- Multi-tour en una sola reserva (futuro).
- Lista de espera al agotarse cupos (futuro).

## Changelog

- `2026-05-17` — Creación inicial.
- `2026-05-19` — Review Playwright detectó (P0-2 sistémico para F006/F008/F009/F010/F011/F012/F013/F014) que `router.post('/api/v1/...')` no dispara request. Ver `docs/review-2026-05-19/findings.md`.
- `2026-07-12` — Checkout dividido en dos fases: el `POST /bookings` ahora pide solo `adults_count`/`minors_count` + comprador + contacto de emergencia (ya no la lista de viajeros); nuevo `PUT /bookings/{booking_number}/travelers` para cargar datos de viajeros post-reserva (upsert por id, columna `booking_travelers.is_minor`, validación adultos/menores contra las cantidades). `require_traveler_details` deja de bloquear la creación y ahora solo marca la sección "Viajeros" del detalle como requerida u opcional (AC reemplazado). Eliminado `traveler_mode` (`complete_now`/`share_link`) y la tarjeta "Compartir enlace de registro" por código muerto. Razón: decisión de UX para reducir la fricción en el checkout y mejorar conversión.
