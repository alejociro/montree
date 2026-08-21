# tours-admin-passengers — Lista de pasajeros y rediseño del CRUD de tours

> Spec funcional. Lo que el feature hace desde la óptica del usuario.
> Cambios a este archivo requieren actualizar [`tasks.md`](./tasks.md) y registrar en `## Changelog`.

---

## Descripción

Tres cosas que llegan juntas porque comparten pantalla y modelo de acceso:

1. **Requerimiento nuevo — lista de pasajeros.** El guía y el administrador de la agencia
   necesitan, por salida, la planilla de las personas que reservaron: nombre completo, tipo y
   número de documento, contacto (email y teléfono), contacto de emergencia, EPS (con campo libre
   si es «Otra») y observaciones médicas (alergias, condición o discapacidad física).
   Estado real de cada dato: `booking_travelers` ya tiene `email`, `emergency_contact_name`,
   `emergency_contact_phone`, `medical_notes` y `dietary_restrictions` desde
   `2026_05_17_170010_create_booking_travelers_table.php`, y `SyncBookingTravelersRequest:40` +
   `SyncBookingTravelersAction:40` los validan y los guardan — **ninguna pantalla los pide**
   (`BookingTravelersSection.vue:319-374` solo captura nombre, teléfono, fecha de nacimiento,
   tipo y número de documento). Lo único que falta crear en el esquema es la **EPS**
   (`eps` + `eps_other`) y el **parentesco** del contacto de emergencia. El contacto de
   emergencia hoy se captura **por reserva**, no por persona: `Booking/Create.vue:152` lo manda y
   `CreateBookingAction:92` lo escribe dentro del JSON `bookings.contact_snapshot`.
   El único consumidor actual del lado del guía es
   `GET /api/v1/guide/tour-dates/{tourDate}/travelers` (`routes/api.php:91`), que devuelve
   `full_name`, `email` y `phone` sin Resource ni pantalla que lo pinte.

2. **Zona de lectura del guía.** El guía consulta el **detalle completo** de los tours donde está
   asignado —contenido, ruta y mapa, itinerario, logística, sus salidas y la planilla—, y no
   modifica nada.

3. **Rediseño del CRUD de tours del panel.** Las cuatro vistas (`Index`, `Crear`, `Editar`,
   `Detalle`) se rehacen contra el handoff `design_handoff_tours_admin/tours-admin-montree.html`,
   que además es donde vive la planilla de pasajeros (pestaña «Pasajeros» del detalle).

Dos reglas ordenan el feature:

- **La captura del dato va antes que la pantalla que lo muestra.** Cuatro de las siete columnas ya
  existen y ninguna pantalla las llena: si se construye la planilla primero, nace vacía.
- **La EPS y las observaciones son dato sensible.** Se ven en el panel y en la zona del guía
  asignado, y solo con el permiso `bookings.passengers.medical.view`. Nunca en la ficha pública ni
  en ninguna respuesta del catálogo, y **no** para el rol `sales`.

**Base de las decisiones:** el requerimiento, las reglas de negocio confirmadas por producto y el
código (esquema, Actions, Form Requests, seeders). Lo que hay cargado hoy en cualquier base es
dato de prueba y no se usa como evidencia: ni para dimensionar, ni para justificar una decisión,
ni para preguntar.

Fuente de diseño: `design_handoff_tours_admin/` (paquete local, ignorado por git —
`.gitignore:39`). Copia citable del README en el kit:
`proyectos/montree/fuentes/handoff-tours-admin-2026-08-20.md`. Plan de producto con las siete
decisiones: `proyectos/montree/tablero/paginas/tourspax.md`.

## User stories

- Como **guía**, quiero ver la lista de personas que reservaron **mis** salidas, con documento,
  contacto, contacto de emergencia, EPS y observaciones, para poder responder en campo ante una
  emergencia y verificar quién sube al vehículo.
