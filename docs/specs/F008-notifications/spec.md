# F008 — Confirmación y notificaciones

## Descripción

Sistema de notificaciones que informa a usuarios y admins sobre eventos importantes del ciclo de vida de las reservas. Email transaccional + notificaciones in-app.

## User stories

- Como customer, quiero recibir confirmación inmediata cuando mi pago es exitoso.
- Como customer, quiero recibir un recordatorio el día antes del tour.
- Como admin, quiero ser notificado de nuevas reservas.
- Como customer, quiero ver mis notificaciones en la app.
- Como customer, quiero marcar notificaciones como leídas.

## Acceptance criteria

- **Given** pago confirmado, **then** customer recibe email + notificación in-app con detalles y código.
- **Given** tour mañana, **then** 24h antes se envía recordatorio con meeting point y hora.
- **Given** reserva a punto de expirar (10 min), **then** notificación urgente.
- **Given** nueva reserva, **then** admin del tenant recibe notificación.
- **Given** usuario abre la campana, **then** listado paginado con conteo de no leídas.

## Edge cases

- Email bounce: reintentar 3 veces con backoff.
- Usuario con notificaciones desactivadas: respetar pero enviar críticas (confirmación pago).
- Múltiples notificaciones del mismo tipo: no duplicar (idempotencia por `booking + type`).
- Timezone del usuario vs tenant: mostrar en timezone del tenant.

## Dependencias

- F006 (Bookings), F007 (Payments).

## Endpoints involucrados

```
GET    /api/v1/notifications
PATCH  /api/v1/notifications/{id}/read
POST   /api/v1/notifications/read-all
```

## Componentes UI

- Pages: `NotificationsPage`
- Organisms: `NotificationList`, `NotificationDropdown`
- Molecules: `NotificationCard`, `NotificationBadge`
- Atoms: `Badge`, `TimeAgo`, `UnreadDot`

## Datos requeridos

Tablas: `notifications`

---

## Changelog

- `2026-05-17` — Creación inicial.
