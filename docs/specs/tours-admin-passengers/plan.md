# tours-admin-passengers — Plan técnico

> Decisiones técnicas para implementar este feature.
> Backend, frontend, base de datos, tests.

---

## 0. Regla de método

Las decisiones de este feature salen del **requerimiento**, de las **reglas de negocio
confirmadas por producto** y del **código** (esquema, Actions, Form Requests, seeders). Lo que hay
cargado en cualquier base de datos —local, de demo o del entorno de QA— es **dato de prueba** y no
se usa como evidencia: ni para dimensionar el trabajo, ni para justificar una decisión, ni para
formular una pregunta. Toda migración y todo seeder se diseñan para **cualquier estado inicial**,
no para el que hoy se ve en una consulta.

---

## 1. Resumen

Se amplía `booking_travelers` con lo único que falta (EPS y parentesco del contacto de emergencia
— el resto de columnas ya existe y ya se guarda), se crean dos enums (`DocumentType`, `Eps`), y
sobre eso se monta un `PassengerResource` único que sirven dos controladores distintos —uno del
panel y uno de la zona del guía— con autorizaciones distintas pero la **misma respuesta**, salvo
la máscara del dato de salud, que es **un solo chequeo** en el Resource.

En paralelo se endurece la salida: `guide_id` pasa a `NOT NULL`, `ends_at` se deriva de la
duración del tour y una regla reusable (`GuideIsAvailable`) impide asignar a un guía ocupado en
cualquiera de los días calendario de la salida, en los **tres** caminos que asignan guía.

El frontend consume el contrato con un solo organism (`PassengerManifest.vue`) que montan la
pestaña «Pasajeros» del panel y la zona del guía, y las cuatro vistas del CRUD se rehacen contra
el handoff reutilizando los organisms existentes (`TourForm`, `TourDatesPanel`,
`TourRouteStopsBuilder`, `TourRouteMapSection`, `TourImageUploader`, `TourStatusBadge`).

---

## 2. Decisiones

Las diez quedaron **ratificadas por producto el 2026-08-20**. Ninguna está abierta.

### D1 — El guía ve todo, no toca nada, y no entra al panel

Confirmado: el guía consulta **el detalle completo de los tours donde está asignado** —datos de
salud de los viajeros incluidos— y no modifica nada. Crear, editar, actualizar, pausar y archivar
son del administrador.

`design_handoff_tours_admin/README.md` pide «el mismo componente para el rol guía y el rol
administrador». Se cumple a nivel de **componente**, no de ruta: el guía sigue en `/guide/*` y
recibe dos páginas nuevas, `Guide/Passengers.vue` y `Guide/TourShow.vue`, que montan los mismos
componentes en modo lectura.

Por qué: `routes/web.php:79` y `routes/api.php:96` montan todo `admin/*` detrás de
`tenant_admin.only` + `can:dashboard.view` precisamente para que el guía no entre con sus
`tours.view`/`departures.view`. Abrirle `/admin/tours/{tour}` obligaría a reescribir ese gate y a
filtrar el resto de la pantalla (salidas de otros guías, precios, acciones de edición) rol por
rol — exactamente lo que F018 vino a eliminar. La spec de F018 lo dice como user story:
«Como `guide`, quiero seguir viendo solo mi agenda y los viajeros de mis salidas, sin acceso a
ninguna pantalla de administración».

**El alcance del guía se filtra por pertenencia, no por permiso.** Alcanza los tours donde tiene
al menos una salida asignada, y de ellos solo ve sus salidas. Un tour de la misma agencia sin
salida suya devuelve `403`. Es una regla de pertenencia, no de catálogo de permisos: por eso vive
como comprobación explícita en el controlador de la zona del guía, no en una Policy.

### D2 — Permiso nuevo: `bookings.passengers.medical.view`

**Decisión 7 de producto: `sales` no ve el dato de salud.** Vende; la EPS, las alergias y la
discapacidad de un pasajero no son asunto suyo. Hoy las vería: el rol tiene `bookings.view`
(`RolesAndPermissionsSeeder.php`, `ROLE_PERMISSIONS['sales']`), que es la llave con la que se abre
la planilla.

Se separa en un permiso propio dentro del módulo `bookings`, que hoy tiene
`['bookings.view', 'bookings.update', 'payments.refund']`:

| Rol | `bookings.view` | `bookings.passengers.medical.view` |
|---|---|---|
| `admin` | sí (hereda el catálogo completo) | **sí** |
| `guide` | no aplica (usa `guide.travelers.view`) | **sí** |
| `sales` | sí | **no** |
| `operator` | no lo tiene hoy | no |