- Como **guía**, quiero abrir el detalle completo de los tours donde estoy asignado —contenido,
  ruta, itinerario, logística y mis salidas— en modo lectura, para prepararme sin depender de que
  la agencia me lo cuente.
- Como **administrador de la agencia**, quiero la misma lista para cualquier salida de cualquier
  tour de mi agencia, filtrable por salida y buscable por nombre o documento.
- Como **vendedor (`sales`)**, quiero ver la planilla para atender a un cliente, **sin** acceso a
  su EPS ni a sus observaciones médicas: vendo, no atiendo emergencias.
- Como **viajero**, quiero registrar mis datos de salud y mi contacto de emergencia al completar
  los datos de mi reserva, para no tener que dictarlos el día del tour.
- Como **guía**, quiero que la planilla que imprimo el día anterior sea la que se sube al vehículo:
  que el viajero no pueda cambiar un contacto de emergencia después de que la tengo en papel.
- Como **admin/operator**, quiero corregir o completar los datos de un pasajero desde el panel
  cuando el viajero no los llenó.
- Como **guía o admin**, quiero imprimir la planilla y exportarla a CSV para llevarla en papel.
- Como **admin/operator**, quiero que toda salida tenga guía y que el sistema no me deje asignar a
  alguien que ya está ocupado esos días.
- Como **admin/operator**, quiero un listado de tours que muestre el estado operativo (próxima
  salida, ocupación, pasajeros con saldo) y no solo la foto y el precio.
- Como **admin/operator**, quiero crear un tour sabiendo en todo momento qué me falta para poder
  publicarlo.
- Como **admin/operator**, quiero editar el tour por pestañas (contenido, ruta, salidas,
  pasajeros) y ver el impacto de mis cambios sobre las reservas vivas.

## Acceptance criteria

### Datos del pasajero

- **Given** un viajero completando los datos de su reserva, **when** guarda, **then** puede
  registrar además de los campos de hoy: email, contacto de emergencia (nombre, parentesco y
  teléfono), EPS y observaciones.
- **Given** un pasajero con `eps = other`, **when** se guarda sin `eps_other`, **then** error de
  validación `422` sobre `eps_other`.
- **Given** un pasajero con `eps ≠ other`, **when** se envía `eps_other`, **then** se ignora y se
  persiste `null`.
- **Given** el formulario de viajeros, **when** se marca la EPS «Otra», **then** aparece el campo
  «¿Cuál EPS?» y recibe el foco.
- **Given** cualquier salida, **when** se consulta la ficha pública del tour, **then** ninguna
  respuesta pública incluye `eps`, `eps_other`, `medical_notes`, documento ni contacto de
  emergencia.

### Ventana de edición del viajero (Decisión 8 · D10)

- **Given** un titular de reserva y una salida que empieza en más de 24 h, **when** edita los datos
  de sus acompañantes, **then** se guardan.
- **Given** ese mismo titular y una salida que empieza en menos de 24 h, **when** intenta guardar,
  **then** `409` con `BOOKING_TRAVELER_EDIT_WINDOW_CLOSED` y el mensaje lo remite a la agencia.
- **Given** una reserva cuya ventana ya cerró, **when** el **administrador de la agencia** edita al
  pasajero desde el panel o registra un pago manual, **then** funciona igual que siempre: la
  ventana **no** aplica al panel. El cambio de última hora se hace por la agencia.
- **Given** una reserva sin salida resuelta o sin `starts_at`, **then** el deadline es `null` y
  **no** bloquea.
- **Given** una salida ya iniciada, **then** la ventana está cerrada.
- **Given** el formulario de datos de la reserva, **when** la ventana ya cerró, **then** se muestra
  en **solo lectura** con el aviso de hasta cuándo se podía editar — no se deja escribir para
  después devolver un `409`.
- El plazo es configurable (`config/montree.php` → `passengers.traveler_edit_cutoff_hours`); 24 h
  es el default, no una constante en la lógica.

### Dato de salud y el rol `sales` (Decisión 7)

