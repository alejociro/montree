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

- [x] `types/passenger.ts` espejo de `contracts.md §0`, con `can_view_medical`
- [x] `composables/usePassengerManifest.ts` con las dos fuentes (`tour` | `departure`)
- [x] Atoms `InitialsAvatar.vue`, `MonoLabel.vue`
- [x] Molecules `PassengerRow`, `PassengerFilters`, `PaymentStatusChip`, `PassengerDrawer`, `PassengerFormDialog`
- [x] Organism `PassengerManifest.vue` (tabla sticky, estado vacío explícito, pie con totales, `:readonly`)
- [x] Sin `can_view_medical`: la columna de observaciones **no se dibuja**, el segmento no se ofrece y el conteo del pie no aparece — nada de guiones ni candados
- [x] Con el permiso: la observación médica se **resalta** en la fila (terracota + semibold)
- [x] Fila de marcador de posición «Datos pendientes» para reservas sin viajeros cargados
- [x] Hoja de impresión `@media print` dentro del organism, que imprime **todo el resultado filtrado**
- [x] Pestaña «Pasajeros» en `Admin/Tour/Show.vue`, visible solo con `bookings.view`
- [x] Página `Guide/Passengers.vue` + ruta + enlace desde `Guide/Schedule.vue`
- [x] Página `Guide/TourShow.vue`: contenido, ruta y mapa (`TourRouteMapSection` de PR #15), itinerario, logística, **sus** salidas y la planilla, sin ninguna acción de escritura
- [x] Estados loading (skeleton de filas), error y vacío
- [x] **(añadido 2026-08-20 · D10)** El formulario del viajero (`BookingTravelersSection.vue` y su
      página) consume `can_edit_travelers` y `travelers_edit_deadline` de `BookingResource`: con la
      ventana cerrada se pinta en **solo lectura** con el aviso de hasta cuándo se podía editar y el
      camino de contacto con la agencia. Nada de dejar escribir para devolver un `409`
- [x] Cadenas nuevas en `lang/en.json`

## Fase 5 — Guía obligatorio y disponibilidad (`montree-backend-dev` + `montree-frontend-dev`) · ~2 días

- [x] `App\Rules\GuideIsAvailable`: solape de `[date(starts_at) … date(ends_at)]` contra las salidas `open`/`closed` del mismo guía, excluyendo la que se edita
- [x] Los **tres** caminos: `StoreTourDateRequest`, `UpdateTourDateRequest` y `App\Actions\Team\AssignGuideAction` (hoy no valida nada). El `PATCH` estrena `AssignGuideRequest`: además de la disponibilidad, comprueba que el usuario sea guía **del tenant** — antes aceptaba cualquier `users.id` del mundo
- [x] `guide_id` `required` en los dos Form Requests y en la Action; se elimina «Sin asignar» del contrato
- [x] `ends_at` **derivado** en `CreateTourDateAction` / `UpdateTourDateAction` y `prohibited` en los Form Requests. `UpdateTourDateAction` lo **rederiva siempre**, no solo cuando cambia el inicio: así la salida vieja con el fin inventado se corrige la primera vez que alguien la toca
- [x] `App\Queries\GuideAvailabilityQuery` + `GET /api/v1/admin/guides/availability?from&to&exclude_tour_date_id`
- [x] `useGuideAvailability` + `GuideSelect.vue`: ocupados deshabilitados con el motivo («Ocupado 12–14 sep · Valle de Cocora»)
- [x] Aviso al cambiar `duration_hours`: se listan las salidas que quedarían en solape antes de guardar (`422` en `duration_hours`, con los nombres). Solo mira salidas **futuras**: alargar un tour no puede romper lo que ya pasó
- [x] `default_guide_id` obligatorio al publicar el tour — **desviación**: no vive en `ChangeTourStatusRequest` sino en `ChangeTourStatusAction`, junto a la comprobación de imagen, con `error_code` `TOUR_NEEDS_GUIDE_TO_ACTIVATE`. El endpoint de estado tiene su propio contrato de error (`error_code` en la raíz, sin `errors`), y meterlo como error de validación lo habría partido en dos formas distintas de decir lo mismo
- [x] **(añadido)** `default_guide_id` entra a `StoreTourRequest`, `UpdateTourRequest` y `TourResource`, y el formulario del tour estrena su selector. Sin esto la regla de publicación era insatisfacible: la columna existía desde la Fase 1 pero ninguna entrada la escribía
- [x] Tests: mismo día, rango de 3 días, salida cancelada que libera, edición de la propia salida sin falso positivo, mover el inicio a un día ocupado sin cambiar de guía, el camino del `PATCH`, `guide_id` ausente ⇒ 422 en los tres, `ends_at` del cliente ⇒ 422, derivación correcta para un tour de 51 h, tope de 180 días del endpoint, y que ni la factory ni el seeder demo puedan producir un solape

## Fase 6 — Rediseño del CRUD (`montree-frontend-dev`, un commit por pantalla) · 3–4 días

- [x] 5 tokens nuevos en `resources/css/app.css` (D5): `--brand-warn`, `--brand-warn-50`, `--brand-drop-50`, `--brand-green-50`, `--brand-line-2` — **adelantados en la Fase 4**, que ya los necesitaba para el chip de pago y la observación resaltada. Con los nombres exactos de D5: esta fase no tiene nada que rehacer, solo usarlos
- [x] **Index**: `TourKpiGrid`, toolbar de pills + buscador + categoría + orden, `TourAdminCard` con próxima salida / pasajeros / `OccupancyBar` / línea de saldos, pie de paginación
- [x] **Crear**: bloques en cards, `ChipsInput` para incluye / no incluye / requisitos, `DifficultySelector` con `aria-pressed`, `TourProgressRail`, `TourPublishChecklist`, savebar sticky
- [x] **Editar**: head con contexto, pestañas `Contenido · Ruta y mapa · Salidas (n) · Pasajeros (n)`, `TourImpactCard`, `TourDeparturesTable` con el `GuideSelect` **de la Fase 5** en cada fila, savebar con contador de cambios
- [x] **Detalle**: hero, pestañas `Resumen · Pasajeros · Ruta`, 4 KPIs, ocupación de próximas salidas, itinerario como línea de tiempo, mapa de solo lectura
- [x] Nada de «Falta guía»: no existe el estado (D7). Se eliminan etiqueta, acción, select ámbar y opción «Sin asignar»
- [x] `fit()` (`invalidateSize()` + `fitBounds()`) al activarse cada pestaña con mapa
- [x] Los primarios, el subrayado de pestaña, las barras de ocupación y el pin de recogida van por `--primary` (color del tenant); los estados, por los tokens semánticos fijos
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

### Fase 6 — cierre de frontend (2026-08-20)

Los dos cabos sueltos que quedaban del rediseño. Suite en **702/702** (sin cambio: nada de esto es
backend); Pint, `types:check`, `lint:check`, `format:check` y `npm run build` en verde.

1. **Los tres órdenes operativos del handoff ya se pueden elegir.** `TOUR_SORT_PARAMS`
   (`resources/js/types/tour.ts`) y el select de `TourFilters.vue` suman «Ordenar: próxima
   salida», «Ordenar: ocupación» y «Ordenar: ingresos», que mapean a los `sort`
   `next_departure`, `occupancy` y `revenue` del commit `ee44baf`. La dirección va fijada por
   orden y no se expone: la próxima salida se lee de más cercana a más lejana (`asc`), ocupación
   e ingresos de mayor a menor (`desc`). El comentario que decía «la API todavía no expone estos
   datos» queda retirado porque ya no es cierto.
2. **`TourKpiGrid` y el bloque operativo de `TourAdminCard` calzan con lo que el backend emite.**
   Se comprobaron campo por campo contra `BuildTourIndexStatsAction` y
   `TourSummaryResource::operations()`: `tours`, `upcoming_departures`, `occupancy` y
   `pending_balance` opcional en el primero; `next_departure_at`, `passengers_count`,
   `occupancy.{occupied,capacity}` y `passengers_with_due` en el segundo. **No hubo nada que
   corregir**: la ausencia de `pending_balance` sin `bookings.view` ya tumbaba el KPI entero en
   vez de pintar un cero, y la tarjeta ya ocultaba el bloque cuando `operations` no viaja.
3. **El patrón ARIA de pestañas queda completo.** `TourTabs.vue` añade `aria-controls`, tabulador
   móvil (`tabindex` 0 en la activa, −1 en el resto) y navegación por ← → con Home/End, con
   activación automática: los paneles ya están montados, así que moverse no cuesta una carga.
   Los paneles de «Editar» (`Contenido · Ruta y mapa · Salidas · Pasajeros`) y «Detalle»
   (`Resumen · Pasajeros · Ruta`) estrenan `role="tabpanel"`, `aria-labelledby` y `tabindex="0"`.
   Los `id` de pestaña y panel salen de `resources/js/lib/tour-tabs.ts` y no de plantillas
   repetidas a mano: son la única cuerda que une la barra con su panel.
   - La columna de ayudas de «Editar» (riel, checklist, impacto) acompaña a la pestaña
     «Contenido» pero vive **fuera** de su `tabpanel`, porque la rejilla la pone al lado del
     formulario. Se queda como landmark complementario con `aria-label` propio en vez de ser un
     segundo `tabpanel` de la misma pestaña, que no sería válido.
   - «Pasajeros» se monta con `v-if` a propósito (dispara el fetch de la planilla), así que su
     `aria-controls` apunta a un `id` que solo existe con la pestaña abierta. Se prefiere eso a
     pedir los pasajeros en cada visita.
4. **Las pills de estado del Index dejan de ser un `tablist`.** Filtran el listado; no cambian de
   panel. Un `tablist` sin `tabpanel` que gobernar promete al lector de pantalla una navegación
   por paneles que no existe, y arrastra expectativas de teclado (flechas, tabulador móvil) que
   tampoco se cumplían. Pasan a ser **botones de alternancia mutuamente excluyentes dentro de un
   `role="group"` con nombre** (`aria-label="Filtrar por estado"`), y el estado se anuncia con
   `aria-pressed` en vez de `aria-selected`. Se descartó `radiogroup`: obligaría a un tabulador
   móvil y a navegación por flechas dentro del grupo, más ceremonia de la que un filtro de una
   línea necesita, y perdería el «Tab llega a cada pill» que hoy funciona.

Cinco claves nuevas en `lang/en.json`, ninguna huérfana.

**Pendiente para la Fase 8:** todo lo visual. Sin navegador en el entorno no se verificaron la
navegación real con teclado en las dos pantallas, el anuncio del lector de pantalla, ni el
responsive del checklist de la Fase 6 (390/430/768/1024/1280/1440).

### Fase 6 — Detalle (2026-08-20)

- **Tres pestañas de verdad: `Resumen · Pasajeros (n) · Ruta`,** con el `TourTabs` de «Editar» —
  ningún tercer copiar-pegar de la barra. «Ruta» solo existe si hay algo que dibujar
  (`routeStopsFromTour`: las paradas guardadas o, sin ellas, el punto de encuentro); «Pasajeros»
  solo con `bookings.view`. El contador de la pestaña sale de `meta.summary.total_passengers`
  (`useTourManifestSummary`, la misma llamada que usa «Editar»): mientras no llega, no se dibuja.
- **`KpiCard` (atom) nuevo, compartido con el Index.** `TourKpiGrid` pasó a usarlo y el detalle
  monta sus cuatro KPIs con el mismo componente; el estilo `alert` (`--brand-drop`) vive en un solo
  sitio. Verificado que el Index no cambia de aspecto: mismas clases, mismo `v-for`.
- **Los 4 KPIs, todos con dato real.** Reservas, Pasajeros e «Ingresos cobrados» salen de
  `BuildTourShowStatsAction`. El cuarto es **«Saldo pendiente»** desde `meta.summary`
  (`total_due_amount` + `with_due`), con esqueleto mientras carga; **sin `bookings.view` no hay
  saldo que mostrar y ese hueco lo ocupa «Calificación»**, que siempre es dato del tour. La
  maqueta pinta además «de $3.250.000 facturados» bajo los ingresos: el total facturado no existe
  en la respuesta, así que no se dibujó.
- **Ocupación de las próximas salidas.** La cifra agregada (`stats.occupancy_upcoming`) va en una
  `OccupancyBar` por `--primary` y debajo la lista de salidas —`TourUpcomingDatesList`, que
  también estrena `OccupancyBar` en vez de su barra a mano—, cada una con «Ver pasajeros» (emite y
  abre la pestaña; la planilla no acepta filtro inicial, la salida se elige en su selector). Sin
  salidas próximas: vacío explícito con «Programar salida».
- **Itinerario como línea de tiempo** al lado de la ocupación, en dos columnas que colapsan en
  `1180px`. Los conectores usan `--brand-line-2`; el número del paso, `--primary`.
- **Mapa de solo lectura** en la pestaña «Ruta»: `TourRouteMapSection` tal cual, con `v-show` y
  `fit()` en el `watch` de la pestaña (`invalidateSize()` + reencuadre) — el mismo camino que
  «Editar». Con `v-if` se recrearía Leaflet en cada visita. La tarjeta lleva «Editar paradas» y,
  si hay coordenadas de encuentro, el enlace a Google Maps.
- **Barrido de «Falta guía» (D7), cerrado para todo el feature.** `grep` por `Sin asignar`,
  `unassigned`, `needs_guide`, `falta guía`, `missing guide`, `sin guía` y `asignar guía` sobre
  `resources`, `app`, `routes`, `lang`, `tests` y `database`: no queda ni etiqueta, ni acción, ni
  opción, ni select ámbar. Lo único que sobrevive con ese nombre es el permiso RBAC
  `departures.assign_guide` («Asignar guía a una salida»), que es la capacidad de cambiar el guía
  —no un estado de falta— y los comentarios/mensajes que explican la regla.
- **Reparto `--primary` vs. tokens fijos, verificado en las cuatro pantallas.** Dos correcciones:
  (a) el estado de una **salida** se pintaba con las variantes de `Badge` —«Abierta» en
  `--primary`— igual que le pasaba a `TourStatusBadge` antes del Index; nace
  `molecules/TourDateStatusBadge.vue` con los tokens fijos y lo usan `TourDeparturesTable` (Editar)
  y `TourUpcomingDatesList` (Detalle). (b) el **pin de recogida** iba por `--brand-green-600`;
  D5 lo pone en `--primary`, así que `ROUTE_COLOR_TOKENS.pickup` pasa a `--primary` con el mismo
  fallback. Los demás pines (zona, regreso, traslado) siguen fijos porque son semánticos. Nota:
  `routeColor()` cachea el valor computado, así que si `useTenantBranding` escribiera `--primary`
  después del primer mapa, ese primer render usaría el color anterior — hoy la marca se aplica en
  el arranque, antes de montar cualquier mapa.
- **Datos que la pantalla necesita y el backend hoy no entrega:** el total **facturado** del tour
  (la maqueta lo pone bajo los ingresos cobrados), la fecha de vencimiento del saldo («vence el 25
  oct»), y el saldo pendiente **sin** `bookings.view` — no hay una cifra agregada de dinero que
  `sales` pueda ver sin la planilla. Ninguno se dibujó.
- **Higiene de `lang/en.json`:** se agregaron 14 claves y se quitaron 5 que quedaron huérfanas al
  reescribir la pantalla (`TranslationCatalogTest` las detecta y era lo único rojo de la suite).
- **Verificación.** `pint --dirty`, `types:check`, `lint:check`, `format:check`, `build` y
  `php artisan test --compact` (692/692) en verde. **Sin comprobación en navegador**: este agente
  tampoco tenía la herramienta; los breakpoints 390/430/768/1024/1280/1440 y los 0 errores de
  consola quedan para el barrido de la Fase 8 (por eso el ítem de responsive sigue sin marcar).

### Fase 6 — datos del Index (backend) (2026-08-20)

- **Los dos bloques que el Index dibujaba con `v-if` ya tienen dato.** `TourPagesController@index`
  manda la prop `stats` (`TourIndexStats`) y `TourSummaryResource` emite `operations`
  (`TourOperationalSummary`), ambos con la forma exacta ya tipada en `resources/js/types/tour.ts`.
  Formas documentadas en `contracts.md`, sección «Cifras del listado de tours del panel».
- **Un solo criterio para el detalle y el listado.** `BuildTourIndexStatsAction` reusa el de
  `BuildTourShowStatsAction`: salida futura en `open` o `full`, pagos `completed`, dinero derivado
  de `bookings` (D5). Dos precisiones que quedaron escritas en el contrato: la ocupación del
  encabezado mira la **misma ventana de 30 días** que el conteo de salidas, y `next_starts_at` mira
  **todas** las futuras — si la próxima cae en el día 45, la fecha sigue siendo cierta aunque el
  contador diga 0.
- **`pending_balance` no viaja sin `bookings.view`.** Es dinero de pasajeros: el mismo permiso que
  abre la planilla (`BookingPolicy@viewAny`). `operator` tiene `tours.view` y entra al listado, así
  que la clave se **omite**, no se manda en cero. Consecuencia mínima en el frontend, y era
  obligatoria para no romper la pantalla: `TourIndexStats.pending_balance` pasa a opcional y
  `TourKpiGrid` deja de pintar ese KPI cuando no llega (antes leía `pending_balance.passengers` sin
  guarda y habría reventado para un operador). Es lo único que se tocó fuera del backend.
- **`operations` sin N+1: subconsultas correlacionadas, no relaciones por fila.**
  `App\Queries\TourOperationalSummaryQuery` cuelga cinco agregados del `select` (fecha, cupos y
  capacidad de la próxima salida, viajeros y viajeros con saldo). `TourIndexQueryCountTest` corre
  el mismo listado con 3 y con 30 tours —cada uno con salida, reserva y pasajeros— y exige el
  **mismo** conteo de consultas; mide en caliente porque la primera petición paga el catálogo de
  permisos.
- **El campo es condicional, no universal.** `TourSummaryResource` lo sirve solo si la consulta
  adjuntó los agregados (`ops_*`): el selector de tours de promociones usa el mismo Resource y ahí
  `operations` no existe, en vez de ceros que se leerían como «este tour no tiene pasajeros».
- **Órdenes nuevos: los tres del handoff entran.** `next_departure`, `occupancy` y `revenue` se
  resuelven en SQL con la misma subconsulta correlacionada que alimenta `operations`; ninguno
  quedó fuera. `revenue` es el más caro (subconsulta con `join` a `payments`) y ordenar obliga a
  evaluarla sobre todo el catálogo del tenant, no solo sobre la página: aceptable porque el plan
  más alto tope 500 tours (`TenantPlan::limits()['max_tours']`). Si algún día ese tope sube, este
  orden es el primer candidato a materializarse. **Falta el mapeo en el frontend**:
  `TOUR_SORT_PARAMS` y las opciones del select de `TourFilters` siguen con los cuatro de siempre —
  se dejó al agente de frontend, que es quien pone las etiquetas.
- **`passengers_with_due` sí viaja sin `bookings.view`**, a diferencia de `pending_balance`: es un
  conteo de personas, no una cifra de dinero, y es la misma información operativa que ya da la
  ocupación. Si producto lo considera dinero, se cubre con el mismo `mergeWhen`.
- **Verificación.** `pint --dirty`, `types:check`, `lint:check`, `format:check`, `build` y
  `php artisan test --compact` en verde: **702/702** (692 antes, +10 de este cambio). Sin
  comprobación en navegador: este agente tampoco tenía la herramienta.

### Fase 6 — Index (2026-08-20)

- **Huecos de datos, no datos inventados.** La maqueta pinta cuatro KPIs y, por tarjeta, próxima
  salida, pasajeros, ocupación y saldos. Hoy nada de eso sale del backend: `TourSummaryResource`
  emite catálogo (nombre, precio, duración, cupo, categoría, portada, `images_count`,
  `bookings_count`) y `TourPagesController@index` solo manda `categories`. Se implementaron los
  componentes completos y la página los dibuja **condicionados a que el dato llegue**:
  `TourKpiGrid` se monta con `v-if="props.stats"` y el bloque operativo de `TourAdminCard` con
  `v-if="tour.operations"`. Sin backend no se ve nada — que es la verdad — en vez de ceros que se
  leerían como «este tour no tiene pasajeros». Contratos propuestos, ya tipados en
  `resources/js/types/tour.ts`: `TourIndexStats` (prop `stats` de la página) y
  `TourOperationalSummary` (campo `operations` de `TourSummaryResource`).
- **Orden: solo lo que la API sabe hacer.** El handoff ofrece «próxima salida · ocupación ·
  ingresos · alfabético»; `TourController@index` acepta `created_at`, `name`, `base_price` y
  `status`. El select ofrece más recientes / alfabético / precio mayor / precio menor, con el mapeo
  a `sort` + `direction` en `TOUR_SORT_PARAMS`. Los tres órdenes agregados entran cuando entren las
  cifras operativas.
- **Nada de «Falta guía» (D7).** El Index no tenía ni etiqueta ni acción ni select de guía; no hubo
  nada que quitar. Se verificó también que ningún estado nuevo lo reintroduzca.
- **`TourStatusBadge` pasa a los tokens semánticos fijos (D5).** Usaba las variantes de `Badge`
  (`default` = `--primary`), así que «Activo» se pintaba con el color del tenant: una agencia con el
  principal en rojo mostraba «Activo» en rojo. Ahora: activo `--brand-green-*`, pausado
  `--brand-warn-*`, archivado neutro, borrador contorno. Lo comparten Edit y Show.
- **`OccupancyBar` es genérica** (`occupied`, `capacity`, `label?`, `hideValue?`, `size?`) y va
  siempre por `--primary`: llenarse no es una alerta. La reutilizan Editar y Detalle.
- **`TourFilters` absorbió la toolbar** en vez de nacer un componente nuevo: pills de estado
  (`role="tablist"` + `aria-selected`), buscador con `label` oculto, categoría y orden. El estado
  compartido se movió a `TourIndexFilters` en `types/tour.ts`.
- **Enums.** `types/tour.ts` dejó de mantener a mano `TourStatus`/`TourDifficulty` y sus listas:
  ahora reexporta `enums.generated.ts`.
- **Fetch por `useHttp().submit()`** con la ruta de Wayfinder, como `Admin/Dashboard.vue`; antes era
  `fetch()` a pelo. Estados: skeleton de 6 tarjetas al primer carga, spinner en el encabezado al
  refiltrar, `Alert` con «Reintentar» en error y vacío explícito.
- **Verificación.** `types:check`, `lint:check`, `format:check`, `build`, `pint --dirty` y
  `php artisan test --compact` (692/692) en verde. **Sin comprobación en navegador**: este agente no
  tenía la herramienta de navegador disponible; queda para el barrido de la Fase 8 junto con los
  breakpoints 390/430/768/1024/1280/1440.

### Fase 6 — Crear (2026-08-20)

- **El checklist refleja el backend, no la maqueta.** `ChangeTourStatusAction` solo bloquea la
  activación por dos cosas: `images()->count() === 0` y `default_guide_id === null`. Lo demás que
  «bloquea» lo bloquea antes `StoreTourRequest`/`UpdateTourRequest`, que exigen `name`,
  `description`, `base_price`, `currency`, `duration_hours`, `difficulty` y `default_capacity`.
  Entonces `TourPublishChecklist` lista como **obligatorias** nombre + descripción, precio + cupo +
  duración, al menos una imagen y guía por defecto. **Desviación consciente**: `short_description`
  aparece como **recomendada**, no como bloqueante — en el backend es `nullable` y marcarla como
  bloqueante habría sido inventar una regla que nadie aplica. Las paradas de recogida y regreso van
  también en recomendadas, como manda D7.
- **La verdad de completitud vive en `useTourCompletion.ts`**, no en los componentes: el riel de
  progreso y el checklist son dos vistas del mismo estado, y ese estado son las reglas del backend.
  `TourProgressRail` y `TourPublishChecklist` son presentación pura. Lo reutiliza «Editar» pasándole
  `imagesCount: tour.images.length` y sin `pendingCreation`.
- **Galería en «crear»: hueco real, no cargador falso.** `TourImageUploader` sube contra
  `/tours/{tour}/images` y en esta pantalla el tour todavía no existe. El bloque «Paso 5» explica
  que las imágenes se cargan al guardar el borrador (que es lo que ya hacía el flujo: al crear se
  redirige a la edición) en vez de fingir un dropzone que fallaría. `TourForm` estrena el slot
  `#gallery` para que «editar» monte allí el cargador real.
- **`ChipsInput` reemplaza los tres `<textarea>` de «una entrada por línea»** en incluye / no
  incluye / requisitos. Enter o coma agregan, Retroceso con el campo vacío quita la última ficha,
  cada «×» tiene `aria-label`, y el contador respeta los topes reales del Form Request (30 ítems por
  lista, 200 caracteres por ítem). Se descartan duplicados exactos.
- **`DifficultySelector` no se duplicó**: se le agregó `aria-pressed` por opción, `role="group"` y
  foco visible. **Desviación de la tabla de D5**: la tarjeta activa se queda en `--primary` (como ya
  estaba desplegada) en vez de pasar a `--brand-green-50`. Es una selección, no un estado, y con el
  borde en el color del tenant un fondo verde fijo se vería roto en una agencia con otro principal.
  `--brand-green-50` sí se usa en el hover de las fichas del `ChipsInput` y del riel.
- **Nada de «Falta guía» (D7).** El select de guía por defecto perdió la opción «Sin guía por
  defecto»: ahora el placeholder es un `<option disabled>` («Elige un guía»), igual que
  `GuideSelect`. `default_guide_id` sigue siendo `nullable` al guardar el borrador —el backend lo
  permite— y su ausencia se comunica por el checklist, no por un estado ámbar.
- **`GuideSelect` de la Fase 5 no aplica acá y no se forzó.** Responde «quién está libre entre el
  día X y el Y»; el guía por defecto del tour no tiene fechas, así que sin rango no hay agenda que
  consultar. La lista salió a `useTenantGuides.ts` (el `fetch` al equipo que antes estaba dentro de
  `TourForm`), reutilizable por «editar».
- **Savebar sticky** en `molecules/StickySaveBar.vue`, con slots `note` y `actions`: «crear» pone el
  contador de condiciones pendientes; «editar» pondrá su contador de cambios sin duplicar la barra.
- **Riel a `320px` y colapso en `1180px`** con la variante arbitraria `min-[1180px]:` (el proyecto no
  define breakpoints propios y `xl` es 1280). El riel es `sticky top-20` solo por encima de ese
  ancho; debajo cae al final, en una columna.
- **Datos que la pantalla no tiene y el backend hoy no entrega:** el «Borrador sin guardar · los
  cambios se conservan 24 h» de la maqueta (no hay autosave ni borrador local: se reemplazó por el
  contador de condiciones pendientes) y el botón «Vista previa» (no hay ruta pública para un tour que
  todavía no existe). Ninguno se dibujó.
- **Verificación.** `pint --dirty`, `types:check`, `lint:check`, `format:check`, `build` y
  `php artisan test --compact` (692/692) en verde. **Sin comprobación en navegador**: este agente
  tampoco tenía la herramienta de navegador; los breakpoints 390/430/768/1024/1280/1440 quedan para
  el barrido de la Fase 8.

### Fase 6 — Editar (2026-08-20)

- **`TourForm` estrena `sections`, y por eso la pestaña «Ruta y mapa» existe.** El bloque «Paso 4»
  —punto de encuentro, itinerario y paradas— tenía que salir de «Contenido» sin que el formulario
  supiera de pestañas. La solución es un prop `sections?: TourFormStepId[]` que por defecto trae los
  cinco bloques: **«Crear» no cambia** (no pasa el prop, renderiza todo, verificado en `build` y en
  el diff). «Editar» monta **dos instancias** sobre el mismo `modelValue`: una con
  `general/pricing/detail/gallery` y otra con `route`. Los `id` de ancla no se duplican porque los
  conjuntos son disjuntos, y por eso las dos pestañas pueden ir con `v-show` en vez de `v-if`: al
  cambiar de pestaña no se pierde ni el foco ni el mapa.
- **`fit()` nuevo en `useTourRouteMap`, expuesto por `TourRouteMapSection`.** Un mapa de Leaflet
  montado dentro de una pestaña oculta mide 0×0 y se queda gris. Al activarse «Ruta y mapa» se llama
  `fit()` (`invalidateSize()` + reencuadre de la vista activa). Es aditivo: el detalle público y la
  zona del guía no cambian de comportamiento. **La pantalla «Detalle» puede usar lo mismo.**
- **El mapa de la pestaña dibuja los BORRADORES, no lo guardado.** `routeStopsFromDrafts()`
  (`lib/tour-route.ts`) arma las paradas desde el formulario y calcula el `code` del pin con la misma
  regla que `SyncTourStopsAction::codeFor()` (`A`, `1..n`, `B`). Las paradas sin coordenadas válidas
  se omiten: no se pueden dibujar. Sin paradas no hay tarjeta de mapa.
- **`TourDeparturesTable` reemplaza a `TourDatesPanel`, que se elimina.** Solo lo usaba esta página.
  La tabla nueva trae la ocupación (`OccupancyBar`, por `--primary`), los tres ámbitos
  (próximas/pasadas/canceladas) y las acciones; los diálogos —alta/edición de salida y cancelación—
  los hospeda la página, y los datos salen del composable `useTourDepartures`.
- **El `GuideSelect` de la Fase 5 se monta SOLO en la fila que se toca.** Cada instancia consulta la
  agenda para SU rango (`GET /admin/guides/availability?from&to`): montarlo en las veinte filas
  serían veinte consultas al abrir la pestaña. La celda muestra el nombre del guía como botón y al
  pulsarlo se convierte en el select con disponibilidad y motivos. **Nada de «Falta guía» (D7)**: no
  hay etiqueta ámbar, ni acción «Asignar guía», ni opción «Sin asignar» — se revisaron
  `TourDatesPanel` (eliminado), `TourDateFormDialog` y `UpcomingDatesTable` y no quedaba ninguna.
- **`PATCH /admin/tour-dates/{tourDate}/guide` responde `{id, guide_id}`, no la salida.** Así que
  tras asignar hay que recargar la lista para ver el nombre nuevo y el estado recalculado. Es un
  viaje de más por asignación; si el endpoint devolviera `TourDateResource` se resolvería en el sitio.
- **Los contadores de las pestañas son datos reales o no son.** «Salidas (n)» sale de las salidas no
  canceladas que ya se piden para la tabla. «Pasajeros (n)» sale de `meta.summary.total_passengers`
  de la planilla, que se pide una vez al montar con `per_page=10` (`useTourManifestSummary`): lo que
  interesa es el `meta`, no las filas. Mientras el dato no llegue **no se dibuja el contador** en vez
  de un `0` que se leería como «no hay nadie».
- **`TourImpactCard` no inventa ninguna de sus tres cifras**: pasajeros con reserva activa y saldos
  pendientes salen del mismo `meta.summary`; las salidas abiertas, del `status === 'open'` de la
  lista. Sin `bookings.view` no hay resumen y la tarjeta lo dice; el bloque de pasajeros
  simplemente no se pinta.
- **La pestaña «Pasajeros» reutiliza `PassengerManifest` tal cual (Fase 4), no una versión reducida.**
  El handoff pinta una mini-lista con «Abrir lista completa →» hacia el detalle; con el organism ya
  hecho, media planilla habría sido un segundo componente que mantener para mostrar menos. Va con
  `v-if` —no `v-show`— porque dispara su fetch al montarse, y solo aparece con `bookings.view` (regla
  de oro del menú, F018). **Limitación:** `PassengerManifest` no recibe filtro inicial, así que el
  botón «Pasajeros» de una fila abre la pestaña y la salida se elige en el selector de la propia
  planilla.
- **Contador de cambios en la savebar existente.** `StickySaveBar` con sus slots `note`/`actions`:
  «N cambios sin guardar» comparando el `payload` actual contra `initialValues` campo por campo
  (`form.isDirty` solo responde sí/no), más «Descartar». Sin cambios cae al contador de condiciones
  de publicación, que es lo que hay que mirar cuando no hay nada que guardar.
- **El 422 de `duration_hours` sube a un `Alert` de la página.** El mensaje del servidor nombra las
  salidas que quedarían en solape y estaba condenado a aparecer bajo un input de otra pestaña: ahora
  se muestra arriba y la vista salta a «Contenido». `ends_at` no es input en ningún camino.
- **Estado del tour: los botones se mudaron al head** (como el handoff) y la tarjeta «Estado» del
  riel desaparece; el riel queda con progreso, checklist e impacto. Se agregó el mensaje de
  `TOUR_NEEDS_GUIDE_TO_ACTIVATE`, que la pantalla no traducía y caía al texto genérico.
- **`TourTabs` (molecule) es nuevo y lo hereda «Detalle».** Subrayado en `--primary`, `role="tablist"`,
  `aria-selected` y contador opcional. Evita el tercer copiar-pegar de la misma barra.
- **Datos que la pantalla necesita y el backend hoy no entrega:** el autor de la última edición («por
  Eduardo U.» en la maqueta; solo hay `updated_at`, así que se muestra «Última edición hace 2 h» sin
  nombre), «N reservas dependen de este tour» en el subtítulo del head (existe agregado en la
  tarjeta de impacto, no en el head), «Duplicar tour» (no hay endpoint) y el aviso de la maqueta de
  que cambiar una parada de recogida notifica a los pasajeros (`NotifyPickupChangeAction` es de la
  Fase 7: hoy no existe, así que no se promete). Ninguno se dibujó.
- **El riel no tiene scroll-spy en «Editar»** (sí en «Crear»): con los bloques repartidos en dos
  pestañas, un `IntersectionObserver` sobre secciones ocultas marca pasos que nadie está viendo. El
  paso activo se marca al pulsarlo.
- **Verificación.** `pint --dirty`, `types:check`, `lint:check`, `format:check`, `build` y
  `php artisan test --compact` (692/692) en verde. **Sin comprobación en navegador**: este agente
  tampoco tenía la herramienta; los breakpoints 390/430/768/1024/1280/1440 y los 0 errores de consola
  quedan para el barrido de la Fase 8.

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


### Fase 4 — Planilla + zona del guía (2026-08-20)

#### Dos cosas rotas que la fase se encontró y arregló

- **`develop`+Fase 3 dejó `Guide/Schedule.vue` sin compilar.** La página importaba
  `travelers` de `GuideController`, el endpoint que la Fase 3 eliminó, así que
  `npm run types:check` fallaba **antes** de tocar nada en esta fase (1 error, el único).
  El diálogo de viajeros que vivía ahí se retiró entero: servía una versión recortada
  (nombre, email, teléfono) y la planilla completa es ahora una pantalla propia. La agenda
  enlaza a ella y al detalle del tour. **Radar:** una fase de backend que elimina un endpoint
  tiene que correr `types:check`, no solo `php artisan test`.
- **`Booking/Show.vue` estaba borrando datos de salud en cada guardado.** La página Inertia
  **no usa `BookingResource`**: `BookingPagesController@show` armaba el array a mano y su
  bloque `travelers` se quedó en los ocho campos de antes de la Fase 2 — sin emergencia, sin
  EPS, sin observaciones. El formulario los cargaba vacíos y, como
  `UpdatePassengerAction` reemplaza al viajero entero, el siguiente «Guardar» los escribía
  `null`. Es exactamente lo que la nota de la Fase 2 quería evitar («si el formulario no puede
  releer esos dos campos, el siguiente guardado los borra»), pero la corrección se hizo en el
  Resource de la API y la pantalla no pasa por ahí. Ahora `travelers` se serializa con
  `BookingTravelerResource`, el mismo de la API. **Radar:** hay más superficies armadas a mano
  que no pasan por un Resource; vale la pena barrerlas antes de la Fase 8.

#### Decisiones tomadas sobre la marcha

- **Los 5 tokens semánticos de D5 se adelantaron a esta fase.** No existían en
  `resources/css/app.css` y el chip de pago, el aviso ámbar y la observación resaltada los
  necesitaban ya. Se crearon con los **nombres exactos** que fija D5 y con sus valores, más el
  mapeo `--color-brand-*` que Tailwind v4 necesita para las utilidades. La Fase 6 no tiene que
  rehacer nada: su primer ítem queda marcado. Se agregó además un bloque de override en
  `.dark` para las tres superficies `*-50`: una tarjeta crema sobre fondo ink no se lee. El
  **hue** de los semánticos no cambia en oscuro, solo la superficie.
- **`php artisan wayfinder:generate` a secas rompe el type-check.** El plugin de Vite corre
  `wayfinder:generate --with-form` (`vite.config.ts`, `formVariants: true`); sin ese flag se
  regeneran los actions **sin** los helpers `.form`, y 16 archivos que los usan (auth,
  settings, `DeleteUser`, `TwoFactor*`) dejan de compilar. La nota de la Fase 3 decía solo
  «con PHP 8.4 en el PATH». **El comando correcto es
  `php artisan wayfinder:generate --with-form`**, y `resources/js/{actions,routes}` está en
  `.gitignore`, así que el estropicio no se ve en el diff.
- **La hoja de impresión imprime todo el resultado filtrado con un barrido en segundo plano.**
  `window.print()` no se puede esperar desde `beforeprint`, así que no sirve «cargar todo al
  pulsar Imprimir»: el atajo del navegador (Cmd+P) se saltaría la carga. En cuanto la planilla
  pasa de una página, el composable trae el resto con `per_page=100` y la hoja está siempre
  lista. Tope de 20 páginas (2.000 filas) como cortafuegos. Con una sola página no hay
  petición extra.
- **El `<style>` del organism va sin `scoped`.** Es la única excepción a «Tailwind
  utility-first», y la pide `plan.md §4` explícitamente. Apaga con `visibility` en vez de
  `display` para no tener que enumerar barra lateral, topbar y demás chrome: si mañana aparece
  otro, sigue funcionando.
- **La fila de marcador de posición no se edita, se completa.** Su acción abre el alta sobre
  esa reserva (`POST /admin/bookings/{n}/passengers`) en vez de un editar sobre una persona
  que todavía no existe. El nombre del titular queda precargado.
- **El formulario del panel no manda `eps`, `eps_other` ni `medical_notes` sin el permiso
  médico.** El backend los descartaría igual (máscara de escritura, D7), pero además el
  fieldset entero no se dibuja: prometer un campo que no se va a guardar es peor que no
  ofrecerlo.
- **La pestaña «Pasajeros» es `v-if`, no `v-show`.** La planilla dispara su fetch al montarse;
  con `v-show` cada visita al detalle del tour pediría la planilla aunque nadie abra la
  pestaña. `Admin/Tour/Show.vue` se tocó lo mínimo (barra de pestañas + envoltorio): el
  rediseño completo es de la Fase 6.
- **Las rutas de la zona del guía repiten la guarda de pertenencia en el controlador de
  páginas.** `GET /guide/tours/{tour}` va **sin `can:`** a propósito: el alcance es tener al
  menos una salida asignada, no un permiso de catálogo (D1). Sin repetir la guarda, la ruta
  respondería 200 con una pantalla que la API luego rechaza — al revés de la regla de oro del
  menú de F018. Cubierto por `tests/Feature/Guide/GuidePagesAccessTest.php` (4 casos).
- **Los nombres de campo salen de `{{ }}`** en `PassengerFormDialog`: `TranslationCatalogTest`
  marca cualquier literal dentro de una interpolación como copy sin traducir, y tiene razón.
  Se resuelven en computeds del script.
- **68 cadenas nuevas en `lang/en.json` y 3 huérfanas retiradas** (las del diálogo de viajeros
  que desapareció de `Guide/Schedule.vue`).

#### Verificación

- `npm run types:check` → **0 errores** (la rama venía con **1**, el de `Guide/Schedule.vue`).
- `npm run lint:check` → **0 errores, 0 warnings**.
- `npm run format:check` → **todos los archivos con el estilo de Prettier**.
- `vendor/bin/pint --dirty --test` → **passed**.
- `php artisan test --compact` → **667/667, 2381 assertions** (venía en 661/661: **+6**,
  4 de `GuidePagesAccessTest` y 2 de `TravelerEditWindowTest`).
- `npm run build` → **OK**, 5,57 s. `PassengerManifest` sale como chunk propio de 42,8 kB
  (11,7 kB gzip).
- **Sin navegador ni Playwright en este entorno.** En su lugar se montó un arnés SSR temporal
  (`@vue/server-renderer` + un stub de `usePage`, compilado con el Vite del proyecto para no
  duplicar la instancia de Vue) y se comprobaron **34 aserciones de comportamiento** sobre HTML
  real, no sobre el código. El arnés se borró después; lo que verificó:
  - **Máscara médica (11):** con permiso, la observación sale en terracota + `font-semibold` y
    la EPS «Otra» muestra el texto libre; sin permiso **no aparece** ni la observación ni la
    EPS, la fila tiene **una `<td>` menos**, no hay guion ni candado, y el segmento «Con
    observaciones» **no está** en los filtros. La fila de marcador de posición dice «Datos
    pendientes» y conserva su saldo.
  - **Composable (12):** con 137 resultados, la página visible trae 50 y `printRows` trae las
    **137**; sin ids repetidos; el barrido pide `per_page=100`; cambiar de filtro vuelve a la
    página 1; con 12 resultados no hay barrido extra; todas las URL salen de Wayfinder; la zona
    del guía **nunca** manda `tour_date_id`; el `exportUrl` lleva el filtro y no lleva
    paginación; un `500` deja estado de error sin filas fantasma.
  - **D10 (11):** con la ventana abierta hay formulario; con la ventana cerrada **no hay
    `<form>`, ni un solo `<input>`, `<textarea>` o `<select>`, ni botón de guardar**, sí el
    aviso, sí el deadline formateado, sí el camino con la agencia, y sí lo ya guardado
    (emergencia, EPS «Otra», observaciones). Sin las props nuevas el componente sigue editable
    (compatibilidad).
- **Pendiente para la Fase 8 (verificación visual):** el diálogo de impresión real
  (`@media print` solo se puede juzgar en el navegador), el mapa de `Guide/TourShow` con
  Leaflet montado, el foco real del campo «¿Cuál EPS?», el scroll de la cabecera sticky, el
  responsive (390/430/768/1024/1280/1440) y la comparación **admin vs ventas vs guía** con dos
  tenants de colores distintos. Es el mismo pendiente que dejó anotado la Fase 2.

#### Lo que se podría mejorar

- **`PassengerFormDialog` y `BookingTravelersSection` repiten el bloque de EPS** (5 radios +
  campo libre + la regla `eps ≠ other ⇒ eps_other = null`) y los dos mapas de etiquetas de
  enum. Es candidato claro a una molécula `EpsSelector.vue` y a mover
  `DOCUMENT_TYPE_LABELS`/`EPS_LABELS` a `lib/`. No se hizo ahora para no tocar el componente
  del viajero más de lo que D10 exigía.
- ~~**Todavía no hay generación de enums PHP → TS.**~~ **Resuelto el 2026-08-20**: los espejos
  los genera `php artisan enums:typescript` en `resources/js/types/enums.generated.ts`, y la
  suite falla si el archivo queda desactualizado. La deuda que arrastraba desde la Fase 2 se
  cierra aquí.
- **`Admin/Tour/Show.vue` quedó con pestañas «a mano»**, no con un componente de pestañas. La
  Fase 6 lo rehace con `Resumen · Pasajeros · Ruta` y ahí conviene extraer el `TourTabs`.
- **La paginación de la planilla es Anterior/Siguiente**, sin números de página. Con 50 por
  página y una salida por planilla alcanza; si la Fase 6 pide el pie de paginación del handoff,
  hay que ampliarlo.

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
- [x] ~~**Pendiente de frontend (Fase 4).**~~ **HECHO (2026-08-20, Fase 4).** El formulario consume
  los dos campos y se pinta en solo lectura con el aviso. Detalle en las notas de la Fase 4.
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
- `2026-08-20` — **Fase 4 completa** (planilla, zona del guía y D10 en el formulario del viajero).
  16 ítems marcados, notas de implementación registradas. Suite en **667/667** (era 661, +6:
  4 de `GuidePagesAccessTest` y 2 de `TravelerEditWindowTest`); `types:check`, `lint:check`,
  `format:check`, `pint --dirty --test` y `npm run build` en verde. Cambios que salen del
  checklist y conviene tener en el radar:
  1. **Los 5 tokens semánticos de D5 se adelantaron** desde la Fase 6, con los nombres exactos.
     El primer ítem de la Fase 6 queda marcado; no hay nada que rehacer allí.
  2. **`Guide/Schedule.vue` no compilaba en esta rama** desde la Fase 3, que eliminó el endpoint
     que la página importaba. La Fase 4 lo arregló retirando el diálogo de viajeros entero.
  3. **`Booking/Show.vue` borraba emergencia, EPS y observaciones en cada guardado**: la página
     Inertia arma su prop `booking` a mano y no pasa por `BookingResource`, así que la corrección
     de la Fase 2 no la alcanzó. Ahora usa `BookingTravelerResource`. Hay dos tests nuevos.
  4. **El comando de Wayfinder de este proyecto es `wayfinder:generate --with-form`.** Sin el
     flag se pierden los helpers `.form` y 16 archivos dejan de compilar; como
     `resources/js/{actions,routes}` está en `.gitignore`, no se ve en el diff.
- `2026-08-20` — **La verificación visual de la Fase 4 queda pendiente para la Fase 8**, igual
  que la de la Fase 2: no hay navegador ni Playwright en el entorno. En su lugar se corrieron 34
  aserciones de comportamiento sobre HTML renderizado en servidor (máscara médica, composable de
  la planilla y formulario de D10). Detalle en las notas de la fase.
- `2026-08-20` — **Cerradas las dos preguntas abiertas de la Fase 5.** Suite en **692/692**
  (era 688, +4); Pint, `types:check`, `lint:check`, `format:check` y `npm run build` en verde.

  1. **`scopeOccupying()` ahora incluye `full`.** Una salida agotada ocupa al guía igual que una
     abierta: agotada significa vendida entera, no suspendida. Solo `cancelled` libera sus días.
     Dos tests nuevos en `GuideAvailabilityTest`: la regla rechaza el solape contra una salida
     `full`, y el endpoint de disponibilidad la reporta como bloque ocupado.
  2. **El espejo de enums PHP → TS se genera.** `php artisan enums:typescript` vierte todos los
     enums con respaldo de string de `app/Enums` a `resources/js/types/enums.generated.ts`
     (`<ENUM>_VALUES as const` + la unión derivada). `--check` no escribe: falla si el archivo
     está desactualizado, y `tests/Feature/Enums/TypeScriptEnumsAreInSyncTest.php` lo corre
     dentro de la suite. El archivo generado está en `.prettierignore` y en los `ignores` de
     ESLint, como los de Wayfinder.
     - Se vierten **todos** los enums, no una lista marcada a mano: una lista es otra cosa que se
       olvida de actualizar, y un tipo que la UI no usa no llega al bundle.
     - Consumidores migrados: `types/booking.ts` (`DOCUMENT_TYPES`, `EPS_OPTIONS` pasan a ser
       alias del generado, conservando el nombre que la UI ya importa), `types/logistics.ts`
       (`TourDateStatus`, `TourDateDisplayStatus`), `types/tour-detail.ts` y
       `config/roles.ts` (`BASE_ROLE_LABELS` pasa de `Record<string, string>` a
       `Record<UserRole, string>`: agregar un rol en PHP ahora rompe la compilación hasta que se
       le escriba etiqueta).
     - **Un espejo ya se había desincronizado sin que nadie lo notara**: `guide-availability.ts`
       declaraba `status: 'open' | 'closed'` y sobrevivió al cambio del punto 1. Es exactamente
       el fallo que el generador previene, y de paso quedó corregido.
