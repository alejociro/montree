# tours-admin-passengers — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.

---

## 0. Shape compartido: `PassengerResource`

Es el mismo objeto en las dos zonas (panel y guía). El guía recibe **exactamente** los mismos
campos que el administrador: la planilla es su herramienta de campo, no una versión recortada, y
los datos de salud son parte del trabajo.

Quien recibe menos es **quien no tiene `bookings.passengers.medical.view`** (Decisión 7 — el rol
`sales`): los tres campos marcados como sensibles **no se serializan**, no llegan al navegador
ocultos por CSS.

```json
{
  "id": 412,
  "booking_number": "9f1c…",
  "tour_date_id": 88,
  "departure_starts_at": "2026-09-14T06:00:00-05:00",
  "full_name": "María Fernanda Ríos",
  "is_minor": false,
  "document_type": "cc",
  "document_type_label": "Cédula de ciudadanía",
  "document_number": "1017234567",
  "email": "maria@example.com",
  "phone": "+57 300 111 2233",
  "emergency_contact_name": "Julián Ríos",
  "emergency_contact_relationship": "Hermano",
  "emergency_contact_phone": "+57 311 222 3344",
  "eps": "other",
  "eps_label": "Otra",
  "eps_other": "Compensar",
  "medical_notes": "Alergia a la penicilina.",
  "dietary_restrictions": null,
  "payment": {
    "share_amount": "180000.00",
    "paid_amount": "120000.00",
    "due_amount": "60000.00",
    "currency": "COP",
    "status": "due"
  }
}
```

| Campo | Notas |
|---|---|
| `document_type` | `cc` · `ce` · `ti` · `passport` · `other` (`App\Enums\DocumentType`). **Sin `nit`**: el pasajero es una persona. |
| `eps` | **Sensible.** `sura` · `nueva_eps` · `sanitas` · `salud_total` · `other` · `null` (`App\Enums\Eps`) |
| `eps_label` | **Sensible.** Etiqueta del enum. |
| `eps_other` | **Sensible.** Texto libre. Solo no nulo cuando `eps = other`. |
| `medical_notes` | **Sensible.** Observaciones: alergias, condición o discapacidad física. |
| `dietary_restrictions` | No sensible a efectos del permiso; se muestra en el drawer, no en la tabla. |
| `emergency_contact_*` | **No** sensible: es logística. `sales` sí los ve. |
| `payment.share_amount` | `booking.total_amount / booking.travelers_count`, redondeado a 2. Derivado. |
| `payment.paid_amount` | Misma proporción sobre `booking.paid_amount`. Derivado. |
| `payment.status` | `paid` si `due_amount = 0`; `due` en otro caso. |

**Máscara del dato de salud.** El chequeo es **uno solo**, en el Resource
(`mergeWhen($request->user()->can('bookings.passengers.medical.view'), …)` sobre `eps`, `eps_label`,
`eps_other` y `medical_notes`), y vale igual en la zona del panel y en la del guía. Dos
comprobaciones distintas para el mismo dato es la forma habitual de que una se olvide.

**Prohibido:** este Resource no aparece en ninguna respuesta pública. `PublicTourResource` y el
catálogo no lo referencian.

**Fila de marcador de posición.** Una reserva sin viajeros cargados devuelve un objeto con
`"id": null` y `full_name` = nombre del titular de la reserva (edge case de la spec). El frontend
lo pinta como «Datos pendientes».

Lo que queda en `null` son **los campos de la persona** que todavía no se cargó
(`document_type`, `document_number`, `email`, `phone`, `emergency_contact_*`, `eps`, `eps_label`,
`eps_other`, `medical_notes`, `dietary_restrictions`). Los campos que son **hechos de la reserva**
se emiten con su valor real: `booking_number`, `tour_date_id`, `departure_starts_at` y **`payment`
completo**. Ratificado por producto el 2026-08-20: el pie de la tabla muestra «Total por cobrar» y
esa fila representa una reserva con dinero pendiente; con `payment` en `null` el total mentiría
justo en el caso en que más interesa (nadie cargó los datos, probablemente tampoco pagó).

---

## GET /api/v1/admin/tours/{tour}/passengers

**Auth:** required · **Middleware:** `tenant_admin.only`, `can:dashboard.view`
**Permission:** `bookings.view` · **Permiso adicional para el dato de salud:**
`bookings.passengers.medical.view`

### Query params