- **Given** un usuario **sin** `bookings.passengers.medical.view`, **when** pide la planilla en
  cualquiera de las dos zonas, **then** la respuesta **no serializa** `eps`, `eps_other` ni
  `medical_notes`, y `meta.can_view_medical` es `false`.
- **Given** ese mismo usuario, **when** filtra por el segmento «Con observaciones», **then** `403`:
  un filtro que dice *quién* tiene una condición médica la delata sin mostrarla.
- **Given** ese mismo usuario, **when** exporta el CSV, **then** las columnas `EPS` y
  `Observaciones` **no se emiten** — no se emiten vacías.
- **Given** ese mismo usuario con `bookings.update`, **when** edita un pasajero enviando `eps`,
  `eps_other` o `medical_notes`, **then** esos campos se **descartan** y los valores guardados no
  cambian.
- **Given** ese mismo usuario, **when** ve el pie de la tabla, **then** no aparece el conteo de
  pasajeros con observaciones; el de saldos sí.
- **Given** un usuario con el permiso (`admin` o `guide`), **then** ve los tres campos, el
  segmento, el conteo y las columnas del CSV.
- El **contacto de emergencia sí lo ve `sales`**: es un teléfono de logística, no un dato clínico.

### Planilla de pasajeros — guía

- **Given** un guía asignado a una salida, **when** entra a esa salida desde su agenda, **then**
  ve la planilla completa de los pasajeros de las reservas `confirmed` y `completed`, datos de
  salud incluidos.
- **Given** un guía **no** asignado a una salida, **when** pide su planilla, **then** `403`.
- **Given** un guía, **when** intenta entrar a `/admin/*`, **then** sigue recibiendo `403`
  (F018 no se relaja: el guía tiene su propia zona con los mismos componentes en modo lectura).
- **Given** un guía, **when** abre la planilla, **then** puede buscar, filtrar, exportar e
  imprimir, pero **no** puede crear ni editar pasajeros ni registrar pagos.

### Detalle de tour del guía (Decisión 1)

- **Given** un guía con al menos una salida asignada de un tour, **when** abre
  `/guide/tours/{tour}`, **then** ve contenido, ruta y mapa, itinerario, logística, **sus**
  salidas y la planilla, sin ninguna acción de escritura.
- **Given** un guía sin ninguna salida asignada en un tour de su misma agencia, **when** abre ese
  detalle, **then** `403`. El alcance se filtra por **pertenencia**, no por permiso.
- **Given** el detalle del guía, **then** las salidas de otros guías del mismo tour **no**
  aparecen.

### Planilla de pasajeros — agencia

- **Given** un usuario con `bookings.view`, **when** abre el detalle de un tour y la pestaña
  «Pasajeros», **then** ve la planilla de todas las salidas de ese tour, con selector de salida
  («Todas las salidas» por defecto).
- **Given** la planilla, **when** se combinan el segmento (`Todos` / `Con saldo` / `Al día` /
  `Con observaciones`) y la búsqueda por nombre o número de documento, **then** la tabla y el pie
  («N pasajeros · M con saldo pendiente · Total por cobrar $X») se recalculan sobre el resultado
  combinado.
- **Given** ningún pasajero coincide, **then** se muestra el estado vacío «Ningún pasajero coincide
  con el filtro.»
- **Given** una fila de la tabla, **when** se hace clic, **then** se abre el drawer con la ficha:
  identificación, contacto, emergencia, EPS, estado de pago y observaciones.
- **Given** un usuario con `bookings.update`, **when** edita un pasajero desde el modal, **then**
  los cambios se persisten sobre `booking_travelers` y la tabla se refresca.
- **Given** un pasajero con observación médica registrada y un actor **con** el permiso médico,
  **when** se pinta la fila, **then** la observación se resalta (terracota + semibold) para que no
  pase inadvertida. Sin el permiso, la columna **no se dibuja**: nada de guiones ni candados, que
  solo señalan lo que hay detrás.

