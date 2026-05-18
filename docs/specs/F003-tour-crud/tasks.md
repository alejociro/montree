# F003 — Tasks

## Backend
- [x] Actions: `CreateTourAction`, `UpdateTourAction`, `DeleteTourAction`, `ChangeTourStatusAction`, `AttachTourImageAction`, `UpdateTourImageAction`, `DetachTourImageAction` (+ `SyncTourItineraryAction` interno)
- [x] Services: `TourSlugGenerator`, `TourStatusTransition`, `PlanLimitChecker`
- [x] Form Requests: `StoreTourRequest`, `UpdateTourRequest`, `ChangeTourStatusRequest`, `StoreTourImageRequest`, `UpdateTourImageRequest`
- [x] Controllers: `Admin\TourController` (apiResource), `Admin\TourStatusController` (invoke), `Admin\TourImageController` (store/update/destroy), `Admin\TourPagesController` (Inertia pages)
- [x] Resources: `TourResource`, `TourSummaryResource`, `TourImageResource`, `TourItineraryStepResource`, `CategoryResource`
- [x] Policy: `TourPolicy` (viewAny/view/create/update/delete/archive) registrada en `AppServiceProvider`
- [x] Exceptions de dominio: `PlanLimitReachedException`, `InvalidTourStatusTransitionException`, `TourHasActiveBookingsException` + handlers registrados en `bootstrap/app.php`
- [x] Routes: `routes/api.php` admin block + `routes/web.php` 3 Inertia pages
- [x] Tests feature (3 archivos: TourControllerTest, TourStatusControllerTest, TourImageControllerTest — 24 tests)
- [x] Tests unit (3 archivos: TourSlugGeneratorTest, TourStatusTransitionTest, PlanLimitCheckerTest — 13 tests)
- [x] Wayfinder generate
- [x] Pint clean + tests pasan (137/137)

## Frontend
- [x] Pages: `Admin/Tour/Index.vue`, `Create.vue`, `Edit.vue`
- [x] Organisms: `TourForm`, `TourImageUploader`, `TourItineraryBuilder`, `TourStatusBadge`, `TourFilters`
- [x] Molecules: `DifficultySelector`, `PriceInput`, `CapacityInput`, `MeetingPointPicker`
- [x] Types: `types/tour.ts` (re-exportado desde `types/index.ts`)
- [x] AdminSidebar: agregado item "Tours" → /admin/tours con icon Mountain (antes de Configuración)
- [x] Wayfinder imports usados, sin URLs hardcoded
- [x] types-check (sin errores nuevos), lint (clean), build (OK)

## Review
- [x] Tests + lint + types verde
- [x] Cobertura acceptance criteria (draft → active con requisitos, archivar con bookings, plan limit, slug colisión, tenant isolation)
- [x] Sin lógica en Resource (los Resources sólo formatean; el `Storage::disk()->url()` es presentation pura)
- [x] N+1 verified: index usa `with(['category','coverImage'])->withCount(['images','bookings'])`; show/edit cargan `['category','images','itineraries']`

---

## Notas durante implementación
- `2026-05-17` (claude principal): F003 arrancado. Specs escritos.
- `2026-05-17` (worktree agent): backend + frontend end-to-end completos.
  Desviaciones documentadas en `contracts.md` changelog: payload alineado al
  schema real (no `min/max_capacity` separados; `default_capacity` único;
  `duration_label` string en itinerario; `alt_text` en imágenes en lugar de
  `caption`). Sin cambios de schema. Sin paquetes nuevos.
  Cómo correr local: composer install dentro de la worktree + symlink temporal
  `public/build` → main durante tests (los tests Inertia esperan el manifest).
  Decisiones nuevas: (1) handlers de excepción se registran centralmente en
  `bootstrap/app.php` via `Exceptions::render`; (2) `TourPagesController` aparte
  para pages Inertia evita closures con lógica; (3) `coverImage` HasOne en `Tour`
  para eager load del listado sin N+1; (4) image uploader usa `router.post` con
  `forceFormData: true` (la action de Wayfinder solo provee URL).
