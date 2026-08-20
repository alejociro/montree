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

- [x] Ampliar `Booking\SyncBookingTravelersRequest` con `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_phone`, `eps` y `eps_other` (`email`, `phone`, `dietary_restrictions` y `medical_notes` ya están, `:40`)
- [x] `UpdatePassengerAction` con la normalización `eps ≠ other ⇒ eps_other = null` — una sola fuente de la regla para panel, viajero y seeder
- [x] `BookingTravelerResource` (zona del viajero) expone los campos nuevos **solo al dueño**
- [x] Tests de validación de `eps_other` (obligatorio / anulado)
- [x] `BookingTravelersSection.vue`: email, emergencia (nombre, parentesco, teléfono), EPS como radios en grid de 3, campo «¿Cuál EPS?» que aparece y **recibe el foco** al marcar «Otra», textarea de observaciones con el aviso «visible solo para la agencia y el guía asignado»
- [x] `DOCUMENT_TYPES` del componente sale del enum, no de la lista a mano (`:48-52`)
- [x] Cadenas nuevas en `lang/en.json`

## Fase 3 — API de la planilla (`montree-backend-dev`) · ~2,5 días

- [x] `App\Queries\PassengerManifestQuery` con eager loading y resumen
- [x] `PassengerResource` con el **único** `mergeWhen()` del permiso médico sobre `eps`, `eps_label`, `eps_other` y `medical_notes`
- [x] `PassengerManifestSummary` (omite `with_notes` sin el permiso) y `DepartureOptionResource`
- [x] `meta.can_view_medical` en la respuesta de las dos zonas
- [x] `PassengerManifestRequest`: query params + **403** en `segment=obs` sin el permiso médico
- [x] `Api\V1\Admin\TourPassengerController@index` + ruta con `can:bookings.view`
- [x] `Api\V1\Guide\TourDatePassengerController@index` + ruta con `can:guide.travelers.view` y comprobación `tourDate.guide_id === auth()->id()`
- [x] `Api\V1\Guide\GuideTourController@show` — detalle en lectura, alcance por pertenencia, `my_departures` filtrado
- [x] Eliminar `GuideController@travelers` y su ruta (`routes/api.php:91`)
- [x] Exportadores CSV (panel y guía), streamed, con BOM UTF-8; **sin** las columnas `EPS` y `Observaciones` cuando falta el permiso — no vacías, ausentes
- [x] `StorePassengerAction` / `UpdatePassengerAction` + Form Requests + `PassengerController`, con la **máscara de escritura** (`prepareForValidation()` descarta los tres campos sensibles; no rechaza la petición)
- [x] `RegisterManualPaymentAction` + `BookingPaymentController@store`
- [x] `BookingTravelerPolicy` registrada
- [x] Tests de la tabla de `plan.md §5`: privacidad, aislamiento, conteo de consultas, y los **cinco** de la Decisión 7 (campos, segmento, CSV, escritura, `with_notes`)
- [x] `php artisan wayfinder:generate` (con PHP 8.4 en el PATH)

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
- [ ] **(añadido 2026-08-20 · D10)** El formulario del viajero (`BookingTravelersSection.vue` y su
      página) consume `can_edit_travelers` y `travelers_edit_deadline` de `BookingResource`: con la
      ventana cerrada se pinta en **solo lectura** con el aviso de hasta cuándo se podía editar y el
      camino de contacto con la agencia. Nada de dejar escribir para devolver un `409`
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

## Notas durante implementación

### Fase 2 — backend (2026-08-20)

- **`UpdatePassengerAction` no existía.** Se creó en `App\Actions\Passengers\UpdatePassengerAction`
  (el namespace que fija `plan.md §3 · Actions`) y `SyncBookingTravelersAction` pasó a delegarle el
  guardado de cada viajero: la normalización `eps ≠ other ⇒ eps_other = null` queda en un solo
  sitio y la reutiliza tal cual el `PUT /api/v1/admin/passengers/{traveler}` de la Fase 3.
  El seeder no la llama: usa `BookingTravelerFactory::withOtherEps()`, que fija el par
  `eps = other` + `eps_other` de una pieza y no puede producir el estado inconsistente.
- **`BookingTravelerResource` tampoco existía.** El viajero se serializaba a mano dentro de
  `BookingResource::toArray()` (`travelers`, `:50-59`). Se extrajo a
  `App\Http\Resources\Booking\BookingTravelerResource` con un `mergeWhen()` de pertenencia.
- **«Dueño» se resuelve comparando `auth()->id()` con `booking.user_id`**, que es como ya lo hacen
  las dos entradas de la zona del viajero (`BookingController@show:59` y
  `SyncBookingTravelersRequest::booking():91`, ambas con `where('user_id', …)`). La reserva se le
  pasa al viajero con `setRelation()` desde `BookingResource` para no disparar una consulta por
  fila.