### Estado de pago del pasajero

- **Given** una reserva con `total_amount` y `paid_amount`, **when** se lista a sus pasajeros,
  **then** cada uno muestra su parte proporcional (`total_amount / travelers_count`) y el estado
  se deriva del saldo de **la reserva**: `saldo > 0` ⇒ «Saldo pendiente», `saldo = 0` ⇒ «Pagado».
- **Given** un usuario con `bookings.update`, **when** registra un pago manual desde el drawer,
  **then** se crea un `Payment` con `gateway = manual` sobre la reserva del pasajero y el saldo se
  recalcula (ver D3 en [`plan.md`](./plan.md)).

### Guía obligatorio y disponibilidad (Decisiones 2 y 3)

- **Given** la creación o la edición de una salida, **when** no se envía `guide_id`, **then**
  `422`. No existe «Sin asignar»: `tour_dates.guide_id` es `NOT NULL`.
- **Given** un tour, **when** se publica sin `default_guide_id`, **then** se rechaza; ese guía por
  defecto es la propuesta que hereda cada salida nueva y se puede cambiar fecha por fecha.
- **Given** un guía con una salida `open` o `closed` que ocupa los días `[12 sep … 14 sep]`,
  **when** se le intenta asignar otra salida que solape **cualquiera** de esos días calendario,
  **then** `422` con el motivo y el tour que lo ocupa.
- **Given** un tour de varios días, **then** la ocupación se calcula por **días calendario**
  (`[date(starts_at) … date(ends_at)]`): un tour de 5 días bloquea los 5, aunque el último termine
  a las 9 de la mañana.
- **Given** una salida `cancelled`, **then** sus días quedan libres.
- **Given** la edición de una salida, **when** se guarda sin cambiar el guía, **then** la propia
  salida se excluye del cálculo y no genera un falso positivo.
- **Given** cualquiera de los **tres** caminos que asignan guía —crear salida, editar salida y
  `PATCH /api/v1/admin/tour-dates/{tourDate}/guide` (`routes/api.php:119`)—, **then** la regla se
  aplica igual. Hoy `AssignGuideAction` hace `update(['guide_id' => …])` sin comprobar nada.
- **Given** el select de guía, **then** los ocupados aparecen deshabilitados y con el motivo
  («Ocupado 12–14 sep · Valle de Cocora»): la UI no ofrece lo que va a rechazar.
- **Given** una salida, **when** se crea o edita, **then** `ends_at` se **deriva** en el servidor
  de `starts_at + tours.duration_hours` y **deja de aceptarse como entrada del cliente**. Hoy los
  dos Form Requests lo aceptan sin contrastarlo con la duración (`StoreTourDateRequest:29`) y
  `TourDateFactory:30` le suma 4 horas fijas a cualquier tour: un `ends_at` que miente es peor que
  uno vacío, porque la regla de disponibilidad lo creería.
- **Given** un tour con salidas, **when** se cambia `duration_hours`, **then** se listan antes de
  guardar las salidas que quedarían en solape.

### CRUD de tours rediseñado

- **Given** el listado de tours, **then** se ven 4 KPIs (tours activos, salidas de los próximos 30
  días, ocupación media y pasajeros con saldo), pills de estado, buscador, filtro de categoría y
  orden, y una card por tour con próxima salida, pasajeros, barra de ocupación y precio.
- **Given** la pantalla de creación, **then** el riel derecho muestra el progreso por bloque y el
  checklist de publicación, y la savebar informa el estado del borrador.
- **Given** un tour en `draft` al que le falta algún requisito de publicación, **when** se intenta
  activar, **then** se rechaza indicando exactamente qué falta.
- **Given** la pantalla de edición, **then** el contenido se organiza en pestañas
  `Contenido · Ruta y mapa · Salidas (n) · Pasajeros (n)` y el riel derecho muestra el impacto
  (reservas activas, salidas abiertas, pasajeros con saldo).
