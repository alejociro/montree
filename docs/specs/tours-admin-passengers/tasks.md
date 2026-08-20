# tours-admin-passengers — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Generado a partir de [`plan.md`](./plan.md). Modificaciones se reflejan en ambos.

Rama: `feature/tours-admin-passenger-manifest`, desde `develop`.
Entorno: el PHP del PATH es 7.4 y el proyecto exige 8.4 — anteponer
`/opt/homebrew/opt/php@8.4/bin` o fallan `migrate`, `test`, `wayfinder:generate` y `npm run build`.

**Regla de método:** ninguna tarea se dimensiona ni se decide mirando lo que hay cargado en una
base de datos. Migraciones y seeders se diseñan para cualquier estado inicial (plan.md §0).

Orden no negociable: **Fase 2 antes que Fase 4** (si no, la planilla nace vacía) y
**Fase 5 antes que Fase 6** (si no, el select de guía se construye dos veces).

---

## Fase 0 — Decisiones

- [x] **D1** El guía ve el detalle completo en lectura, incluidos los datos de salud; no modifica nada
- [x] **D2** Toda salida lleva guía; `guide_id` pasa a `NOT NULL`; se elimina «Falta guía»
- [x] **D3** Un guía, una salida a la vez, por días calendario completos; `ends_at` derivado
- [x] **D4** La paleta es del tenant; los semánticos quedan fijos, con 5 tokens nuevos
- [x] **D5** El saldo del pasajero se calcula, no se guarda
- [x] **D6** El checklist de publicación avisa; no bloquea más de lo que ya bloquea
- [x] **D7** `sales` no ve EPS ni observaciones: permiso `bookings.passengers.medical.view`

## Fase 1 — Esquema (`montree-db-architect`) · ~2 días

- [x] Migration `add_health_and_emergency_fields_to_booking_travelers_table` (`eps`, `eps_other`, `emergency_contact_relationship`, índice `[tenant_id, document_number]`)
- [x] La misma migración traslada el contacto de emergencia de `bookings.contact_snapshot` al primer viajero de cada reserva que lo tenga; idempotente
- [x] Migration `add_default_guide_id_to_tours_table`
- [x] Migration `require_guide_and_derive_ends_at_on_tour_dates_table`: recalcula `ends_at` de todas las salidas, asigna guía a las que no tengan, **aborta con el listado** si algún tenant no tiene ningún usuario `guide`, pasa `guide_id` a `NOT NULL` y **reporta los solapes** que encuentre
- [x] `App\Enums\DocumentType` (`cc · ce · ti · passport · other`, sin `nit`) con `label()`
- [x] `App\Enums\Eps` (`sura · nueva_eps · sanitas · salud_total · other`) con `label()` y `requiresDetail()`
- [x] `BookingTraveler`: `$fillable`, casts, accessor `epsLabel()`, scope `search()`
- [x] `Tour::defaultGuide()`; `TourDate::occupiedDays()` y scope `occupying()`; `Booking::dueAmount()` y `Booking::passengerShare()`
- [x] `TourDateFactory`: `ends_at` derivado de `duration_hours` (hoy suma 4 h fijas, `:30`) y reparto de guías **sin solapes**
- [x] `BookingTravelerFactory`: estados `withDue()`, `withNotes()`, `withOtherEps()`
- [x] `DemoTenantSeeder`: una salida con pasajeros que cubran los tres casos (con saldo, con observaciones, con EPS «Otra») y un tour de varios días para probar el bloqueo del guía
- [x] Permiso `bookings.passengers.medical.view` en `RolesAndPermissionsSeeder`: módulo `bookings`, roles `admin` y `guide`. **No** `sales`, **no** `operator`
- [x] `php artisan migrate:fresh --seed` en verde

## Fase 2 — Captura del dato (`montree-backend-dev` + `montree-frontend-dev`) · ~1 día

Va antes que la planilla: sin esto la planilla nace vacía.