| Param | Tipo | Reglas |
|---|---|---|
| `tour_date_id` | int | nullable, `exists:tour_dates,id` del mismo tour. Omitido = todas las salidas. |
| `segment` | string | nullable, `in:all,due,paid,obs`. Default `all`. **`obs` exige el permiso médico.** |
| `q` | string | nullable, max:120. Busca en `full_name` y `document_number`. |
| `status` | string[] | nullable. Estados de reserva a incluir. Default `["confirmed","completed"]`. |
| `per_page` | int | nullable, `between:10,100`. Default 50. |
| `page` | int | nullable. |

### Response 200

```json
{
  "data": [ /* PassengerResource[] */ ],
  "meta": {
    "current_page": 1, "last_page": 1, "per_page": 50, "total": 9,
    "can_view_medical": true,
    "summary": {
      "total_passengers": 9,
      "with_due": 3,
      "paid": 6,
      "with_notes": 4,
      "total_due_amount": "540000.00",
      "currency": "COP"
    },
    "departures": [
      { "id": 88, "starts_at": "2026-09-14T06:00:00-05:00", "ends_at": "2026-09-14T18:00:00-05:00",
        "capacity": 12, "booked_count": 9, "guide": { "id": 5, "name": "Camilo Ruiz" },
        "status": "open" }
    ]
  }
}
```

- `meta.can_view_medical` le dice al frontend **qué no pintar**: con `false` la columna de
  observaciones no se dibuja y el segmento «Con observaciones» no se ofrece.
- `meta.summary.with_notes` **se omite** cuando `can_view_medical` es `false`: el conteo de
  pasajeros con observaciones también es dato clínico agregado.
- `meta.summary` se calcula sobre el resultado **filtrado** (segmento + búsqueda + salida), no
  sobre el total del tour: es lo que pinta el pie de la tabla.
- `departures[].guide` nunca es `null` (Decisión 2: `guide_id` es `NOT NULL`).

### Errores

| Código | Cuándo |
|---|---|
| `403` | Sin `bookings.view`. |
| `403` | `segment=obs` sin `bookings.passengers.medical.view`: filtrar por él es leer el dato por deducción. |
| `404` | Tour inexistente o de otro tenant (el scope global lo convierte en `404`). |
| `422` | `tour_date_id` que no pertenece al tour. |

---

## GET /api/v1/guide/tour-dates/{tourDate}/passengers

**Auth:** required · **Middleware:** `tenant_guide.only`
**Permission:** `guide.travelers.view` + pertenencia (`tourDate.guide_id === auth()->id()`)

Reemplaza a `GET /api/v1/guide/tour-dates/{tourDate}/travelers` (`routes/api.php:91`), que se
elimina: nadie lo consume desde el frontend.

### Query params

`segment`, `q`, `per_page`, `page` — idénticos al endpoint del panel.

`tour_date_id` y `status` **se ignoran**: la salida va en la ruta y los estados son fijos
(`confirmed` + `completed`). Enviarlos **no** produce `422` — se descartan en silencio. Ratificado
por producto el 2026-08-20 como el comportamiento correcto, no como una desviación: un cliente que
reutilice el mismo composable de la planilla del panel manda esos dos parámetros de más y no tiene
sentido romperle la pantalla por un dato que de todos modos no puede cambiar nada. La tolerancia
es solo de forma: hay test de que `?status[]=pending_payment` **no** le abre al guía ninguna
reserva que no le corresponda, y `?tour_date_id=<otra salida>` no cambia la planilla devuelta.

### Response 200

Mismo shape, con `meta.departures` de un solo elemento (la salida pedida). El rol `guide` tiene
`bookings.passengers.medical.view`, así que `can_view_medical` es `true`.

### Errores

| Código | Cuándo |
|---|---|
| `403` | `tourDate.guide_id !== auth()->id()`. |
| `404` | Salida de otro tenant. |

---

## GET /api/v1/guide/tours/{tour}

**Auth:** required · **Middleware:** `tenant_guide.only`
**Permission:** `tours.view` **no** aplica — el alcance es por **pertenencia**: el guía alcanza el
tour si tiene al menos una salida asignada en él.

Alimenta la página `Guide/TourShow.vue`: detalle de tour en modo lectura (Decisión 1).

### Response 200

