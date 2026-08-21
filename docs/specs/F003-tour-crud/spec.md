# F003 — CRUD de Tours (Admin)

## Descripción

Panel de administración para crear, editar, publicar y archivar tours. Incluye gestión de imágenes, itinerario y control de estados. Es la herramienta principal del operador de la agencia.

## User stories

- Como admin/operator, quiero crear un nuevo tour con toda su información.
- Como admin/operator, quiero subir múltiples imágenes y reordenarlas.
- Como admin/operator, quiero definir el itinerario paso a paso.
- Como admin/operator, quiero publicar un tour cuando esté listo.
- Como admin/operator, quiero pausar temporalmente un tour.
- Como admin, quiero archivar tours que ya no ofrezco.

## Acceptance criteria

- **Given** un admin creando tour, **when** completa campos requeridos y guarda, **then** se crea en `status = draft`.
- **Given** un tour `draft` con ≥1 imagen y ≥1 fecha futura, **when** cambia status a `active`, **then** aparece en el catálogo público.
- **Given** un tour con reservas activas, **when** intenta eliminarlo, **then** recibe `409` y debe archivarlo en su lugar.
- **Given** un tour activo, **when** se pausa, **then** desaparece del catálogo pero mantiene sus reservas existentes.
- **Given** el límite de tours del plan alcanzado, **when** intenta crear otro, **then** recibe error indicando upgrade.

## Edge cases

- Imágenes >5MB: rechazar con error claro.
- Slug duplicado: auto-generar variante (`senderismo-cocora-2`).
- Tour sin categoría: permitido, aparece en "Todos".
- Edición concurrente: last-write-wins con `updated_at`.
- Tour con fechas pasadas solamente: no mostrarlo en catálogo (regla TOU-002).

## Dependencias

- F002 (Tenant resolution).

## Endpoints involucrados

```
GET    /api/v1/admin/tours
POST   /api/v1/admin/tours
GET    /api/v1/admin/tours/{id}
PUT    /api/v1/admin/tours/{id}
DELETE /api/v1/admin/tours/{id}
POST   /api/v1/admin/tours/{id}/images
PATCH  /api/v1/admin/tours/{id}/status
```

## Componentes UI

- Pages: `TourListAdminPage`, `TourCreatePage`, `TourEditPage`
- Organisms: `TourForm`, `ImageUploader`, `ItineraryBuilder`, `TourStatusBadge`
- Molecules: `ImageSortable`, `ItineraryStep`, `FormSection`, `DifficultySelector`
- Atoms: `BaseInput`, `BaseTextarea`, `BaseSelect`, `BaseButton`, `FileInput`, `DragHandle`

## Datos requeridos

Tablas: `tours`, `tour_images`, `tour_itineraries`, `categories`

---

## Out of scope

- Bulk import desde CSV (futuro).
- Duplicar tour (futuro).

## Changelog

- `2026-05-17` — Creación inicial.
- `2026-08-21` — **Superada por [`tours-admin-passengers`](../tours-admin-passengers/spec.md)** en todo
  lo que toca el panel de tours. Esta spec queda como registro de lo que se construyó primero; para
  saber qué hace hoy la pantalla hay que leer la nueva. Lo que cambió respecto de lo escrito aquí:
  - Las cuatro pantallas (índice, crear, editar, detalle) se rediseñaron con la maqueta del handoff:
    KPIs, barra de ocupación, pestañas y savebar.
  - `tour_dates.guide_id` es obligatorio y un guía no puede llevar dos salidas el mismo día; `ends_at`
    se deriva de la duración y ya no se escribe a mano.
  - Publicar exige además resumen corto y guía por defecto, no solo imagen; la lista de condiciones la
    emite el servidor (`publish_checklist`).
  - El tour estrena pestaña «Pasajeros» con la planilla de la salida, y el guía la ve en su propia zona.
  - `operator` conserva la escritura sobre tours (`tours.create`, `tours.update`, `tours.publish`), pero
    no tiene `bookings.view`: la pestaña «Pasajeros» no es suya. Quién ve qué lo fija el RBAC (F018) y
    la Decisión 7 de la spec nueva, no esta.