- **Given** la pestaña «Salidas», **then** cada fila tiene su select de guía **con la
  disponibilidad ya aplicada**. No existe la etiqueta «Falta guía».
- **Given** el detalle del tour, **then** el hero, los 4 KPIs, la ocupación de las próximas salidas,
  el itinerario como línea de tiempo y el mapa de ruta de solo lectura reemplazan la vista actual.
- **Given** cualquier pestaña con mapa, **when** se vuelve visible, **then** el mapa se reencuadra
  (`invalidateSize()` + `fitBounds()`); un mapa creado oculto calcula un viewport 0×0.

### Marca y color (Decisión 4)

- **Given** dos agencias con colores distintos, **when** cada una abre el panel, **then** los
  botones primarios, el subrayado de la pestaña activa, las barras de ocupación, el pin de
  recogida y el ítem activo de la barra lateral siguen **su** color: `useTenantBranding.ts` ya
  sobrescribe `--primary`, `--secondary`, sus dos `*-foreground`, `--ring`, `--sidebar-primary` y
  `--sidebar-ring`. El `--green #12A150` del handoff **es «el color principal»**, no un verde
  literal.
- **Given** cualquier agencia, **then** los colores **semánticos** no siguen al tenant: «Pagado»
  en verde, «Saldo pendiente» en terracota, los avisos en ámbar y la observación médica resaltada
  son estados, no marca. Colgados de `--primary`, una agencia con el principal en rojo mostraría
  «Pagado» en rojo y la alerta médica invisible.

### Reglas de negocio restantes

- **Given** un tour con `default_guide_id`, **when** se crea una salida, **then** ese guía se
  propone por defecto.
- **Given** un tour con reservas activas, **when** se cambia o elimina una parada de tipo `pickup`,
  **then** se notifica por correo a los pasajeros afectados y la UI lo advierte antes de guardar.

## Edge cases

- Reserva `pending_payment` o `expired`: sus pasajeros **no** entran en la planilla del guía
  (solo `confirmed` y `completed`); en el panel se pueden ver con el filtro correspondiente.
- Reserva sin viajeros cargados (`booking_travelers` vacío): la planilla muestra una fila de
  marcador de posición con el titular y «Datos pendientes», no la esconde. Un guía que no vea a
  esa persona en la lista la dejaría fuera del vehículo. Esa fila **conserva el bloque `payment`**
  (solo se anulan los campos de la persona): es una reserva con dinero, y sin él el «Total por
  cobrar» del pie no cuadraría.
- Reserva comprada a menos de 24 h de la salida: nace con la ventana de edición **ya cerrada**. El
  titular carga a sus acompañantes por la agencia. Es el caso raro, y el correcto: la planilla del
  guía ya está impresa.
- Documento repetido en la misma salida: se advierte, no se bloquea (hay homónimos y errores de
  digitación; bloquear impide cerrar la planilla el día del tour).
- Salida cancelada: la planilla se conserva en modo lectura con el aviso de cancelación, y sus
  días **liberan** al guía.
- Más de ~50 filas: se pagina en el panel; la hoja de impresión imprime **todo** el resultado
  filtrado, no solo la página visible.
- Tour archivado: el detalle y la planilla siguen accesibles en lectura.
- Guía que deja de ser miembro del tenant: pierde acceso a la planilla aunque siga como
  `guide_id` de la salida (lo resuelve `EnsureTenantGuide`).
- Tenant sin ningún usuario con rol `guide`: la migración de la Fase 1 **aborta con el listado**
  en vez de inventar el dato. Mejor una migración que falla y dice qué le falta, que una columna
  llena con un guía equivocado.
- Dato previo ilegal (solapes que existían antes de la regla): la migración los **detecta y
  reporta**; no los deshace en silencio.
- Un guía en dos agencias distintas: **no** se valida el solape entre tenants (ver Out of scope).

## Dependencias