```json
{
  "data": {
    "id": 31, "slug": "valle-de-cocora", "name": "Valle de Cocora",
    "summary": "…", "description": "…", "duration_hours": 10,
    "difficulty": "moderate", "category": { "id": 3, "name": "Naturaleza" },
    "cover_image_url": "…", "images": [ … ],
    "includes": [ … ], "excludes": [ … ], "requirements": [ … ],
    "itinerary": [ { "step_number": 1, "title": "…", "description": "…", "duration_label": "…" } ],
    "stops": [ /* igual que PublicTourResource.stops (PR #15) */ ],
    "meeting_point": "…", "meeting_latitude": 4.6371, "meeting_longitude": -75.4869,
    "my_departures": [
      { "id": 88, "starts_at": "…", "ends_at": "…", "capacity": 12,
        "booked_count": 9, "status": "open", "passengers_count": 9 }
    ]
  }
}
```

`my_departures` contiene **solo** las salidas donde `guide_id` es el propio guía. Las de otros
guías del mismo tour no se listan. No hay ningún campo de precio de costo, ni acciones.

### Errores

| Código | Cuándo |
|---|---|
| `403` | Tour de la misma agencia sin ninguna salida asignada al guía. |
| `404` | Tour de otro tenant. |

---

## GET /api/v1/admin/tours/{tour}/passengers/export
## GET /api/v1/guide/tour-dates/{tourDate}/passengers/export

Mismos auth, permisos y query params que sus `index`. Ignoran `per_page`/`page`: exportan todo el
resultado filtrado.

### Response 200

`text/csv; charset=UTF-8`, con BOM UTF-8 (Excel en Windows), streamed.
`Content-Disposition: attachment; filename="pasajeros-<slug-del-tour>-<fecha>.csv"`.

Columnas, en este orden, **con** `bookings.passengers.medical.view`:

```
Nombre completo,Tipo de documento,Documento,Email,Teléfono,
Contacto de emergencia,Parentesco,Teléfono de emergencia,
EPS,Observaciones,Salida,Reserva,Valor,Abonado,Saldo,Estado
```

**Sin** el permiso, `EPS` y `Observaciones` **no se emiten** — ni el encabezado ni la celda. Una
columna vacía invita a preguntar qué falta.

---

## GET /api/v1/admin/guides/availability

**Auth:** required · **Middleware:** `tenant_admin.only`, `can:dashboard.view`
**Permission:** `departures.assign_guide`

Alimenta el select de guía con ocupación (Decisión 3). Devuelve, por guía del tenant, los días
calendario ocupados en el rango y por qué tour.

### Query params

| Param | Tipo | Reglas |
|---|---|---|
| `from` | date | required |
| `to` | date | required, `after_or_equal:from`, máximo 180 días de rango |
| `exclude_tour_date_id` | int | nullable. La salida que se está editando, para que no cuente como su propio conflicto. |

### Response 200

```json
{
  "data": [
    {
      "id": 5, "name": "Camilo Ruiz",
      "busy": [
        { "tour_date_id": 88, "tour_name": "Valle de Cocora",
          "from": "2026-09-12", "to": "2026-09-14", "status": "open" }
      ]
    }
  ]
}
```

Ocupan las salidas en estado `open`, `full` y `closed`; `status` viaja con cualquiera de los tres.
Solo una `cancelled` **no** ocupa: libera sus días. Agotada es vendida entera, no suspendida.
El rango de cada bloque es `[date(starts_at) … date(ends_at)]` — días calendario completos.

---

## POST /api/v1/admin/tours/{tour}/dates · PUT /api/v1/admin/tour-dates/{tourDate} (existentes — se endurecen)

Cambios de contrato de las Decisiones 2 y 3:

| Campo | Antes | Ahora |
|---|---|---|
| `guide_id` | `nullable`, `exists:users,id` | **`required`**, `exists:users,id`, con rol `guide` del tenant, y regla `GuideIsAvailable` |
| `ends_at` | aceptado del cliente (`StoreTourDateRequest:29`), sin contrastar con la duración | **`prohibited`**: se deriva en el servidor de `starts_at + tours.duration_hours` |

### Errores nuevos

| Código | Cuándo |
|---|---|
| `422` | `guide_id` ausente. No existe «Sin asignar». |
| `422` | `guide_id` ocupado en alguno de los días calendario de la salida. El mensaje nombra el rango y el tour: «Ocupado 12–14 sep · Valle de Cocora». |
| `422` | `ends_at` enviado por el cliente. |

## PATCH /api/v1/admin/tour-dates/{tourDate}/guide (existente — se endurece)

`routes/api.php:119`, `AssignGuideController` → `App\Actions\Team\AssignGuideAction`, que hoy hace
`update(['guide_id' => …])` **sin comprobar nada**. Pasa a correr la misma regla
`GuideIsAvailable`. Es el tercer camino: sin esto, la regla se salta por aquí.

