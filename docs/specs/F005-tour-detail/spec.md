# F005 — Detalle del tour (PDP)

## Descripción

Page Detail Page con toda la información del tour: galería, descripción, itinerario, fechas, mapa y reseñas. Página clave de conversión.

## User stories

- Como visitante, quiero ver toda la información del tour antes de reservar.
- Como visitante, quiero ver fechas disponibles y cupos.
- Como visitante, quiero ver fotos en galería.
- Como visitante, quiero ver el punto de encuentro en mapa.
- Como visitante, quiero ver reseñas de otros viajeros.
- Como visitante, quiero agregar a favoritos.

## Acceptance criteria

- **Given** la URL `/tours/{slug}`, **when** carga, **then** muestra galería, descripción, itinerario, fechas y reseñas.
- **Given** fechas disponibles, **then** muestra para cada una: fecha/hora, precio (override si aplica), cupos.
- **Given** una fecha `full`, **then** se muestra deshabilitada con "Agotado".
- **Given** visitante no autenticado que intenta reservar, **then** redirect a login con return URL.
- **Given** tour que no existe o no `active`, **then** `404`.

## Edge cases

- Tour con 1 sola imagen: no carousel, solo imagen.
- Tour sin reseñas: CTA "Sé el primero en opinar".
- Tour sin fechas futuras: mensaje "No hay fechas disponibles" + opción suscribirse a notificación.
- Coordenadas del meeting point null: no mostrar mapa.
- Precio override ≠ base: mostrar precio base tachado y override como actual.

## Dependencias

- F003 (Tour existe), F010 (Reviews, puede estar vacío).

## Endpoints involucrados

```
GET    /api/v1/tours/{slug}
GET    /api/v1/tours/{slug}/reviews
POST   /api/v1/favorites
```

## Componentes UI

- Pages: `TourDetailPage`
- Organisms: `ImageGallery`, `TourInfo`, `ItineraryTimeline`, `DateSelector`, `ReviewSection`, `MapEmbed`
- Molecules: `DateCard`, `IncludesList`, `RequirementsList`, `RatingBreakdown`, `ReviewCard`
- Atoms: `Badge`, `RatingStars`, `PriceTag`, `AvailabilityIndicator`, `FavoriteButton`

## Datos requeridos

Tablas: `tours`, `tour_images`, `tour_itineraries`, `tour_dates`, `reviews`, `favorites`

---

## Changelog

- `2026-05-17` — Creación inicial.
