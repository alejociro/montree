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
`"id": null`, `full_name` = nombre del titular de la reserva y el resto de campos en `null`
(edge case de la spec). El frontend lo pinta como «Datos pendientes».

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

`segment`, `q`, `per_page`, `page` — idénticos al endpoint del panel. **No** acepta
`tour_date_id` (la salida va en la ruta) ni `status` (fijo en `confirmed` + `completed`).

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

Ocupan las salidas en estado `open` y `closed`. Una `cancelled` **no** ocupa: libera sus días.
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