## PUT /api/v1/admin/tours/{tour} (existente — aviso nuevo)

Cambiar `duration_hours` alarga retroactivamente `ends_at` de todas las salidas del tour y puede
crear solapes que antes no existían. La respuesta de validación lista las salidas que quedarían en
conflicto antes de guardar (`422` con el detalle, o confirmación explícita desde la UI).

Al publicar (`status = active`), `default_guide_id` es **obligatorio**.

---

## POST /api/v1/admin/bookings/{bookingNumber}/passengers

**Auth:** required · **Permission:** `bookings.update`

Agrega un pasajero a una reserva existente. **No** crea reservas (ver Out of scope de la spec).
**Solo panel**: el guía no escribe.

### Request

```json
{
  "full_name": "María Fernanda Ríos",
  "is_minor": false,
  "document_type": "cc",
  "document_number": "1017234567",
  "birth_date": "1994-03-11",
  "email": "maria@example.com",
  "phone": "+57 300 111 2233",
  "emergency_contact_name": "Julián Ríos",
  "emergency_contact_relationship": "Hermano",
  "emergency_contact_phone": "+57 311 222 3344",
  "eps": "other",
  "eps_other": "Compensar",
  "medical_notes": "Alergia a la penicilina."
}
```

**Validación:**

| Campo | Tipo | Reglas |
|---|---|---|
| `full_name` | string | required, max:255 |
| `is_minor` | bool | required |
| `document_type` | string | nullable, `Enum(DocumentType)` |
| `document_number` | string | nullable, max:40, `required_with:document_type` |
| `birth_date` | date | nullable, `before:today` |
| `email` | string | nullable, email, max:255 |
| `phone` | string | nullable, max:40 |
| `emergency_contact_name` | string | nullable, max:255 |
| `emergency_contact_relationship` | string | nullable, max:60 |
| `emergency_contact_phone` | string | nullable, max:40, `required_with:emergency_contact_name` |
| `eps` | string | nullable, `Enum(Eps)`. **Se descarta sin el permiso médico.** |
| `eps_other` | string | nullable, max:120, **`required_if:eps,other`**; se persiste `null` si `eps ≠ other`. **Se descarta sin el permiso médico.** |
| `medical_notes` | string | nullable, max:2000. **Se descarta sin el permiso médico.** |

**Descartar, no rechazar.** El Form Request elimina los tres campos sensibles del payload cuando el
actor no tiene `bookings.passengers.medical.view`; no devuelve `403`. `sales` tiene
`bookings.update` y, sin ese filtro, editaría a ciegas lo que no puede leer y borraría la alergia
que el guía necesita. Rechazar la petición entera rompería su caso legítimo (corregir un teléfono).

### Response 201

`{ "data": { /* PassengerResource */ } }`

### Errores

| Código | Cuándo |
|---|---|
| `409` | La reserva está `cancelled`, `expired` o `refunded`. |
| `409` | Ya hay `travelers_count` pasajeros cargados en la reserva. |
| `422` | `eps_other` faltante con `eps = other`; email inválido. |

---

## PUT /api/v1/admin/passengers/{traveler}

**Auth:** required · **Permission:** `bookings.update`

Mismo cuerpo, misma validación y **la misma máscara de escritura** que el `POST`. Todos los campos
opcionales salvo `full_name`.

### Response 200

`{ "data": { /* PassengerResource */ } }`

### Errores

`404` si el `traveler` es de otro tenant · `409` si su reserva está cancelada/expirada.

---

## POST /api/v1/admin/bookings/{bookingNumber}/payments

**Auth:** required · **Permission:** `bookings.update`

Registro de un pago recibido fuera de pasarela (efectivo, transferencia). Es el «Registrar pago»
del drawer.

### Request

```json
{ "amount": "60000.00", "reference": "Transferencia Bancolombia 4412", "paid_at": "2026-09-01" }
```

| Campo | Tipo | Reglas |
|---|---|---|
| `amount` | decimal | required, `gt:0`, `≤ booking.total_amount - booking.paid_amount` |
| `reference` | string | nullable, max:180 |
| `paid_at` | date | nullable, `before_or_equal:today`. Default hoy. |

### Response 201

```json
{ "data": { "booking_number": "9f1c…", "total_amount": "540000.00",
            "paid_amount": "540000.00", "due_amount": "0.00", "status": "confirmed" } }
```

### Errores

| Código | Cuándo |
|---|---|
| `409` | Reserva cancelada, expirada o reembolsada. |
| `422` | `amount` mayor al saldo pendiente. |

