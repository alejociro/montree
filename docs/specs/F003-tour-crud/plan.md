# F003 — Plan técnico

## 1. Resumen

CRUD completo de tours para admin/operator de un tenant. Backend: Actions per use-case (Create, Update, Delete, ChangeStatus, AttachImage, etc.), Form Requests, Policies. Frontend: 3 pages Inertia (Index, Create, Edit) con Image manager drag-and-drop y itinerary builder. Validaciones de plan (max_tours) y reglas de transición de estado en Actions.

## 2. Backend

### Actions (`app/Actions/Tour/`)
- `CreateTourAction::handle(array $data): Tour` — crea + itinerary + slug autogenerado con desambiguación
- `UpdateTourAction::handle(Tour $tour, array $data): Tour` — patch + reemplaza itinerary si viene
- `DeleteTourAction::handle(Tour $tour): void` — chequea reservas, soft delete
- `ChangeTourStatusAction::handle(Tour $tour, TourStatus $next): Tour` — valida transición + reglas (imagen+fecha)
- `AttachTourImageAction::handle(Tour $tour, UploadedFile $file, ?bool $isCover, ?string $caption): TourImage` — guarda en storage, calcula display_order
- `UpdateTourImageAction::handle(TourImage $image, array $data): TourImage` — toggle cover, reorder
- `DetachTourImageAction::handle(TourImage $image): void`

### Services (`app/Services/Tour/`)
- `TourSlugGenerator::generate(string $name, ?int $excludeTourId, int $tenantId): string` — slug con sufijo numérico si colisiona dentro del tenant.
- `TourStatusTransition::isValid(TourStatus $from, TourStatus $to): bool` — matriz de transiciones.
- `PlanLimitChecker::canCreateTour(Tenant $tenant): bool` — lee `TenantPlan::limits()['max_tours']` vs `tours()->count()`.

### Form Requests (`app/Http/Requests/Admin/Tour/`)
- `StoreTourRequest`, `UpdateTourRequest`, `ChangeTourStatusRequest`, `StoreTourImageRequest`, `UpdateTourImageRequest`.

### Controllers (`app/Http/Controllers/Api/V1/Admin/`)
- `TourController` — index, show, store, update, destroy.
- `TourStatusController` — `__invoke` (PATCH /status).
- `TourImageController` — store, update, destroy.

### Resources
- `TourResource` (full), `TourSummaryResource` (para listados), `TourImageResource`, `TourItineraryStepResource`, `CategoryResource`.

### Policies
- `TourPolicy`: `viewAny`/`view` (admin/operator/guide), `create`/`update` (admin/operator), `delete`/`archive` (admin only). Verificar tenant.

### Storage
- Imágenes en disco `public` (default Laravel) bajo `tours/{tour_id}/{ulid}.{ext}`. Si querés S3, hacer en producción cambiando `FILESYSTEM_DISK`.

### Routes (`routes/api.php`)
Agregar bloque admin scoped:
```
Route::middleware(['auth:sanctum', 'verified.or-skip'])->prefix('admin')->group(function () {
    Route::apiResource('tours', Admin\TourController::class);
    Route::patch('tours/{tour}/status', Admin\TourStatusController::class)->name('admin.tours.status');
    Route::post('tours/{tour}/images', [Admin\TourImageController::class, 'store']);
    Route::patch('tours/{tour}/images/{image}', [Admin\TourImageController::class, 'update']);
    Route::delete('tours/{tour}/images/{image}', [Admin\TourImageController::class, 'destroy']);
});
```

### Inertia routes (`routes/web.php`)
```
Route::middleware(['auth', 'verified.or-skip'])->prefix('admin')->group(function () {
    Route::get('tours', fn() => Inertia::render('Admin/Tour/Index', [...]))->name('admin.tours.index');
    Route::get('tours/create', fn() => Inertia::render('Admin/Tour/Create', [...]));
    Route::get('tours/{tour}/edit', fn(Tour $tour) => Inertia::render('Admin/Tour/Edit', [...]));
});
```

### Eager loading
- Index: `with('category', 'coverImage')`, `withCount('images', 'bookings')`, paginate.
- Show/Edit: `with('category', 'images', 'itinerarySteps', 'futureDates')`.