- **El bloque del dueño incluye también `medical_notes` y `dietary_restrictions`**, además de los
  cinco campos de la tarea. El endpoint de sync reemplaza al viajero entero: si el formulario de la
  Fase 2 no puede releer esos dos campos, el siguiente guardado los borra.
- **`travelers.*.document_type` pasó de `string|max:255` a `Rule::enum(DocumentType::class)`.** No
  estaba en el checklist, pero desde la Fase 1 la columna se castea a `DocumentType` y un valor
  fuera del catálogo revienta al leer el modelo, no al escribirlo. Son los mismos cinco valores que
  `BookingTravelersSection.vue:48-52` ya envía.
- **`eps_other` se anula, no se rechaza**, cuando `eps ≠ other` (acceptance criteria de `spec.md`).
  Lo que sí devuelve `422` es `eps = other` sin `eps_other`.

### Fase 2 — frontend (2026-08-20)

- **No existe generación de enums PHP → TS en el repo** (ni transformer, ni prop compartida de
  Inertia). La convención vigente es el espejo en `resources/js/types/*.ts`
  (`TOUR_DIFFICULTIES`, `SUPPORTED_CURRENCIES`), así que `DOCUMENT_TYPES` y `EPS_OPTIONS` viven
  ahí como `as const` y las uniones se derivan del array. Las etiquetas son
  `Record<DocumentType, string>` y `Record<Eps, string>` dentro del componente: si el enum de PHP
  gana o pierde un caso y el espejo se actualiza, `npm run types:check` falla hasta completar el
  mapa. Sacar el catálogo del backend en cada visita sería una prop nueva y un cambio de contrato;
  no se hizo por eso.
- **El foco del campo «¿Cuál EPS?» se lleva con `document.getElementById()` tras `nextTick()`.**
  `components/ui/input` no expone la referencia del `<input>` y el `id` del campo ya es
  determinista (`traveler-eps-other-{index}`); un `ref` de plantilla obligaría a castear `$el`.
- **Grid de 3 columnas a partir de `sm:`**, dos en móvil (`grid-cols-2 sm:grid-cols-3`): con tres
  columnas fijas «Salud Total» y «Nueva EPS» se parten a 390 px.
- **La regla `eps ≠ other ⇒ eps_other = null` se aplica también en el cliente**: al marcar otra EPS
  se limpia el texto libre y el campo desaparece, y el payload solo lleva `eps_other` con «Otra».
  Se añadieron dos validaciones espejo (`required_if:eps,other` y
  `required_with:emergency_contact_name` sobre el teléfono) que solo se muestran tras el primer
  intento de guardado; la fuente de verdad sigue siendo el Form Request.
- **El primer adulto se precarga también con el email y el contacto de emergencia del
  `contact_snapshot`** (el par nombre + teléfono solo si están los dos, por el `required_with`).
  Es el mismo dato que la migración de la Fase 1 trasladó al primer viajero, y solo se usa cuando
  la reserva todavía no tiene viajeros guardados.
- **Verificación**: `types:check`, `lint:check`, `format:check` y `npm run build` en verde, más un
  render SSR del componente con un viajero de `eps = other` (5 radios, uno marcado, campo libre
  visible con su valor, observaciones y aviso de privacidad). **No** se pudo abrir el navegador
  desde este entorno: la comprobación visual y el foco real quedan para la Fase 8.

### Fase 3 — API de la planilla (2026-08-20)

- **El único chequeo del permiso médico vive en `PassengerResource::canViewMedical()`, y la
  regla en `BookingTravelerPolicy::viewMedical`.** Las otras cuatro superficies (segmento,
  resumen, CSV y máscara de escritura) preguntan por el mismo gate, no por el string del permiso:
  el nombre `bookings.passengers.medical.view` aparece una sola vez en todo el backend.
- **La respuesta se memoiza en `$request->attributes`.** Con teams activos, cada `can()` vuelve a
  leer los roles del usuario: una planilla de 50 filas disparaba 119 consultas, 105 de ellas para
  preguntar dos veces por fila lo mismo. Lo encontró
  `PassengerManifestQueryCountTest`, que ahora exige menos de 20.
- **La planilla no sale de una sola consulta paginada sobre `booking_travelers`.** Son dos
  acotadas —viajeros y reservas sin viajeros— que se mezclan en memoria y se paginan con un
  `LengthAwarePaginator` construido a mano. Motivos: (a) la fila de marcador de posición del edge
  case no existe como registro; (b) los segmentos `due`/`paid` se deciden sobre dinero **derivado**
  (D5), que no es columna. La búsqueda sí baja a SQL por `BookingTraveler::scopeSearch()`.
  Contrapartida: la planilla de una salida se carga entera en memoria. Es una salida, no un
  catálogo; si algún día un tour tuviera miles de reservas, esto es lo primero que hay que revisar.