### Decisiones cerradas el 2026-08-20 (no son pendientes)

- **`reference` se persiste dentro de `payments.gateway_response` como `{"reference": …}`.** No hay
  columna propia y **no se abre migración** por ella: es un texto libre que hoy nadie consulta ni
  busca. Producto decidió integrar una pasarela de pagos más adelante; ese trabajo revisa el modelo
  de `payments` completo y es el momento de decidir si la referencia merece columna e índice.
- **El pago manual no notifica.** `ProcessPaymentAction` manda `BookingConfirmedNotification` al
  completar un pago de pasarela; el pago de mostrador lo registra alguien que ya está frente al
  cliente, y un correo de «pago recibido» a quien acaba de entregar el efectivo no aporta.
  Se revisa junto con la pasarela, no antes.

---

## Shape compartido: `BookingResource` (zona del viajero)

Lo devuelven `POST /api/v1/bookings`, `GET /api/v1/bookings/{bookingNumber}` y
`PUT /api/v1/bookings/{bookingNumber}/travelers`
(`app/Http/Resources/Booking/BookingResource.php`). No estaba documentado aquí; se escribe con el
shape **real de hoy**, incluidos los dos campos de D10.

```json
{
  "data": {
    "booking_number": "9f1c…",
    "status": "confirmed",
    "travelers_count": 3,
    "adults_count": 2,
    "minors_count": 1,
    "subtotal": "540000.00",
    "discount_amount": "0.00",
    "total_amount": "540000.00",
    "paid_amount": "540000.00",
    "currency": "COP",
    "special_requests": null,
    "contact_snapshot": { "full_name": "…", "email": "…", "phone": "…" },
    "can_edit_travelers": true,
    "travelers_edit_deadline": "2026-09-13T06:00:00-05:00",
    "expires_at": null,
    "confirmed_at": "2026-08-20T10:12:33-05:00",
    "tour": { "id": 31, "slug": "valle-de-cocora", "name": "Valle de Cocora" },
    "tour_date": { "id": 88, "starts_at": "2026-09-14T06:00:00-05:00", "ends_at": "…" },
    "promotion": { "id": 7, "code": "COCORA10" },
    "travelers": [ /* BookingTravelerResource[] */ ],
    "created_at": "2026-08-19T21:40:02-05:00"
  }
}
```

| Campo | Notas |
|---|---|
| `tour` · `tour_date` · `promotion` · `travelers` | `whenLoaded`: **ausentes** si la relación no se cargó. `promotion` es `null` cuando la reserva no tiene ninguna. |
| `contact_snapshot` | JSON tal cual se guardó al crear la reserva. |
| `can_edit_travelers` | **Nuevo (D10).** `boolean`. `true` solo si la reserva no está bloqueada (`cancelled` / `expired` / `refunded`) **y** la ventana de edición del viajero sigue abierta. |
| `travelers_edit_deadline` | **Nuevo (D10).** `string` ISO8601 o `null`. Instante hasta el que el titular puede editar a sus acompañantes: `tour_date.starts_at − passengers.traveler_edit_cutoff_hours`. `null` cuando la reserva no tiene salida resuelta o la salida no tiene `starts_at`: en ese caso **no bloquea**. |

`travelers[]` es `BookingTravelerResource`: `id`, `full_name`, `is_minor`, `email`, `phone`,
`document_type`, `document_number`, `birth_date`, y **solo para el dueño de la reserva**
(`booking.user_id === auth()->id()`) el bloque `emergency_contact_name`,
`emergency_contact_relationship`, `emergency_contact_phone`, `eps`, `eps_label`, `eps_other`,
`medical_notes` y `dietary_restrictions`.

### GET /api/v1/bookings/{bookingNumber}

**Auth:** required · Alcance por **pertenencia**: `bookings.user_id === auth()->id()`
(`routes/api.php:79`). Carga `tour`, `tourDate`, `travelers` y `promotion`.

| Código | Cuándo |
|---|---|
| `404` | `BOOKING_NOT_FOUND` — inexistente, de otro tenant o de otro usuario. No se distingue: decir «existe pero no es tuya» ya filtra información. |

### Ventana de edición del viajero (D10)

El titular edita a sus acompañantes hasta **24 h antes** de `tour_dates.starts_at`; pasada esa
hora la planilla se congela **solo para él**. Configurable en
`config/montree.php` → `passengers.traveler_edit_cutoff_hours`
(env `MONTREE_TRAVELER_EDIT_CUTOFF_HOURS`, default `24`).

