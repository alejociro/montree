# F004 — Catálogo y búsqueda de tours

## Descripción

Página pública con todos los tours activos del tenant, con filtros, búsqueda y ordenamiento. Es la página principal de descubrimiento.

## User stories

- Como visitante, quiero ver todos los tours disponibles de la agencia.
- Como visitante, quiero filtrar por categoría, dificultad y precio.
- Como visitante, quiero buscar por nombre o descripción.
- Como visitante, quiero ordenar por precio, popularidad o fecha más próxima.
- Como visitante, quiero ver rápidamente precio, duración y rating.

## Acceptance criteria

- **Given** la página de catálogo, **when** carga, **then** muestra solo tours `active` del tenant actual.
- **Given** filtro "Senderismo", **when** se aplica, **then** muestra solo tours de esa categoría.
- **Given** término "cocora", **when** busca, **then** retorna tours que contienen "cocora" en título o descripción.
- **Given** tours sin fechas futuras abiertas, **then** se muestran con badge "Sin disponibilidad".
- **Given** visitante autenticado, **then** cada card indica si es favorito.

## Edge cases

- Búsqueda sin resultados: estado vacío con sugerencia.
- Filtros combinados sin resultados: sugerir relajar filtros.
- Tour con precio override en fechas: mostrar "desde $X".
- >100 tours: paginación con infinite scroll.
- Caracteres especiales: sanitizar antes de enviar.

## Dependencias

- F002 (Tenant), F003 (Tours existen).

## Endpoints involucrados

```
GET    /api/v1/tours?category=&difficulty=&price_min=&price_max=&search=&sort=&page=
```

## Componentes UI

- Pages: `CatalogPage`
- Organisms: `TourGrid`, `FilterSidebar`, `SearchBar`, `ActiveFilters`
- Molecules: `TourCard`, `PriceRange`, `CategoryChip`, `SortDropdown`, `PaginationBar`
- Atoms: `Badge`, `RatingStars`, `Icon`, `Skeleton`

## Datos requeridos

Tablas: `tours`, `tour_images`, `categories`, `favorites` (si autenticado)

---

## Changelog

- `2026-05-17` — Creación inicial.
