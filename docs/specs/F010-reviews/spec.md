# F010 — Sistema de reseñas

## Descripción

Customers que completaron un tour pueden dejar reseña con calificación. Admins moderan y responden. Reseñas se muestran públicamente en PDP.

## User stories

- Como customer, quiero dejar reseña después de completar un tour.
- Como customer, quiero calificar con estrellas (1-5) y escribir comentario.
- Como visitante, quiero ver reseñas para decidir si reservo.
- Como admin, quiero moderar reseñas antes de que se publiquen.
- Como admin, quiero responder reseñas públicamente.

## Acceptance criteria

- **Given** booking `completed`, **when** customer escribe reseña, **then** se crea `pending` (si moderación activa) o `approved`.
- **Given** customer ya reseñó ese booking, **when** intenta otra, **then** `409`.
- **Given** booking que no es `completed`, **when** intenta reseñar, **then** `403`.
- **Given** reseña `pending`, **when** admin aprueba, **then** se muestra pública y se recalcula promedio.
- **Given** reseña aprobada, **when** admin responde, **then** respuesta se muestra bajo la reseña.
- **Given** listado público, **then** solo muestra `approved` con promedio y distribución.

## Edge cases

- Contenido ofensivo: admin rechaza, queda en BD pero no pública.
- Tour con 0 reseñas: no mostrar sección de promedio, solo CTA.
- Admin responde dos veces: `409`.
- Reseña 1 estrella: válida, no se trata diferente.
- Cálculo de promedio: recalcular solo al aprobar/rechazar, no en cada lectura.

## Dependencias

- F006 (Booking completed), F008 (Invitación a reseñar).

## Endpoints involucrados

```
POST   /api/v1/reviews
GET    /api/v1/tours/{slug}/reviews
PATCH  /api/v1/admin/reviews/{id}/status
POST   /api/v1/admin/reviews/{id}/respond
```

## Componentes UI

- Integrado en `TourDetailPage` y `AccountPage`
- Organisms: `ReviewForm`, `ReviewList`, `ReviewModeration` (admin)
- Molecules: `ReviewCard`, `RatingInput`, `RatingBreakdown`, `AdminReviewCard`
- Atoms: `RatingStars`, `Badge`, `TextArea`, `BaseButton`

## Datos requeridos

Tablas: `reviews`, `bookings`, `tours`, `users`

---

## Changelog

- `2026-05-17` — Creación inicial.