- [ ] Ampliar `Booking\SyncBookingTravelersRequest` con `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `eps` y `eps_other` (`email`, `phone`, `dietary_restrictions` y `medical_notes` ya están, `:40`)
- [ ] `UpdatePassengerAction` con la normalización `eps ≠ other ⇒ eps_other = null` — una sola fuente de la regla para panel, viajero y seeder
- [ ] `BookingTravelerResource` (zona del viajero) expone los campos nuevos **solo al dueño**
- [ ] Tests de validación de `eps_other` (obligatorio / anulado)
- [ ] `BookingTravelersSection.vue`: email, emergencia (nombre, parentesco, teléfono), EPS como radios en grid de 3, campo «¿Cuál EPS?» que aparece y **recibe el foco** al marcar «Otra», textarea de observaciones con el aviso «visible solo para la agencia y el guía asignado»
- [ ] `DOCUMENT_TYPES` del componente sale del enum, no de la lista a mano (`:48-52`)
- [ ] Cadenas nuevas en `lang/en.json`

## Fase 3 — API de la planilla (`montree-backend-dev`) · ~2,5 días

- [ ] `App\Queries\PassengerManifestQuery` con eager loading y resumen
- [ ] `PassengerResource` con el **único** `mergeWhen()` del permiso médico sobre `eps`, `eps_label`, `eps_other` y `medical_notes`
- [ ] `PassengerManifestSummary` (omite `with_notes` sin el permiso) y `DepartureOptionResource`
- [ ] `meta.can_view_medical` en la respuesta de las dos zonas
- [ ] `PassengerManifestRequest`: query params + **403** en `segment=obs` sin el permiso médico
- [ ] `Api\V1\Admin\TourPassengerController@index` + ruta con `can:bookings.view`
- [ ] `Api\V1\Guide\TourDatePassengerController@index` + ruta con `can:guide.travelers.view` y comprobación `tourDate.guide_id === auth()->id()`
- [ ] `Api\V1\Guide\GuideTourController@show` — detalle en lectura, alcance por pertenencia, `my_departures` filtrado
- [ ] Eliminar `GuideController@travelers` y su ruta (`routes/api.php:91`)
- [ ] Exportadores CSV (panel y guía), streamed, con BOM UTF-8; **sin** las columnas `EPS` y `Observaciones` cuando falta el permiso — no vacías, ausentes
- [ ] `StorePassengerAction` / `UpdatePassengerAction` + Form Requests + `PassengerController`, con la **máscara de escritura** (`prepareForValidation()` descarta los tres campos sensibles; no rechaza la petición)
- [ ] `RegisterManualPaymentAction` + `BookingPaymentController@store`
- [ ] `BookingTravelerPolicy` registrada
- [ ] Tests de la tabla de `plan.md §5`: privacidad, aislamiento, conteo de consultas, y los **cinco** de la Decisión 7 (campos, segmento, CSV, escritura, `with_notes`)
- [ ] `php artisan wayfinder:generate` (con PHP 8.4 en el PATH)

## Fase 4 — Planilla + zona del guía (`montree-frontend-dev`) · ~3 días

- [ ] `types/passenger.ts` espejo de `contracts.md §0`, con `can_view_medical`
- [ ] `composables/usePassengerManifest.ts` con las dos fuentes (`tour` | `departure`)
- [ ] Atoms `InitialsAvatar.vue`, `MonoLabel.vue`
- [ ] Molecules `PassengerRow`, `PassengerFilters`, `PaymentStatusChip`, `PassengerDrawer`, `PassengerFormDialog`
- [ ] Organism `PassengerManifest.vue` (tabla sticky, estado vacío explícito, pie con totales, `:readonly`)
- [ ] Sin `can_view_medical`: la columna de observaciones **no se dibuja**, el segmento no se ofrece y el conteo del pie no aparece — nada de guiones ni candados
- [ ] Con el permiso: la observación médica se **resalta** en la fila (terracota + semibold)
- [ ] Fila de marcador de posición «Datos pendientes» para reservas sin viajeros cargados
- [ ] Hoja de impresión `@media print` dentro del organism, que imprime **todo el resultado filtrado**
- [ ] Pestaña «Pasajeros» en `Admin/Tour/Show.vue`, visible solo con `bookings.view`
- [ ] Página `Guide/Passengers.vue` + ruta + enlace desde `Guide/Schedule.vue`
- [ ] Página `Guide/TourShow.vue`: contenido, ruta y mapa (`TourRouteMapSection` de PR #15), itinerario, logística, **sus** salidas y la planilla, sin ninguna acción de escritura
- [ ] Estados loading (skeleton de filas), error y vacío
- [ ] Cadenas nuevas en `lang/en.json`

## Fase 5 — Guía obligatorio y disponibilidad (`montree-backend-dev` + `montree-frontend-dev`) · ~2 días

- [ ] `App\Rules\GuideIsAvailable`: solape de `[date(starts_at) … date(ends_at)]` contra las salidas `open`/`closed` del mismo guía, excluyendo la que se edita
- [ ] Los **tres** caminos: `StoreTourDateRequest`, `UpdateTourDateRequest` y `App\Actions\Team\AssignGuideAction` (hoy no valida nada)
- [ ] `guide_id` `required` en los dos Form Requests y en la Action; se elimina «Sin asignar» del contrato
- [ ] `ends_at` **derivado** en `CreateTourDateAction` / `UpdateTourDateAction` y `prohibited` en los Form Requests
- [ ] `App\Queries\GuideAvailabilityQuery` + `GET /api/v1/admin/guides/availability?from&to&exclude_tour_date_id`
- [ ] `useGuideAvailability` + `GuideSelect.vue`: ocupados deshabilitados con el motivo («Ocupado 12–14 sep · Valle de Cocora»)
- [ ] Aviso al cambiar `duration_hours`: se listan las salidas que quedarían en solape antes de guardar
- [ ] `default_guide_id` obligatorio al publicar el tour
- [ ] Tests: mismo día, rango de 3 días, salida cancelada que libera, edición de la propia salida sin falso positivo, el camino del `PATCH`, `guide_id` ausente ⇒ 422, `ends_at` del cliente ⇒ 422, derivación correcta para un tour de 51 h, y que factory y seeder no puedan producir un solape

## Fase 6 — Rediseño del CRUD (`montree-frontend-dev`, un commit por pantalla) · 3–4 días

- [ ] 5 tokens nuevos en `resources/css/app.css` (D5): `--brand-warn`, `--brand-warn-50`, `--brand-drop-50`, `--brand-green-50`, `--brand-line-2`
- [ ] **Index**: `TourKpiGrid`, toolbar de pills + buscador + categoría + orden, `TourAdminCard` con próxima salida / pasajeros / `OccupancyBar` / línea de saldos, pie de paginación
- [ ] **Crear**: bloques en cards, `ChipsInput` para incluye / no incluye / requisitos, `DifficultySelector` con `aria-pressed`, `TourProgressRail`, `TourPublishChecklist`, savebar sticky
- [ ] **Editar**: head con contexto, pestañas `Contenido · Ruta y mapa · Salidas (n) · Pasajeros (n)`, `TourImpactCard`, `TourDeparturesTable` con el `GuideSelect` **de la Fase 5** en cada fila, savebar con contador de cambios
- [ ] **Detalle**: hero, pestañas `Resumen · Pasajeros · Ruta`, 4 KPIs, ocupación de próximas salidas, itinerario como línea de tiempo, mapa de solo lectura
- [ ] Nada de «Falta guía»: no existe el estado (D7). Se eliminan etiqueta, acción, select ámbar y opción «Sin asignar»
- [ ] `fit()` (`invalidateSize()` + `fitBounds()`) al activarse cada pestaña con mapa
- [ ] Los primarios, el subrayado de pestaña, las barras de ocupación y el pin de recogida van por `--primary` (color del tenant); los estados, por los tokens semánticos fijos
- [ ] Responsive: `1180px` colapsa a una columna; verificar 390/430/768/1024/1280/1440 sin desbordamiento horizontal

## Fase 7 — Reglas restantes (`montree-backend-dev`) · ~0,5 día

- [ ] `default_guide_id` se propone al crear una salida (`AdminTourDateController@store`)
- [ ] Checklist de publicación calculado en la respuesta del tour (qué falta, por condición). Bloquean nombre, resumen, precio, cupo e imagen; las paradas se recomiendan (D7)
- [ ] `NotifyPickupChangeAction` + `PickupPointChangedNotification` encolada, disparada desde `SyncTourStopsAction` cuando cambia una parada `pickup` y hay reservas activas
- [ ] Aviso en la UI **antes** de guardar el cambio de recogida
- [ ] Tests de las tres reglas

## Fase 8 — Cierre · ~1 día

- [ ] Avisar al equipo de QA antes de correr las migraciones de la Fase 1 en su entorno: `ends_at`, `NOT NULL` y el reparto de guías reescriben lo que estén mirando
- [ ] `vendor/bin/pint --dirty`
- [ ] `npm run types:check`, `npm run lint:check`, `npm run format:check`
- [ ] `php artisan test --compact` (594/594 en `develop` al 2026-08-20: no debe bajar)
- [ ] `npm run build`
- [ ] Navegador con las **tres** cuentas —admin, ventas y guía—: la Decisión 7 solo se ve comparando las dos primeras. 0 errores de consola
- [ ] Navegador con **dos tenants de colores distintos** para comprobar la Decisión 4
- [ ] `montree-reviewer` sobre el feature completo → GO
- [ ] Actualizar el Changelog de `docs/specs/F003-tour-crud/spec.md` marcándola superada
- [ ] Actualizar `docs/specs/README.md` con la entrada del feature
- [ ] PR `feature/tours-admin-passenger-manifest` → `develop` (rama y mensajes en inglés, sin co-author)

---

## Pendiente heredado

- [ ] Borrar las ramas `backup/pre-author-fix-*` cuando el usuario confirme