**El panel de la agencia no se ve afectado.** `PUT /api/v1/admin/passengers/{traveler}`,
`POST /api/v1/admin/bookings/{bookingNumber}/passengers` y
`POST /api/v1/admin/bookings/{bookingNumber}/payments` mantienen su contrato **sin cambios**: el
cambio de última hora se resuelve por la agencia. Razón de negocio: el guía descarga o imprime la
planilla el día anterior; si el dato cambia después, el papel miente y el contacto de emergencia
impreso deja de servir.

Casos borde del deadline: sin salida o sin `starts_at` ⇒ `null` ⇒ **no** bloquea. Salida ya
iniciada o pasada ⇒ bloqueada (la cubre la misma comparación `now() >= starts_at − cutoff`).

---

## PUT /api/v1/bookings/{bookingNumber}/travelers (existente — se amplía)

Endpoint del viajero (`BookingController@syncTravelers`, `routes/api.php:73`,
`app/Http/Requests/Booking/SyncBookingTravelersRequest.php`). `email`, `phone`,
`dietary_restrictions` y `medical_notes` **ya están validados y ya se guardan**
(`SyncBookingTravelersRequest:40`, `SyncBookingTravelersAction:40`): lo que falta es el input en
pantalla.

Se le agregan al item de la lista los campos `emergency_contact_name`,
`emergency_contact_relationship`, `emergency_contact_phone`, `eps` y `eps_other`, con **las mismas
reglas de validación** de la tabla de arriba. El viajero es dueño de sus datos: aquí **no** aplica
la máscara del permiso médico.

El resto del contrato (upsert por `id`, límites de `adults_count`/`minors_count`, `404` ajeno,
`409` cancelada) no cambia.

### Response 200

`{ "data": { /* BookingResource, ver arriba */ } }` — incluye `can_edit_travelers` y
`travelers_edit_deadline`.

### Errores

| Código | `error_code` | Cuándo |
|---|---|---|
| `404` | `BOOKING_NOT_FOUND` | Reserva inexistente o de otro usuario. |
| `409` | `BOOKING_TRAVELERS_LOCKED` | Reserva `cancelled`, `expired` o `refunded`. |
| `409` | `BOOKING_TRAVELER_EDIT_WINDOW_CLOSED` | **Nuevo (D10).** Se pasó la ventana de edición del titular. El mensaje nombra el deadline y remite a la agencia. |
| `422` | — | Validación del payload (`eps_other` faltante con `eps = other`, etc.). |

Cuerpo del error: `{ "message": "…", "error_code": "…" }` (`BookingException::toResponse()`).

**Por qué `409` y no `422`:** es una regla de **estado**, no un problema del payload — la misma
familia que el `BOOKING_TRAVELERS_LOCKED` que ya emite este camino. El payload puede ser
impecable y aun así llegar tarde.

**Orden de las guardas:** primero `isLocked()` (reserva cancelada/expirada/reembolsada), después la
ventana. Una reserva cancelada devuelve `BOOKING_TRAVELERS_LOCKED` aunque también esté fuera de
ventana.

---

## Cifras del listado de tours del panel

Dos entregas distintas para la misma pantalla (`Admin/Tour/Index`): los KPIs del encabezado viajan
como **prop de Inertia** en `GET /admin/tours`, y las cifras por tarjeta como **campo del recurso**
en `GET /api/v1/admin/tours`, que es de donde el listado se refiltra.

### Prop `stats` de `GET /admin/tours` (`TourIndexStats`)

```json
{
  "tours": { "active": 12, "draft": 3, "paused": 1, "archived": 4 },
  "upcoming_departures": { "count": 7, "next_starts_at": "2026-08-24T08:00:00+00:00" },
  "occupancy": { "booked_seats": 48, "total_capacity": 120, "rate": 40 },
  "pending_balance": { "passengers": 9, "amount": "3250000.00", "currency": "COP" }
}
```

| Bloque | Cómo se calcula |
|---|---|
| `tours` | Conteo por estado de los tours **no borrados** del tenant. Los cuatro estados de `TourStatus` siempre viajan, en cero si no hay ninguno. |
| `upcoming_departures.count` | Salidas del tenant en `open` o `full` con `starts_at` en los **próximos 30 días**. |
| `upcoming_departures.next_starts_at` | `MIN(starts_at)` de **todas** las salidas futuras en `open` o `full`, sin recortar a 30 días: si la más cercana cae fuera de la ventana, la fecha sigue siendo cierta. `null` sin salidas futuras. ISO 8601. |
| `occupancy` | `SUM(booked_count)` y `SUM(capacity)` de las salidas de la **misma ventana de 30 días**. `rate` es el porcentaje entero; `0` cuando no hay cupo declarado (no se divide por cero). |
| `pending_balance` | Reservas `confirmed` o `completed` con `paid_amount < total_amount`: `passengers` es `SUM(travelers_count)` y `amount` es `SUM(total_amount - paid_amount)` con dos decimales y punto. `currency` es la de `tenant_configurations`. |

