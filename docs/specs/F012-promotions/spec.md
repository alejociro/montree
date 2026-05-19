# F012 — Promociones y descuentos

## Descripción

Códigos promocionales con descuento porcentual o fijo. Reglas de validez, límites de uso, restricciones por tour.

## User stories

- Como admin, quiero crear códigos promocionales (porcentaje o fijo).
- Como admin, quiero limitar por tiempo, cantidad de usos y tours específicos.
- Como admin, quiero ver rendimiento (usos, revenue impactado).
- Como customer, quiero aplicar un código a mi reserva.
- Como admin, quiero desactivar una promoción antes de que expire.

## Acceptance criteria

- **Given** admin creando promoción, **when** define código, tipo, valor y fechas, **then** se crea.
- **Given** código duplicado en el mismo tenant, **then** `409`.
- **Given** customer aplica código válido (dentro de fechas y con usos), **then** descuento aplicado.
- **Given** `max_uses` alcanzado, **then** "Código agotado".
- **Given** `applicable_tours` definido, **when** se usa en tour no listado, **then** "No válido para este tour".
- **Given** admin desactiva, **when** customer intenta usar, **then** "Código inactivo".

## Edge cases

- Descuento porcentual que resulta en total < $1: aplicar regla PRO-009, total mínimo $1.
- `max_discount` definido: descuento no excede ese tope.
- Código case-insensitive: `VERANO2026` = `verano2026`.
- Promoción que expira durante checkout: validar al submit.
- Concurrencia en último uso: primero en persistir gana.

## Dependencias

- F006 (Se aplica durante booking).

## Endpoints involucrados

```
GET    /api/v1/admin/promotions
POST   /api/v1/admin/promotions
PUT    /api/v1/admin/promotions/{id}
DELETE /api/v1/admin/promotions/{id}
```

## Componentes UI

- Pages: `PromotionsPage` (admin)
- Organisms: `PromotionForm`, `PromotionList`, `PromotionStats`
- Molecules: `PromotionCard`, `TourSelector`, `DateRangePicker`, `UsageIndicator`
- Atoms: `BaseInput`, `BaseSelect`, `BaseSwitch`, `Badge`, `ProgressBar`

## Datos requeridos

Tablas: `promotions`, `bookings`

---

## Changelog

- `2026-05-17` — Creación inicial.
- `2026-05-19` — Review Playwright detectó (P0-2 sistémico para F006/F008/F009/F010/F011/F012/F013/F014) que `router.post('/api/v1/...')` no dispara request. Ver `docs/review-2026-05-19/findings.md`.