El chequeo es **uno solo**, en `PassengerResource`, y vale igual en las dos zonas. Dos
comprobaciones distintas para el mismo dato es la forma habitual de que una se olvide.

Lo que hay que tapar es más que tres campos:

| Superficie | Sin el permiso |
|---|---|
| Campos del pasajero | `eps`, `eps_label`, `eps_other` y `medical_notes` **no se serializan** — no llegan al navegador ocultos por CSS |
| Segmento «Con observaciones» | `403`: un filtro que dice *quién* tiene una condición médica la delata sin mostrarla |
| Pie de tabla | `meta.summary.with_notes` se omite; el conteo de saldos se queda |
| Export CSV | Sin las columnas `EPS` y `Observaciones`, no vacías: una columna vacía invita a preguntar qué falta |
| Escritura | El Form Request **descarta** esos tres campos. `sales` tiene `bookings.update`; sin este filtro, edita a ciegas lo que no puede leer y borra la alergia que el guía necesita |

El **contacto de emergencia sí lo ve `sales`**: es un teléfono de logística, no un dato clínico, y
quien atiende una reserva por chat necesita poder confirmarlo.

### D3 — El saldo por pasajero se deriva; no se almacena

El handoff modela `Pasajero { valorTotal, abonado }`. Montree ya tiene la verdad del dinero en
`bookings.total_amount` / `bookings.paid_amount` y en `payments`, con reembolsos y conciliación de
pasarela colgando de ahí. Duplicarla por pasajero crea dos fuentes de verdad que se desincronizan
en el primer reembolso parcial.

Implementación: `share_amount = round(booking.total_amount / booking.travelers_count, 2)`,
`paid_amount` con la misma proporción sobre `booking.paid_amount`, y el chip de estado se decide
por el saldo de **la reserva**. El drawer muestra además «Reserva <número> · N pasajeros» para que
quede claro que el saldo es compartido.

Consecuencia visible: dos pasajeros de la misma reserva nunca aparecerán uno «Pagado» y otro «Con
saldo». Es correcto — pagó la reserva, no la persona.

### D4 — «Observaciones» reutiliza `medical_notes`

La columna existe desde `2026_05_17_170010_create_booking_travelers_table.php`, ya se valida
(`SyncBookingTravelersRequest:40`) y ya se guarda (`SyncBookingTravelersAction:40`); lo que nunca
existió es el input. Se le da el significado del handoff («alergias, condición o discapacidad
física»). No se crea una columna `observations` que sería su sinónimo. `dietary_restrictions` se
conserva como campo aparte y se muestra en el drawer, no en la tabla.

Mismo caso para `email`, `emergency_contact_name` y `emergency_contact_phone`: **la API ya es más
ancha que la pantalla**. Eso abarata la Fase 2 — para varios de los siete datos es trabajo de
front, no de contrato.

Lo que sí es nuevo: el contacto de emergencia hoy se captura **por reserva**
(`Booking/Create.vue:152` → `CreateBookingAction:92`, dentro del JSON `bookings.contact_snapshot`)
y **ningún formulario ni Action escribe** las columnas `emergency_contact_*` de
`booking_travelers`. El requerimiento lo pide por persona: cuando el grupo son cuatro personas de
dos familias, un solo teléfono no sirve. La migración traslada el dato del snapshot al primer
viajero de cada reserva que lo tenga; si no hay nada que mover, no hace nada.

### D5 — El color principal es el del tenant; los semánticos son fijos

`useTenantBranding.ts` ya sobrescribe en `:root` siete variables a partir de los tripletes HSL que
manda el backend — `--primary`, `--secondary`, sus dos `*-foreground`, `--ring`,
`--sidebar-primary` y `--sidebar-ring` — y deduce el contraste del texto por la luminosidad del
color de la agencia.