- ~~**Desviación menor del contrato:**~~ **RATIFICADO (2026-08-20) — es LA regla, no una
  desviación. La fila de marcador de posición conserva los campos de la reserva.** Lo que queda en
  `null` son los campos **de la persona**. `booking_number`, `tour_date_id`, `departure_starts_at`
  y `payment` se emiten, porque son hechos de la reserva y no del pasajero que falta: sin
  `payment`, esa fila no sumaría al pie de la tabla y el total por cobrar mentiría. `contracts.md
  §0` ya está corregido con este texto; no hay nada que cambiar en el código.
- **`UpdatePassengerAction::handle()` pasó a recibir el `Booking`.** Con él dentro llegó la guarda
  de estado (`Booking::isLocked()`: cancelada, expirada o reembolsada), que antes vivía suelto en
  `SyncBookingTravelersAction` con dos estados en vez de tres. Ahora los tres caminos de escritura
  —viajero, panel y pago manual— comparten la misma lista. Efecto colateral asumido: el viajero ya
  no puede editar los pasajeros de una reserva **reembolsada**; antes sí.
- **La máscara de escritura no borra: sustituye.** `prepareForValidation()` reemplaza los tres
  campos sensibles por lo que ya está guardado (en un alta, `null`). Si solo se quitaran del
  payload, la semántica de reemplazo completo de `UpdatePassengerAction` los escribiría como `null`
  y `sales` terminaría borrando la alergia que el guía necesita — justo lo que D7 prohíbe.
- **`payments` no tiene columna para la referencia del pago manual.** Va dentro de
  `gateway_response` como `{"reference": …}`. **DECIDIDO (2026-08-20): se queda así.** No se abre
  migración por un texto libre que hoy nadie consulta. Producto decidió integrar una pasarela de
  pagos más adelante; ese trabajo revisa `payments` completo y es ahí donde se decide si la
  referencia merece columna e índice. **No es un pendiente abierto.**
- **`RegisterManualPaymentAction` no notifica.** `ProcessPaymentAction` manda
  `BookingConfirmedNotification` al completar el pago; el pago de mostrador lo registra alguien que
  ya está frente al cliente. **DECIDIDO (2026-08-20): se queda así**, y se revisa junto con la
  pasarela de pagos. **No es un pendiente abierto.**
- **La zona del guía ignora `tour_date_id` y `status`** en vez de devolver 422. **RATIFICADO
  (2026-08-20): ignorar es lo correcto, no se cambia.** La salida ya va en la ruta y los estados
  son fijos, así que ninguno de los dos parámetros puede cambiar nada; romperle la pantalla a un
  cliente que reutilice el composable del panel —que sí los manda— sería castigar una tolerancia
  inofensiva. La tolerancia es solo de forma: hay test de que `?status[]=pending_payment` **no** le
  abre al guía ninguna reserva que no le corresponda. `contracts.md` ya dice «se ignoran» en vez
  de «no acepta».
- **El CSV se emite con `fputcsv`, que en PHP 8.4 entrecomilla los campos con espacios.** El
  encabezado sale como `"Nombre completo","Tipo de documento",Documento,…`: es el mismo CSV del
  contrato, citado. El test compara la fila ya parseada, no la cadena.
- **12 cadenas nuevas en `lang/en.json`** (encabezados del CSV y mensajes de error).
  `TranslationCatalogTest` falla si falta una o si sobra una huérfana; el mensaje de reserva
  bloqueada cambió de texto y hubo que retirar el viejo.
- **`GuideRouteAccessTest` apunta ahora a `/passengers`.** Era el único consumidor de la ruta
  eliminada; sus tres casos (guía propio, operador, admin ajeno) siguen valiendo igual contra el
  endpoint nuevo.
- **Verificación**: `php artisan test --compact` → 652/652 (era 603, +49). `vendor/bin/pint
  --dirty` en verde. `php artisan wayfinder:generate` sin errores (genera
  `TourPassengerController`, `TourPassengerExportController`, `PassengerController`,
  `BookingPaymentController`, `Guide/TourDatePassengerController`,
  `Guide/TourDatePassengerExportController` y `Guide/GuideTourController`).


### D10 — ventana de edición del viajero (2026-08-20)

Decisión nueva ratificada por producto: el titular edita a sus acompañantes hasta **24 h antes**
de `tour_dates.starts_at`; pasada esa hora la planilla se congela **solo para él**.