- **F018 (RBAC)** — la planilla se autoriza por permiso (`bookings.view` en el panel,
  `guide.travelers.view` en la zona del guía) y el dato de salud por
  `bookings.passengers.medical.view`, **nuevo**. No se relaja el gate `dashboard.view`.
- **F006 (Booking)** — `booking_travelers` y el endpoint de sincronización de viajeros son el
  origen de los datos.
- **F007 (Pagos)** — `payments` y `bookings.paid_amount` sostienen el estado de pago.
- **F017 (Productos y salidas)** — `tour_dates` es la salida; el CRUD de salidas ya existe y es lo
  que endurecen las Decisiones 2 y 3.
- **F003 (CRUD de tours)** — es la spec que este feature rediseña; F003 no se borra, se marca como
  superada en su Changelog.
- **F008 (Notificaciones)** — infraestructura del aviso por cambio de parada de recogida.
- **PR #15 (mapa de ruta)** — `TourRouteMapSection`, `TourRouteStopsBuilder`, `useLeaflet` y
  `tour_stops` se reutilizan tal cual.

## Endpoints involucrados

```
GET    /api/v1/admin/tours/{tour}/passengers
GET    /api/v1/admin/tours/{tour}/passengers/export
GET    /api/v1/guide/tour-dates/{tourDate}/passengers
GET    /api/v1/guide/tour-dates/{tourDate}/passengers/export
GET    /api/v1/guide/tours/{tour}                            (detalle en lectura)
GET    /api/v1/admin/guides/availability?from&to
POST   /api/v1/admin/bookings/{bookingNumber}/passengers
PUT    /api/v1/admin/passengers/{traveler}
POST   /api/v1/admin/bookings/{bookingNumber}/payments        (pago manual)
GET    /api/v1/bookings/{bookingNumber}                       (existente, se documenta; D10)
PUT    /api/v1/bookings/{bookingNumber}/travelers             (existente, se amplía; ventana D10)
POST   /api/v1/admin/tours/{tour}/dates                       (existente, endurecido)
PUT    /api/v1/admin/tour-dates/{tourDate}                    (existente, endurecido)
PATCH  /api/v1/admin/tour-dates/{tourDate}/guide              (existente, endurecido)
GET    /admin/tours/{tour}                                    (pestaña Pasajeros)
GET    /guide/tours/{tour}                                    (página del guía)
GET    /guide/tour-dates/{tourDate}/passengers                (página del guía)
```

Se **elimina** `GET /api/v1/guide/tour-dates/{tourDate}/travelers` (`routes/api.php:91`): nadie lo
consume desde el frontend.

(Detalle en [`contracts.md`](./contracts.md))

## Componentes UI

- **Pages:** `Admin/Tour/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue` (rediseño),
  `Guide/Passengers.vue` (nueva), `Guide/TourShow.vue` (nueva, detalle en lectura),
  `Guide/Schedule.vue` (enlaces a las dos).
- **Organisms:** `PassengerManifest.vue` (el componente único que usan las dos zonas, con
  `:readonly`), `TourKpiGrid.vue`, `TourAdminCard.vue`, `TourPublishChecklist.vue`,
  `TourProgressRail.vue`, `TourImpactCard.vue`, `TourDeparturesTable.vue`.
- **Molecules:** `PassengerRow.vue`, `PassengerDrawer.vue`, `PassengerFormDialog.vue`,
  `PassengerFilters.vue`, `GuideSelect.vue` (con disponibilidad), `ChipsInput.vue`,
  `DifficultySelector.vue`, `OccupancyBar.vue`, `PaymentStatusChip.vue`.
- **Atoms:** `InitialsAvatar.vue`, `MonoLabel.vue`.

## Datos requeridos

Tablas: `booking_travelers` (ampliada), `bookings`, `payments`, `tour_dates` (`guide_id` NOT NULL,
`ends_at` derivado), `tours` (ampliada con `default_guide_id`), `tour_stops`, `users`.