## 3. Frontend

### Pages (`resources/js/pages/Admin/Tour/`)
- `Index.vue` — tabla con filtros (status chips, search, category select), paginación.
- `Create.vue` — form completo con secciones (general, pricing, capacity, itinerary, meeting point, images placeholder).
- `Edit.vue` — mismo form + ImageManager funcional + status switcher.

### Organisms (`resources/js/components/organisms/`)
- `TourForm.vue` — recibe `:tour?` (edit mode), emit `submit`.
- `TourImageUploader.vue` — drag-drop file, llama API endpoint via wayfinder, lista de imágenes con sortable + cover toggle.
- `TourItineraryBuilder.vue` — lista ordenable de steps con drag (`@vueuse/integrations/useSortable` o simple up/down buttons), add/remove.
- `TourStatusBadge.vue` — badge con color por status.
- `TourFilters.vue` — chips de status + search input + category select.

### Molecules
- `DifficultySelector.vue` — radio cards (easy/moderate/hard/expert con icons).
- `PriceInput.vue` — input number + currency suffix.
- `CapacityInput.vue` — dual input min/max.
- `MeetingPointPicker.vue` — input texto + opcional iframe Maps (puede ser solo texto en MVP).

### Reusar
- shadcn-vue `Input`, `Textarea`, `Select`, `Button`, `Card`, `Badge`, `Alert`, `Switch`, `Table`, `Dialog`.

### AdminSidebar
- Agregar item "Tours" → `/admin/tours` con icon `MapPin` o similar.

### Types
- `resources/js/types/tour.ts` — interfaces alineadas con `TourResource` + `TourImageResource` + enums.

### Wayfinder
- Tras backend: `php artisan wayfinder:generate`. Imports esperados:
  - `@/actions/App/Http/Controllers/Api/V1/Admin/TourController` (index, store, update, destroy)
  - `@/actions/App/Http/Controllers/Api/V1/Admin/TourStatusController` (invoke)
  - `@/actions/App/Http/Controllers/Api/V1/Admin/TourImageController` (store, update, destroy)

## 4. Tests

### Feature
- `Api/V1/Admin/TourControllerTest`: index (filters, search, paginated), show, store (happy + 3 validations), update (happy + partial), destroy (blocked by bookings).
- `Api/V1/Admin/TourStatusControllerTest`: each valid transition + 3 invalid + activation rules (needs image, needs future date).
- `Api/V1/Admin/TourImageControllerTest`: store (happy + size + mime), cover toggle, reorder, destroy.
- `Tour/PlanLimitTest`: Basic plan can't create > limit, Pro can, Enterprise unlimited.
- `Tour/TenantIsolationTest`: tour de otro tenant no aparece en index del actual; un admin no puede editar tour del otro.

### Unit
- `Services/Tour/TourSlugGeneratorTest`: collision resolution.
- `Services/Tour/TourStatusTransitionTest`: matriz completa.

## 5. Decisiones tomadas

- **Soft delete** porque el schema ya lo tiene.
- **`PUT` reemplaza itinerary completo** si viene en body (no merge incremental — más simple, evita huérfanos).
- **Image cover unique per tour**: enforcement en `UpdateTourImageAction` (desactivar otras).
- **No image carousel reorder via API drag**: API solo recibe `display_order` ya calculado por frontend.
- **Storage public disk**: en dev archivos visibles en `/storage/...`. En prod requiere S3.
- **Operator rights**: pueden CRUD pero no archivar/eliminar (solo admin).
- **Pages Inertia separadas de endpoints API**: el form en `Create.vue` postea al endpoint API via `useForm` con `forceFormData: true` para imagen.

## 6. Riesgos y mitigaciones

| Riesgo | Mitig. |
|---|---|
| Imágenes pesadas tumban request | max:5MB en validation + nginx/php client_max_body_size |
| Race condition slug | unique constraint en DB + retry con sufijo |
| Plan check con caché stale | leer Tenant fresh en la action |

## 7. Out of scope
- Bulk import CSV
- Duplicar tour
- Reviewing tour por super admin
- Upload directo a S3 desde frontend (presigned URL) — futuro
- Maps embed real (placeholder en MVP)