**`pending_balance` es dinero de pasajeros y tiene permiso propio: `bookings.view`.** Sin él la
clave **no viaja** — no viaja en cero, que se leería como «no se debe nada». Es el caso de
`operator`, que tiene `tours.view` y entra al listado. El chequeo es el mismo que abre la planilla
(`BookingPolicy@viewAny`). En el tipo de TypeScript el campo es opcional y el KPI de saldo
desaparece del encabezado cuando falta.

El criterio de salida futura (`open` o `full`, `starts_at > now`) y el de dinero cobrado son los de
`BuildTourShowStatsAction`: el detalle y el listado no cuentan la misma cosa de dos maneras.

### Campo `operations` de `TourSummaryResource` (`TourOperationalSummary`)

```json
{
  "next_departure_at": "2026-08-24T08:00:00+00:00",
  "passengers_count": 5,
  "occupancy": { "occupied": 5, "capacity": 12 },
  "passengers_with_due": 2
}
```

Todo se refiere a **la próxima salida** del tour: la primera en `open` o `full` con
`starts_at > now`.

| Campo | Cómo se calcula |
|---|---|
| `next_departure_at` | `starts_at` de esa salida, ISO 8601. `null` si el tour no tiene ninguna próxima. |
| `passengers_count` | `SUM(travelers_count)` de las reservas `confirmed` o `completed` de esa salida — los mismos estados por defecto de la planilla. |
| `occupancy.occupied` / `occupancy.capacity` | `booked_count` y `capacity` de esa salida: cupos tomados, no personas cargadas. Por eso puede diferir de `passengers_count` (una reserva `pending_payment` retiene cupo y no suma pasajeros). |
| `passengers_with_due` | Viajeros de esa salida cuya **reserva** aún debe (`paid_amount < total_amount`). El saldo es de la reserva, no de la persona (D5). |

**`operations` viaja solo cuando la consulta adjuntó los agregados** de
`App\Queries\TourOperationalSummaryQuery` — hoy, el índice del panel. El mismo Resource sirve al
selector de tours de promociones, y ahí el campo **no existe**: sin cifras que mostrar, ausencia
antes que ceros. Es aditivo; nada de lo que ya emitía cambia.

**Sin N+1:** las cuatro cifras son subconsultas correlacionadas del `select`, no relaciones
cargadas por fila. `TourIndexQueryCountTest` compara el mismo listado con 3 y con 30 tours y exige
el **mismo** número de consultas.

### Órdenes de `GET /api/v1/admin/tours`

`sort` acepta, además de `created_at`, `name`, `base_price` y `status`:

| `sort` | Ordena por |
|---|---|
| `next_departure` | `starts_at` de la próxima salida. Un tour sin salidas próximas ordena como `NULL`. |
| `occupancy` | `booked_count / capacity` de la próxima salida; capacidad 0 cuenta como vacía. |
| `revenue` | Suma de los pagos `completed` de las reservas del tour. |

`direction` sigue siendo `asc` o `desc` (por defecto `desc`), y un `sort` desconocido sigue cayendo
en `created_at` en vez de responder `422`.

---

## Rutas Inertia

| Ruta | Página | Middleware / permiso |
|---|---|---|
| `GET /admin/tours` | `Admin/Tour/Index` | `tenant_admin.only`, `can:dashboard.view`, `can:tours.view` |
| `GET /admin/tours/create` | `Admin/Tour/Create` | `can:tours.create` |
| `GET /admin/tours/{tour}/edit` | `Admin/Tour/Edit` | `can:tours.update` |
| `GET /admin/tours/{tour}` | `Admin/Tour/Show` | `can:tours.view` |
| `GET /guide/tours/{tour}` | `Guide/TourShow` | `tenant_guide.only` + pertenencia |
| `GET /guide/tour-dates/{tourDate}/passengers` | `Guide/Passengers` | `tenant_guide.only`, `can:guide.travelers.view` + pertenencia |

La pestaña «Pasajeros» de `Admin/Tour/Show` se muestra solo si `auth.permissions` incluye
`bookings.view`; sin ella la página responde 200 con las pestañas restantes. Regla de oro del
menú (F018): si el item aparece, la ruta responde 200.