---

## Out of scope (explícitamente NO se hace)

- **Reserva de mostrador (walk-in).** «+ Agregar pasajero» agrega personas a una reserva
  existente. Crear una reserva desde el panel exige un `user_id` (hoy `bookings.user_id` es NOT
  NULL) y es un feature propio, con su alta de cliente.
- **Que el guía edite el tour.** Decisión 1: ve todo, no toca nada.
- **Saldo por pasajero como dato propio.** El dinero vive en `bookings`/`payments`; el reparto por
  pasajero se deriva, no se almacena (D3 en [`plan.md`](./plan.md)).
- **Módulos «Reservas», «Pasajeros», «Pagos», «Salidas» y «Guías» del menú del handoff.** Son
  enlaces muertos en el prototipo (`href="#"`, `tours-admin-montree.html:262-276`): no hay pantalla
  diseñada detrás de ninguno. La planilla se entrega dentro del tour y en la zona del guía.
- **Re-barrido de la paleta al `#12A150` del handoff.** Decisión 4: el color principal es el del
  tenant; se conservan los tokens de marca ya aplicados.
- **Instrument Serif y Plus Jakarta Sans.** Se conserva Instrument Sans (decisión previa,
  ratificada). Las «etiquetas mono» se resuelven con mayúsculas + `letter-spacing`.
- **Solape de un guía entre dos agencias.** Un usuario guía pertenece a un tenant; si mañana una
  persona guía para dos, la regla no la protege.
- **Ruta real sobre calles (OSRM/Mapbox/GPX).** Sigue fuera, como en el mapa público.

## Decisiones abiertas

**Ninguna.** Las ocho decisiones quedaron ratificadas por producto el 2026-08-20 en tres rondas
(detalle y fundamento en `proyectos/montree/tablero/paginas/tourspax.md` y en
[`plan.md`](./plan.md) §2):

| # | Decisión | Dónde se implementa |
|---|---|---|
| 1 | El guía ve todo (salud incluida) y no toca nada; sigue en `/guide/*` | D1 · Fase 4 |
| 2 | Toda salida lleva guía; `guide_id` pasa a `NOT NULL`; se elimina «Falta guía» | D7 · Fases 1 y 5 |
| 3 | Un guía, una salida a la vez, por días calendario completos | D9 · Fase 5 |
| 4 | La paleta es del tenant; los semánticos quedan fijos, con 5 tokens nuevos | D5 · Fase 6 |
| 5 | El saldo del pasajero se calcula, no se guarda | D3 · Fase 3 |
| 6 | El checklist de publicación avisa; no bloquea más de lo que ya bloquea | D7 · Fase 7 |
| 7 | `sales` no ve EPS ni observaciones: permiso `bookings.passengers.medical.view` | D2 · Fases 1 y 3 |
| 8 | El viajero edita a sus acompañantes hasta 24 h antes de la salida; la agencia, siempre | D10 · Fase 2 (backend, hecho) y Fase 4 (formulario) |

Tres puntos que estaban anotados como observación quedaron **ratificados como comportamiento
correcto** el 2026-08-20, no como deuda: (a) la zona del guía **ignora** `tour_date_id` y `status`
en vez de devolver `422`; (b) la fila de marcador de posición **conserva `payment`**; (c) el pago
manual se queda como está —referencia dentro de `gateway_response`, sin notificación— y se revisa
cuando entre la pasarela de pagos.

Queda una sola coordinación operativa, que no bloquea hasta la Fase 8: **cuándo se resiembra el
entorno de QA**. El recálculo de `ends_at`, el `NOT NULL` y el reparto de guías reescriben lo que
el equipo esté mirando en ese momento.

---

## Changelog

- `2026-08-20` — Creación inicial a partir del handoff `design_handoff_tours_admin` y del
  requerimiento de lista de pasajeros del guía.