Entonces el `--green #12A150` del handoff **es «el color principal»**, no un verde literal: en
pantalla es el de cada agencia. Van por `--primary` los botones primarios, el subrayado de la
pestaña activa, las barras de ocupación, el pin de recogida y el ítem activo de la barra lateral.
No se re-barre la paleta: la app ya está pintada con los tokens del handoff anterior, en 52
archivos desplegados en `develop` (PR #15).

Lo que **no** sigue al tenant son los colores **semánticos**: «Pagado» en verde, «Saldo pendiente»
en terracota, los avisos en ámbar y la observación médica resaltada son estados, no marca.
Colgados de `--primary`, una agencia con el principal en rojo mostraría «Pagado» en rojo y la
alerta médica invisible. Se quedan fijos, en tokens de marca propios:

| Token nuevo | Valor | Para qué |
|---|---|---|
| `--brand-warn` | `#b98a17` | avisos, tour pausado |
| `--brand-warn-50` | `#fdf6e3` | fondo del aviso |
| `--brand-drop-50` | `#f7e6dc` | fondo del KPI de saldo pendiente |
| `--brand-green-50` | `#eff6f0` | hover de superficies, tarjeta de dificultad activa |
| `--brand-line-2` | `#efe9dc` | separadores internos de tabla |

Esto cierra el pendiente que dejó abierto el handoff anterior, que no definía color de éxito ni de
alerta: éxito = `--brand-green-600`, alerta = `--brand-warn`, negativo = `--brand-drop`.

### D6 — Tipografía: se conserva Instrument Sans

El handoff pide Plus Jakarta Sans + Instrument Serif + IBM Plex Mono. El usuario ya decidió no
agregar Instrument Serif en el handoff anterior. Las «etiquetas mono» del diseño se resuelven con
`text-[10.5px] uppercase tracking-[0.09em]` sobre la sans en un atom `MonoLabel.vue`; el efecto
visual (etiqueta técnica, discreta) se conserva sin sumar dos familias y ~180 kB de fuentes.

### D7 — Guía obligatorio (bloquea) · checklist de publicación (avisa)

**Salida sin guía: se elimina.** Decisión 2 de producto, y **contradice la regla 3 del handoff**,
que dejaba abrir una salida sin guía con una etiqueta de aviso. `guide_id` es obligatorio al crear
y al editar, y la columna pasa a **`NOT NULL` en la Fase 1, en un solo paso**.

Producto confirmó que lo que hoy se llama «producción» es el entorno donde el equipo de producto y
QA prueban antes de salir al mercado: no hay agencias reales ni salidas vendidas que proteger. Eso
elimina el estado intermedio —obligatorio en el Form Request primero, `NOT NULL` después— y con él
la ventana en la que la regla vive solo en la aplicación y cualquier `update()` directo la burla.

Se cae del diseño: la etiqueta ámbar «Falta guía» (`tours-admin-montree.html:507`), la acción
«Asignar guía», el select con borde `#E0C98A` de la fila sin guía, la fila «Sin guía asignado»
del detalle (`:595`) y la opción «Sin asignar» de todos los selects de guía (`:344`, `:488`,
`:497`).

Se aplica **por salida** —es donde el guía trabaja y donde la regla de fechas tiene sentido— más
`tours.default_guide_id`, obligatorio al publicar, como la propuesta que hereda cada salida nueva.

**Publicación del tour: sigue avisando.** El checklist del riel derecho lista las 4 condiciones del
handoff. Bloquean la activación las que ya bloquean hoy (nombre, resumen, precio, cupo, ≥1
imagen); las paradas de recogida y regreso se muestran como recomendadas. Endurecerlas dejaría en
`draft` a tours activos hoy la próxima vez que se editen.

### D8 — El mapa ya está resuelto

`useLeaflet.ts` (npm, import dinámico, no evalúa en SSR), `useTourRouteMap.ts`,
`TourRouteMapSection.vue` y `TourRouteStopsBuilder.vue` salieron en PR #15. La pestaña «Ruta y
mapa» reutiliza el builder tal cual, y el mapa de solo lectura del detalle —del panel y del guía—
reutiliza `TourRouteMapSection`. Lo único nuevo es llamar a `fit()` en el `watch` de la pestaña
activa: un mapa creado dentro de un contenedor con `display:none` calcula viewport 0×0 e
`invalidateSize()` solo no alcanza — hace falta también `fitBounds()`.

### D9 — Disponibilidad del guía: días calendario, tres caminos, `ends_at` derivado

Decisión 3 de producto. Requerimiento **nuevo**: no está en el handoff ni existe nada de esto en
el código. Un guía no puede tener dos salidas en la misma fecha, y si el tour dura tres o cinco
días, esos días le quedan bloqueados completos.

| Lo que hace falta | Estado hoy |
|---|---|
| Saber cuándo termina una salida | `ends_at` existe y es opcional: los dos Form Requests lo aceptan (`StoreTourDateRequest:29`) y **nadie lo valida contra la duración del tour** |
| Conocer la duración | Solo hay `tours.duration_hours` (`create_tours_table.php:23`) |
| Validar el solape | No existe: `App\Actions\Team\AssignGuideAction` hace `update(['guide_id' => …])` sin comprobar nada |
| Mostrar quién está libre | El select de guía lista a todos por igual |

Cómo se resuelve:

- **`ends_at` se deriva** en el servidor de `starts_at + duration_hours` al crear o editar la
  salida, y **deja de aceptarse como entrada del cliente**. El problema no es que esté vacío: es
  que **nada garantiza que sea verdad**. `TourDateFactory:30` le suma 4 horas fijas a cualquier
  tour, de modo que un tour de varios días «termina» la misma tarde. Un `ends_at` que miente es
  peor que uno vacío, porque la regla de disponibilidad lo creería.
- La ocupación se calcula por **días calendario**, no por horas: `[date(starts_at) …
  date(ends_at)]`. Un tour de 5 días bloquea los 5, aunque el último termine a las 9 de la mañana.
- Ocupan las salidas en estado `open`, `full` y `closed`; **solo una `cancelled` libera sus días**.
  Agotada quiere decir vendida entera, no suspendida: el guía sale igual. Lo que decide la
  ocupación es si la salida ocurre, no si le quedan cupos.
- La validación vive en una regla reusable, `App\Rules\GuideIsAvailable`, que corren los **tres**
  caminos que asignan guía: crear salida, editar salida y
  `PATCH /api/v1/admin/tour-dates/{tourDate}/guide` (`routes/api.php:119`). Sin eso, la regla se
  salta por el tercero.
- La UI no ofrece lo que va a rechazar: el select marca a los ocupados como deshabilitados y con
  el motivo — «Ocupado 12–14 sep · Valle de Cocora» — alimentado por
  `GET /api/v1/admin/guides/availability`.

Dos bordes que cerrar, y que tampoco salen del handoff:

- **Cambiar `duration_hours` de un tour** alarga retroactivamente todas sus salidas y puede crear
  solapes que antes no existían. Se valida al guardar el tour y se avisa qué salidas chocarían.
- **El dato previo puede ser ilegal.** `TourDateFactory` reparte guías al azar, así que **puede**
  generar solapes; la migración que corrige `ends_at` no deshace por sí sola un solape existente.
  Se cierra por dos lados: el seeder y la factory pasan a repartir guías respetando la regla
  —dejan de poder producir un solape— y la migración **detecta y reporta** los que encuentre en
  vez de dejarlos pasar en silencio.

**Fuera de alcance:** el solape de un guía **entre agencias**. Un usuario guía pertenece a un
tenant; si mañana una persona guía para dos, la regla no la protege.

### D10 — Ventana de edición del viajero: fuera de `isLocked()`, en un solo punto

Decisión 8 de producto (2026-08-20). El titular edita a sus acompañantes hasta **24 h antes** de
`tour_dates.starts_at`; después la planilla se congela **solo para él**. El administrador de la
agencia **no** se ve afectado: el cambio de última hora se resuelve por la agencia. Razón de
negocio: el guía descarga o imprime la planilla el día anterior; si el dato cambia después, el
papel miente y el contacto de emergencia impreso deja de servir.

- **El plazo es configuración, no una constante.** `config/montree.php` →
  `passengers.traveler_edit_cutoff_hours`, env `MONTREE_TRAVELER_EDIT_CUTOFF_HOURS`, default `24`.
  El número 24 no aparece en ninguna rama de la lógica.
- **Modelo:** `Booking::travelerEditDeadline(): ?CarbonInterface` y
  `Booking::isTravelerEditWindowClosed(): bool`.
- **La guarda NO entró en `Booking::isLocked()`.** Esa la comparten los tres caminos de escritura
  —`SyncBookingTravelersAction`, `UpdatePassengerAction` y `RegisterManualPaymentAction`—; metida
  ahí habría congelado también el panel y el pago manual, que son justo por donde se resuelve el
  cambio de última hora. Se aplica en **un solo punto**: `SyncBookingTravelersAction`, después de
  `isLocked()`.
- **Error `409` con código `BOOKING_TRAVELER_EDIT_WINDOW_CLOSED`**
  (`BookingException::travelerEditWindowClosed()`), misma forma que el `BOOKING_TRAVELERS_LOCKED`
  que ya emite ese camino: los dos son regla de **estado**, no problema del payload.
- **Contrato:** `BookingResource` gana `can_edit_travelers` (bool) y `travelers_edit_deadline`
  (ISO8601 o `null`). Sin ellos el frontend no puede hacer otra cosa que dejar escribir y chocar
  contra el `409`.
- **Bordes:** sin salida o sin `starts_at` ⇒ deadline `null` ⇒ no bloquea (a nivel de esquema
  `bookings.tour_date_id` y `tour_dates.starts_at` son `NOT NULL`, así que el caso solo se alcanza
  con la relación sin resolver: se cubre con test unitario, no con uno de HTTP que tendría que
  falsear el esquema). Salida ya iniciada ⇒ bloqueada por la misma comparación, sin rama aparte.
- **Tests:** `tests/Feature/Passengers/TravelerEditWindowTest.php` (7) y
  `tests/Unit/Booking/TravelerEditDeadlineTest.php` (2).
- **Pendiente:** el formulario del viajero (Fase 4) debe consumir los dos campos y renderizarse en
  solo lectura con el aviso, en vez de dejar escribir para recibir un `409`.

---

## 3. Backend

### Migrations

- `XXXX_add_health_and_emergency_fields_to_booking_travelers_table.php`
  - `eps` string nullable, después de `medical_notes`
  - `eps_other` string(120) nullable
  - `emergency_contact_relationship` string(60) nullable, después de `emergency_contact_name`
  - índice `['tenant_id', 'document_number']` para la búsqueda de la planilla
  - traslado del contacto de emergencia de `bookings.contact_snapshot` al primer viajero de cada
    reserva que lo tenga (D4). Idempotente: si no hay nada que mover, no hace nada
- `XXXX_add_default_guide_id_to_tours_table.php`
  - `default_guide_id` foreignId nullable → `users`, `nullOnDelete()`
- `XXXX_require_guide_and_derive_ends_at_on_tour_dates_table.php` — la delicada:
  1. Recalcula `ends_at` de **todas** las salidas como `starts_at + tours.duration_hours`.
  2. Asigna a las salidas sin guía el `default_guide_id` del tour; si no lo hay, el primer usuario
     con rol `guide` del tenant.
  3. **Aborta con el listado de tenants** que no tengan ningún usuario `guide`, en vez de inventar
     el dato. Mejor una migración que falla y dice qué le falta, que una columna llena con un guía
     equivocado.
  4. `guide_id` → `NOT NULL`.
  5. Detecta y **reporta** por consola los solapes de guía que hayan quedado del dato previo.

Sin `down()` destructivo: revierten con `dropColumn`/`dropForeign` y devolviendo `guide_id` a
`nullable`.

### Enums

- `App\Enums\DocumentType` — `cc`, `ce`, `ti`, `passport`, `other` + `label()`. Son los cinco que
  hoy están escritos a mano dentro de `BookingTravelersSection.vue:48-52`. **Sin `nit`**: el
  pasajero es una persona.
- `App\Enums\Eps` — `sura`, `nueva_eps`, `sanitas`, `salud_total`, `other` + `label()`
  + `requiresDetail(): bool`.

### Modelos

- `BookingTraveler` — `$fillable` + casts de los campos nuevos; accessor `epsLabel()`;
  scope `search(string $q)` sobre `full_name` y `document_number`.
- `Tour` — relación `defaultGuide(): BelongsTo`.
- `TourDate` — `occupiedDays(): CarbonPeriod` (`[date(starts_at) … date(ends_at)]`) y scope
  `occupying()` (estados `open` + `full` + `closed`; solo `cancelled` queda fuera).
- `Booking` — accessor `dueAmount()` (`total_amount - paid_amount`) y `passengerShare()` para D3.

### Rules

`App\Rules\GuideIsAvailable` — recibe el rango de la salida y, opcionalmente, el
`tour_date_id` que se excluye (la que se está editando). Falla con el rango y el nombre del tour
que ocupa. La usan `StoreTourDateRequest`, `UpdateTourDateRequest` y `AssignGuideAction`.

### Actions

- `App\Actions\Passengers\StorePassengerAction` — crea un `BookingTraveler` sobre una reserva
  existente; valida el tope `travelers_count`.
- `App\Actions\Passengers\UpdatePassengerAction` — upsert de los campos; normaliza `eps_other` a
  `null` cuando `eps ≠ other` (regla 1 del handoff, en un solo sitio para las tres entradas:
  panel, viajero y seeder).
- `App\Actions\Payments\RegisterManualPaymentAction` — crea el `Payment` con
  `gateway = PaymentGateway::Manual`, `status = Completed`, y suma a `bookings.paid_amount` dentro
  de una transacción.
- `App\Actions\TourDate\CreateTourDateAction` / `UpdateTourDateAction` (existentes) — **derivan**
  `ends_at` de `duration_hours`; `ends_at` deja de venir del cliente.
- `App\Actions\Team\AssignGuideAction` (existente) — pasa a correr `GuideIsAvailable`.
- `App\Actions\Tours\NotifyPickupChangeAction` — despacha la notificación de la regla 6 a los
  pasajeros de las reservas activas del tour cuando cambia una parada `pickup`.

### Queries

- `App\Queries\PassengerManifestQuery` — el constructor de la consulta que comparten los dos
  controladores: recibe la colección de `tour_dates`, los filtros y devuelve el paginador más el
  resumen. Aquí vive el `with(['booking.user', 'booking.tourDate'])` que evita el N+1 (una
  planilla de 50 filas son 50 reservas).
- `App\Queries\GuideAvailabilityQuery` — los días ocupados por guía en un rango, para el select y
  para la regla.

### Form Requests

- `Admin\StorePassengerRequest` / `Admin\UpdatePassengerRequest` — reglas de `contracts.md`, más
  la **máscara de escritura**: `prepareForValidation()` elimina `eps`, `eps_other` y
  `medical_notes` del payload si el actor no tiene `bookings.passengers.medical.view`. Descarta,
  no rechaza: `sales` tiene un caso legítimo (corregir un teléfono).
- `Admin\RegisterManualPaymentRequest`.
- `PassengerManifestRequest` — valida los query params (`segment`, `q`, `tour_date_id`,
  `per_page`) y **rechaza con 403** `segment=obs` sin el permiso médico.
- `Admin\TourDate\StoreTourDateRequest` / `UpdateTourDateRequest` (existentes) — `guide_id`
  `required` + `GuideIsAvailable`; `ends_at` `prohibited`.
- `Booking\SyncBookingTravelersRequest` (existente) — se le agregan `emergency_contact_name`,
  `emergency_contact_relationship`, `emergency_contact_phone`, `eps` y `eps_other`. `email`,
  `phone`, `dietary_restrictions` y `medical_notes` **ya están** (`:40`).

### Controllers

- `Api\V1\Admin\TourPassengerController` (`index`) + `TourPassengerExportController` (`__invoke`).
- `Api\V1\Guide\TourDatePassengerController` (`index`) + su export.
- `Api\V1\Guide\GuideTourController` (`show`) — el detalle en lectura, con la comprobación de
  pertenencia.
- `Api\V1\Admin\GuideAvailabilityController` (`__invoke`).
- `Api\V1\Admin\PassengerController` (`store`, `update`).
- `Api\V1\Admin\BookingPaymentController` (`store`).
- `Guide\GuidePagesController@passengers` y `@tour` — las dos páginas Inertia nuevas.
- `GuideController@travelers` se **elimina** junto con su ruta (`routes/api.php:91`): nadie lo
  consume.

### Resources

- `PassengerResource` — el shape de `contracts.md §0`, con el **único** `mergeWhen()` del permiso
  médico.
- `PassengerManifestSummary` — el bloque `meta.summary` (omite `with_notes` sin el permiso).
- `DepartureOptionResource` — el bloque `meta.departures`.
- `GuideTourResource` — el detalle en lectura del guía, con `my_departures` filtrado.

### Policies

`BookingTravelerPolicy` — `view`/`update` por permiso y por tenant. El acceso del guía **no** pasa
por la Policy sino por la comprobación explícita `tourDate.guide_id === auth()->id()` (y, para el
detalle de tour, «tiene al menos una salida en este tour») en los controladores de su zona: es una
regla de pertenencia, no de permiso.

### Seeder y factory

`RolesAndPermissionsSeeder` gana `bookings.passengers.medical.view` en el módulo `bookings` y en
las matrices de `admin` (implícito: hereda el catálogo) y `guide`. **No** en `sales`.

`TourDateFactory` deriva `ends_at` de la duración del tour en vez de sumar 4 horas fijas, y
reparte guías **sin solaparlos**. `DemoTenantSeeder` genera una salida con pasajeros que cubran
los tres casos que hay que poder ver —con saldo, con observaciones y con EPS «Otra»— y un tour de
varios días para probar el bloqueo del guía. Con eso, **resembrar es la vía limpia** para dejar
cualquier entorno conforme a las reglas nuevas.

### Notifications

`PickupPointChangedNotification` (mail + database) — regla 6 del handoff, encolada.

---

## 4. Frontend

### Types

`resources/js/types/passenger.ts` — `Passenger`, `PassengerPayment`, `PassengerFilters`,
`PassengerManifestMeta` (incluye `can_view_medical`), `DocumentType`, `Eps`. Espejo exacto de
`contracts.md §0`.

`resources/js/types/guide-availability.ts` — `GuideAvailability`, `BusyBlock`.

### Composables

- `usePassengerManifest(source)` — encapsula fetch, filtros, paginación y el recálculo del pie.
  `source` es `{ kind: 'tour', tourId }` o `{ kind: 'departure', tourDateId }`, y de ahí sale a
  qué endpoint de Wayfinder llama. Es lo que permite que el organism sea uno solo.
- `useGuideAvailability(range)` — alimenta el select de guía con los bloques ocupados y arma el
  texto del motivo.

### Componentes

`PassengerManifest.vue` recibe `:readonly` (true en la zona del guía) y emite `passenger-saved`.
Contiene tabla, drawer y modal. Con `meta.can_view_medical` en `false`, la columna de
observaciones **no se dibuja** y el segmento «Con observaciones» no se ofrece: nada de guiones ni
de candados, que solo señalan lo que hay detrás. La hoja de impresión es un bloque `@media print`
en el propio organism: oculta sidebar, topbar, filtros y acciones, fuerza `overflow: visible` en
el contenedor de la tabla, e imprime **todo el resultado filtrado**, no solo la página visible.

`GuideSelect.vue` deshabilita a los ocupados con su motivo. El resto de componentes, en
`spec.md §Componentes UI`.

### Wayfinder

Todo el consumo por `@/actions/...` generado; cero URLs a mano. `php artisan wayfinder:generate`
después de tocar rutas — recordar que el PHP del PATH es 7.4 y el proyecto exige 8.4:
anteponer `/opt/homebrew/opt/php@8.4/bin` o el build falla en `wayfinder:generate`.

### i18n

Todas las cadenas nuevas por `$t()` y registradas en `lang/en.json`. La app ya es bilingüe
(PR #13/#14): una cadena sin traducir se ve en español dentro de la UI en inglés.

---

## 5. Tests

| Qué | Dónde |
|---|---|
| `eps_other` obligatorio con `eps = other` y anulado en otro caso | `tests/Feature/Passengers/PassengerValidationTest.php` |
| Guía asignado ve la planilla; guía no asignado recibe 403 | `tests/Feature/Passengers/GuideManifestAccessTest.php` |
| Guía ve el detalle de su tour; 403 en un tour de su agencia sin salida suya; no ve las salidas de otros guías | `tests/Feature/Guide/GuideTourShowTest.php` |
| Guía sigue recibiendo 403 en `/admin/tours/{tour}` | ampliar `tests/Feature/Rbac/` |
| **D7 (a)** `sales` no recibe `eps`, `eps_other` ni `medical_notes` | `tests/Feature/Passengers/MedicalPermissionTest.php` |
| **D7 (b)** `sales` recibe 403 con `segment=obs` | idem |
| **D7 (c)** el CSV de `sales` no trae esas dos columnas | idem |
| **D7 (d)** `sales` con `bookings.update` no puede escribirlas: se descartan y el valor no cambia | idem |
| `admin` y `guide` sí las reciben, y `meta.summary.with_notes` solo aparece con el permiso | idem |
| Ningún campo sensible en la respuesta pública del tour | `tests/Feature/Passengers/PassengerPrivacyTest.php` |
| Segmentos + búsqueda recalculan `meta.summary` | `tests/Feature/Passengers/PassengerManifestFilterTest.php` |
| Reparto del saldo por pasajero (D3), incluido `travelers_count` impar | `tests/Unit/PassengerShareTest.php` |
| Pago manual: suma, tope al saldo, 409 en reserva cancelada | `tests/Feature/Payments/ManualPaymentTest.php` |
| Aislamiento de tenant en los endpoints nuevos | mismos archivos |
| CSV: encabezados, BOM y filas del filtro | `tests/Feature/Passengers/PassengerExportTest.php` |
| Reserva sin viajeros ⇒ fila de marcador de posición | `PassengerManifestFilterTest` |
| N+1 de la planilla (50 reservas ⇒ consultas acotadas) | `tests/Feature/Passengers/PassengerManifestQueryCountTest.php` |
| **D9** solape el mismo día · rango de 3 días · salida `cancelled` que libera · salida `full` que sigue ocupando (en la regla y en el endpoint) · editar la propia salida sin falso positivo · el camino del `PATCH` | `tests/Feature/TourDates/GuideAvailabilityTest.php` |
| **D9** `guide_id` ausente ⇒ 422 en los tres caminos | idem |
| **D9** `ends_at` enviado por el cliente ⇒ 422; `ends_at` derivado correcto para un tour de 51 h | `tests/Feature/TourDates/EndsAtDerivationTest.php` |
| **D9** cambiar `duration_hours` lista las salidas que quedarían en solape | `tests/Feature/Tours/DurationChangeImpactTest.php` |
| La factory y el seeder no pueden producir un solape | `tests/Feature/TourDates/SeederIntegrityTest.php` |
| **D10** ventana abierta guarda · ventana cerrada ⇒ `409 BOOKING_TRAVELER_EDIT_WINDOW_CLOSED` · el panel y el pago manual siguen funcionando con la ventana cerrada | `tests/Feature/Passengers/TravelerEditWindowTest.php` |
| **D10** deadline `null` sin salida o sin `starts_at`; salida iniciada ⇒ cerrada; el cutoff sale de config | `tests/Unit/Booking/TravelerEditDeadlineTest.php` |

---

## 6. Riesgos

| Riesgo | Mitigación |
|---|---|
| La planilla nace vacía: ninguna pantalla captura EPS ni emergencia hoy | La Fase 2 (captura del viajero) va **antes** que la UI de la planilla, no después |
| Datos sensibles filtrados a una respuesta pública o a `sales` | Test explícito de privacidad, un único `mergeWhen()` en el Resource, y las cinco superficies de D2 con test propio |
| La migración de `guide_id NOT NULL` encuentra un tenant sin ningún guía | Aborta con el listado en vez de inventar el dato; no se asume ningún estado inicial |
| Solapes preexistentes que la regla nueva daría por válidos | La migración los reporta; factory y seeder dejan de poder producirlos; resembrar es la vía limpia |
| `ends_at` mentiroso alimentando la regla de disponibilidad | Se deriva en el servidor y deja de aceptarse del cliente |
| La regla de disponibilidad se salta por el `PATCH` de asignación | Los **tres** caminos comparten `GuideIsAvailable`; hay test del tercero |
| N+1 en planillas grandes | `PassengerManifestQuery` con eager loading + test de conteo de consultas |
| El rediseño del CRUD toca las 4 páginas a la vez | Va **después** de la planilla y de la disponibilidad, y por pantalla, un commit cada una |
| El select de guía se construye dos veces | La Fase 5 (disponibilidad) va **antes** que la Fase 6 (rediseño) |
| El handoff pide módulos que no existen (Reservas, Pagos, Guías) | Son `href="#"` en el prototipo; declarados fuera de alcance en la spec |
| La resiembra pisa lo que QA está mirando | Coordinación explícita antes de correr las migraciones en ese entorno (Fase 8) |
| El viajero escribe con la ventana ya cerrada y solo se entera por un `409` | El formulario consume `can_edit_travelers`/`travelers_edit_deadline` y se pinta en solo lectura (Fase 4) |

---

## Changelog

- `2026-08-20` — Redacción inicial del plan técnico con las decisiones D1–D9.
- `2026-08-20` — **D10 — ventana de edición del viajero.** Se documenta la decisión técnica ya
  implementada: cutoff configurable, dos métodos en `Booking`, guarda aplicada **solo** en
  `SyncBookingTravelersAction` y deliberadamente **fuera** de `isLocked()` (que comparten el panel
  y el pago manual), `409 BOOKING_TRAVELER_EDIT_WINDOW_CLOSED`, y dos campos nuevos en
  `BookingResource`. Se agregan las dos filas de tests de §5 y el riesgo del formulario que deja
  escribir sin poder guardar.
- `2026-08-20` — Ratificados como comportamiento correcto (no como deuda): la zona del guía ignora
  `tour_date_id`/`status`, la fila de marcador de posición conserva `payment`, y el pago manual se
  queda con la referencia dentro de `gateway_response` y sin notificación hasta que entre la
  pasarela. Detalle en [`contracts.md`](./contracts.md) `## Changelog`.
- `2026-08-20` — **D9 implementada (Fase 5).** Piezas reales: `App\Rules\GuideIsAvailable`,
  `App\Queries\GuideAvailabilityQuery` (bloques por guía + `durationChangeConflicts`),
  `App\Data\GuideAvailability` / `GuideBusyBlock`, `TourDate::deriveEndsAt()`,
  `AssignGuideRequest` y el trait `ValidatesTenantGuide`, que unifica la comprobación de «guía del
  tenant» que ahora comparten la salida y el guía por defecto del tour. En el frontend,
  `types/guide-availability.ts`, `useGuideAvailability` y `GuideSelect.vue`. Dos desviaciones
  documentadas en [`tasks.md`](./tasks.md): la regla de publicación vive en
  `ChangeTourStatusAction` (con `error_code`, no como error de validación) y `default_guide_id`
  entra a los Form Requests del tour y a `TourResource` para que esa regla sea satisfacible.
- `2026-08-20` — **Cerradas las dos preguntas que la Fase 5 dejó abiertas.**
  (a) `scopeOccupying()` incluye `full`: una salida agotada ocupa al guía igual que una abierta,
  y solo `cancelled` libera sus días. (b) El espejo de enums PHP → TS deja de escribirse a mano:
  lo genera `php artisan enums:typescript` en `resources/js/types/enums.generated.ts`, y
  `tests/Feature/Enums/TypeScriptEnumsAreInSyncTest.php` hace fallar la suite si queda
  desactualizado. Detalle en [`tasks.md`](./tasks.md).
