# F005 — Plan técnico

## Backend
- `Services/Catalog/TourDetailResolver`: carga tour por slug + eager (`category`, `images`, `itinerarySteps`, `futureDates`), aplica scope `active`.
- `Services/Catalog/RatingDistribution`: count por rating (1..5) sobre reviews approved.
- `Actions/Favorite/ToggleFavoriteAction`: firstOrCreate / delete.
- Controllers (Api/V1): `PublicTourController` (show), `PublicReviewController` (index), `FavoriteController` (store).
- Form Requests: `ToggleFavoriteRequest` (tour_id exists scoped tenant + active).
- Resources: `PublicTourResource` (con itinerary/images/futureDates/rating_distribution), `PublicReviewResource`.
- Inertia route `/tours/{slug}` con controller que renderiza usando Resource toArray.
- `Tour::scopeActive()`, `TourDate::scopeOpenFuture()` helpers.

## Frontend
- Page `TourDetail.vue` con secciones: gallery, header (name + category + rating), description, includes/requirements, itinerary timeline, meeting point (map placeholder con coordenadas), date selector, reviews section.
- Organisms: `ImageGallery` (carousel simple — sin lib, swipe básico con CSS scroll-snap), `TourInfo`, `ItineraryTimeline`, `DateSelector` (grid de cards por fecha con status), `ReviewSection`, `MapPlaceholder` (link a Google Maps si lat/lng presentes).
- Molecules: `DateCard`, `IncludesList`, `RequirementsList`, `RatingBreakdown` (5 barras horizontales), `ReviewCard`, `FavoriteButton` (toggle con optimistic update via `useForm` + onSuccess flash).
- CTA "Reservar" en DateCard que abre `/booking/new?tour_date_id=X` (ruta F006 — si no existe aún, fallback a alert / mostrar pero deshabilitado con `disabled` y tooltip).
- Empty states: sin reviews → CTA "Sé el primero en opinar"; sin fechas → mensaje + notify subscribe (opcional, fuera de scope MVP).
- Estados loading/error.
- Wayfinder routes correspondientes.

## Tests
- Feature: `PublicTourControllerTest` (full payload, 404 archived, 404 draft, tenant isolation), `PublicReviewControllerTest` (only approved, pagination), `FavoriteControllerTest` (toggle + auth required + tenant isolation).
- Unit: `RatingDistributionTest`.