- `2026-08-20` — Sincronización con las **siete decisiones ratificadas** por producto. Cambios de
  fondo: (a) se cierra D2 con el permiso nuevo `bookings.passengers.medical.view` y `sales` deja
  de ver EPS y observaciones en las cinco superficies; (b) se cierra D6 al revés de como estaba
  supuesto — el guía es **obligatorio** por salida, `guide_id` pasa a `NOT NULL` y desaparece
  «Falta guía»; (c) entra la **disponibilidad del guía** por días calendario con `ends_at`
  derivado, que no estaba ni en el handoff ni en el código; (d) se cierra D1 ampliando el alcance
  del guía a un **detalle de tour en modo lectura**, no solo la planilla; (e) la paleta se
  reformula como «el color principal es el del tenant» y solo los semánticos quedan fijos; (f) se
  fija que ninguna decisión se toma sobre el contenido de una base de datos de prueba.
- `2026-08-20` — **Decisión 8 (D10): ventana de edición de pasajeros por el viajero.** El titular
  edita a sus acompañantes hasta 24 h antes de `tour_date.starts_at` (configurable en
  `config/montree.php` → `passengers.traveler_edit_cutoff_hours`); después queda congelada **solo
  para él**. El administrador de la agencia no se ve afectado. Razón: el guía imprime la planilla
  el día anterior; si el dato cambia después, el papel miente y el contacto de emergencia impreso
  deja de servir. Entran una user story del guía, un bloque de acceptance criteria, dos edge cases
  y la fila 8 de la tabla de decisiones. Backend ya implementado; falta el formulario del viajero
  (Fase 4).
- `2026-08-20` — **Fase 7: el resumen corto pasa a bloquear la publicación.** D7 y la regla 4 del
  handoff ya lo listaban como condición de activación, pero no lo exigía nadie:
  `short_description` es `nullable` en el Form Request, `ChangeTourStatusAction` solo miraba imagen
  y guía, y el checklist del riel —la única lista que existía, en el frontend— lo pintaba como
  recomendado. Con la lista unificada en `App\Services\Tour\TourPublishChecklist`, que emiten la
  respuesta del tour y la activación, la contradicción tenía que resolverse hacia un lado: se
  resuelve como dice la spec. Error nuevo `TOUR_NEEDS_SUMMARY_TO_ACTIVATE`. No afecta a los tours ya
  activos —la condición se comprueba en la transición—, solo a quien pause uno y quiera volver a
  publicarlo sin resumen. Las paradas siguen recomendadas, sin cambio.
- `2026-08-20` — Ratificación de tres puntos que estaban anotados como observación y **dejan de ser
  deuda**: la zona del guía ignora `tour_date_id` y `status` en vez de devolver `422`; la fila de
  marcador de posición conserva `payment` (solo se anulan los campos de la persona); el pago manual
  se queda como está y se revisa con la futura pasarela. Detalle en el Changelog de
  [`contracts.md`](./contracts.md).
- `2026-08-21` — **Fase 8: cierre.** Verificación en navegador con las tres cuentas y dos tenants,
  responsive de 390 a 1440, revisión del feature y aviso a QA redactado
  ([`aviso-qa.md`](./aviso-qa.md)). Un fallo real que la suite no podía ver: los tests corren sobre
  SQLite y la aplicación sobre MySQL, y el listado de tours devolvía `500` por un
  `IN (subconsulta con LIMIT)` que MySQL rechaza (error 1235). Corregido a comparación escalar, con
  un test que vigila la forma del SQL. Esta spec queda como la vigente del panel de tours: F003 se
  marcó superada en su propio Changelog.

---

## TODO

**Nada abierto.** El único pendiente que vivía aquí —el formulario del viajero con la ventana de
edición cerrada— se cerró en la Fase 4: `Booking/Show.vue` pasa `can_edit_travelers` y
`travelers_edit_deadline` a `BookingTravelersSection.vue`, que se pinta en solo lectura con la fecha
límite en vez de dejar escribir para recibir un `409`.