El guía **no** entra a `/admin/*`: `routes/web.php:79` lo cierra con `tenant_admin.only` +
`can:dashboard.view`. El handoff pide «el mismo componente para guía y administrador» y se cumple
a nivel de **componente**, no de ruta.

---

## Changelog

- `2026-08-20` — **Cifras del listado de tours del panel.** Se documentan por primera vez la prop
  `stats` de `GET /admin/tours` (`TourIndexStats`) y el campo `operations` de
  `TourSummaryResource` (`TourOperationalSummary`), más los tres órdenes nuevos
  (`next_departure`, `occupancy`, `revenue`). **Aditivo**: nada de lo que el listado ya emitía
  cambia. `pending_balance` queda sujeto a `bookings.view` y se **omite** sin el permiso, con el
  mismo criterio de la planilla; en TypeScript el campo pasa a opcional.
- `2026-08-20` — Redacción inicial de los contratos del feature.
- `2026-08-20` — **D10, ventana de edición del viajero.** Se documenta por primera vez el shape de
  `BookingResource` y el endpoint `GET /api/v1/bookings/{bookingNumber}`, con los dos campos nuevos
  `can_edit_travelers` (bool) y `travelers_edit_deadline` (ISO8601 o `null`), y se agrega a
  `PUT /api/v1/bookings/{bookingNumber}/travelers` el error
  `409 BOOKING_TRAVELER_EDIT_WINDOW_CLOSED` con su tabla de errores completa. Razón: la regla ya
  está implementada y el contrato no la describía; los dos campos son la única forma que tiene el
  frontend de no llevar al usuario a un `409`. **Breaking para el frontend de la zona del viajero**
  (contrato ampliado, no roto: los campos son aditivos y el `409` es un camino de error nuevo).
  **Sin cambio** para los endpoints del panel (`/api/v1/admin/*`): el administrador no queda
  sujeto a la ventana, a propósito.
- `2026-08-20` — **Fila de marcador de posición: `payment` se emite, y esa es LA regla.** Antes el
  contrato decía «el resto de campos en `null`» y la implementación se había anotado como
  desviación. Ratificado por producto: solo van en `null` los campos de la persona; sin `payment`,
  el «Total por cobrar» del pie mentiría.
- `2026-08-20` — **La zona del guía ignora `tour_date_id` y `status` en vez de devolver `422`.**
  Antes el contrato decía «no acepta». Ratificado por producto como comportamiento correcto:
  tolerancia de parámetros extra, con test de que ignorarlos no le abre al guía nada que no le
  corresponda.
- `2026-08-20` — **Pago manual: cerrado como está.** La `reference` sigue dentro de
  `gateway_response` y el registro no notifica. Se revisa cuando entre la pasarela de pagos; deja
  de figurar como pendiente abierto.
- `2026-08-20` — **Fase 5 implementada. Tres precisiones sobre lo ya escrito.** (a)
  `PATCH /api/v1/admin/tour-dates/{tourDate}/guide` no solo estrena la regla de disponibilidad:
  también valida que el `guide_id` sea un miembro **activo con rol `guide` del tenant**. Antes
  aceptaba cualquier `users.id` existente, incluido el de otro tenant — un agujero que el contrato
  no mencionaba porque nadie lo había mirado. (b) Publicar sin `default_guide_id` responde
  `422` con `error_code` `TOUR_NEEDS_GUIDE_TO_ACTIVATE` en la raíz, igual que
  `TOUR_NEEDS_IMAGE_TO_ACTIVATE`, y **no** como error de validación por campo: el endpoint de
  estado tiene su propio formato de error. (c) `default_guide_id` pasa a ser campo de entrada de
  `POST/PUT /api/v1/admin/tours` (nullable, con la misma comprobación de guía del tenant) y sale
  en `TourResource`. Sin eso la regla de publicación no se podía satisfacer: la columna existía
  desde la Fase 1 y ninguna entrada la escribía. **Aditivo** para el frontend.
- `2026-08-20` — **El aviso de `duration_hours` solo mira salidas futuras.** El contrato decía «las
  salidas del tour»; la implementación descarta las que ya terminaron. Alargar la duración de un
  tour no puede quedar bloqueada por un solape en salidas que ya ocurrieron.
- `2026-08-20` — `GET /api/v1/admin/guides/availability`: los bloques ocupados incluyen las
  salidas en estado `full`, no solo `open` y `closed`. Solo `cancelled` libera los días.