- [x] `config/montree.php` → `passengers.traveler_edit_cutoff_hours` (env `MONTREE_TRAVELER_EDIT_CUTOFF_HOURS`, default 24). El 24 no aparece en ninguna rama de la lógica.
- [x] `Booking::travelerEditDeadline(): ?CarbonInterface` y `Booking::isTravelerEditWindowClosed(): bool`
- [x] La guarda se aplica **solo** en `SyncBookingTravelersAction`, después de `isLocked()`
- [x] `BookingException::travelerEditWindowClosed()` — mismo tipo y misma forma (`message` + `error_code`) que `travelersLocked()`, `409`, código `BOOKING_TRAVELER_EDIT_WINDOW_CLOSED`
- [x] `BookingResource`: `can_edit_travelers` (bool) y `travelers_edit_deadline` (ISO8601 o `null`)
- [x] Cadena nueva en `lang/en.json`
- [x] `tests/Feature/Passengers/TravelerEditWindowTest.php` (7) + `tests/Unit/Booking/TravelerEditDeadlineTest.php` (2)

Notas:

- **El cutoff NO entró en `Booking::isLocked()`.** Esa guarda la comparten los tres caminos de
  escritura (`SyncBookingTravelersAction:22`, `UpdatePassengerAction:45`,
  `RegisterManualPaymentAction:30`); metido ahí, habría congelado también el panel y el pago
  manual, que son justo por donde se resuelve el cambio de última hora.
- **Sin salida o sin `starts_at` ⇒ `deadline = null` ⇒ no bloquea.** A nivel de esquema
  `bookings.tour_date_id` y `tour_dates.starts_at` son `NOT NULL`, así que el caso solo se alcanza
  con la relación no resuelta; se cubre con un test unitario, no con uno de HTTP que tendría que
  falsear el esquema.
- **Salida ya iniciada o pasada:** la cubre la misma comparación (`now() >= starts_at - cutoff`),
  sin rama aparte.
- **Se eligió `409`, no `422`**, por coherencia con el `BOOKING_TRAVELERS_LOCKED` que ya emite ese
  mismo camino: los dos son una regla de estado, no un problema del payload.
- ~~**CONTRATO PENDIENTE DE ACTUALIZAR.**~~ **RESUELTO (2026-08-20).** `contracts.md` ya tiene la
  sección «Shape compartido: `BookingResource` (zona del viajero)» con el shape real, el endpoint
  `GET /api/v1/bookings/{bookingNumber}`, los dos campos nuevos (`can_edit_travelers: boolean`,
  `travelers_edit_deadline: string|null` ISO8601), el bloque «Ventana de edición del viajero (D10)»
  y la tabla de errores de `PUT /api/v1/bookings/{bookingNumber}/travelers` con
  `409 BOOKING_TRAVELER_EDIT_WINDOW_CLOSED`. La decisión quedó además como **fila 8** de la tabla
  de decisiones de `spec.md` y como **D10** en `plan.md §2`.
- [ ] **Pendiente de frontend (Fase 4).** El formulario del viajero todavía deja escribir con la
  ventana cerrada y solo se entera por el `409`. Ítem añadido al checklist de la Fase 4.
- **Verificación:** `php artisan test --compact` → 661/661 (era 652, +9). `vendor/bin/pint --dirty`
  en verde.


## Pendiente heredado

- [ ] Borrar las ramas `backup/pre-author-fix-*` cuando el usuario confirme

---

## Changelog

- `2026-08-20` — Generación inicial a partir de `plan.md`.
- `2026-08-20` — Se registran las notas de implementación de las Fases 2 y 3.
- `2026-08-20` — **D10 (ventana de edición del viajero).** Se marca el bloque backend como hecho
  (661/661 en la suite, +9) y se **añade un ítem al checklist de la Fase 4**: el formulario del
  viajero debe consumir `can_edit_travelers`/`travelers_edit_deadline` y pintarse en solo lectura
  con el aviso, en vez de dejar escribir y recibir el `409`. La nota «CONTRATO PENDIENTE DE
  ACTUALIZAR» queda **resuelta**: `contracts.md` ya documenta `BookingResource`,
  `GET /api/v1/bookings/{bookingNumber}` y el error nuevo.
- `2026-08-20` — Tres observaciones de la Fase 3 pasan de deuda a **comportamiento ratificado**:
  (a) la zona del guía ignora `tour_date_id`/`status`; (b) la fila de marcador de posición conserva
  `payment`; (c) el pago manual se queda como está —referencia en `gateway_response`, sin
  notificación— y se revisa cuando entre la pasarela de pagos. Ninguna de las tres genera trabajo
  pendiente. Se conserva el texto original tachado para no perder el rastro.
