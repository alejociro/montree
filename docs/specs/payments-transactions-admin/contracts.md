# Transacciones en el panel — Contratos

> Shapes exactos. Es CONTRATO: backend y frontend se basan en este archivo.
> **Todo es Inertia sobre rutas web.** No hay endpoints JSON: lo que aquí se
> describe como «props» es lo que recibe la page Vue en `defineProps`.
> Los filtros viajan en la query string y vuelven en `filters` para repintar
> los controles tras una recarga.

---

## GET /admin/transactions → page `Admin/Transactions/Index`

**Auth:** admin del tenant. **Permiso:** `payments.view`.

### Query string

**No hay búsqueda libre sobre todas las columnas.** Se elige un campo y se busca
solo ahí, con igualdad exacta. Es el criterio de microsites, donde `Payment` no
declara columnas buscables justamente para no habilitar el barrido: comparar por
`=` usa los índices, `LIKE %…%` obliga a escanear.

| Parámetro | Tipo | Reglas |
|---|---|---|
| `search_by` | string | nullable, in: `reference`, `request_id`, `internal_reference`, `authorization`, `receipt`, `booking_number`, `payer` |
| `search` | string | nullable, max:64, `required_with:search_by` |
| `status` | string | nullable, in: `pending`, `processing`, `completed`, `failed`, `refunded` |
| `gateway` | string | nullable, in: `placetopay`, `manual` |
| `tour_date_id` | int | nullable, la salida debe ser del tenant |
| `from` | date | nullable, `Y-m-d` |
| `to` | date | nullable, `Y-m-d`, `after_or_equal:from`, no futura |
| `page` | int | nullable, min:1 |

Dos reglas del rango de fechas, ambas con test:

1. **Ventana por defecto de 30 días.** Sin `from` ni `to`, el listado muestra los
   últimos 30 días. Entrar al módulo no puede significar barrer la tabla entera.
   El valor viaja de vuelta en `filters.from` para que el control lo muestre.
2. **Un identificador exacto ignora el rango.** Si `search_by` es cualquiera
   menos `payer`, las fechas no se aplican y `filters.from`/`filters.to` vuelven
   en `null`. Quien pega un `requestId` quiere ese pago, no «ese pago si cae en
   el rango»; el pago que se está reclamando suele ser viejo.

`payer` es el único campo que compara parcial (`LIKE`), porque nadie recuerda
cómo se escribió exacto un nombre. Busca en el contacto de la reserva y en el
nombre del usuario. Es también el único sin índice: acotado por el rango de
fechas, que en su caso sí se aplica.

`from`/`to` filtran por `processed_at` cuando el pago está resuelto y por
`created_at` cuando no, para que un pago colgado no desaparezca del rango.

### Props

```json
{
  "transactions": {
    "data": [
      {
        "id": 17,
        "reference": "MTR-17",
        "request_id": "4242",
        "gateway": "placetopay",
        "gateway_label": "PlacetoPay",
        "status": "completed",
        "gateway_status": "APPROVED",
        "status_message": "Aprobada",
        "amount": "450000.00",
        "currency": "COP",
        "type": "partial",
        "processed_at": "2026-09-12T10:04:00-05:00",
        "created_at": "2026-09-12T10:01:00-05:00",
        "booking": {
          "booking_number": "MT-0042",
          "holder_name": "Ana Pérez",
          "tour_name": "Valle de Cocora",
          "tour_date_id": 12,
          "starts_at": "2026-09-14T06:00:00-05:00"
        }
      }
    ],
    "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
    "meta": { "current_page": 1, "last_page": 3, "per_page": 25, "total": 64 }
  },
  "filters": {
    "search": null, "search_by": null, "status": null, "gateway": null,
    "tour_date_id": null, "from": "2026-08-13", "to": null
  },
  "statuses": [{ "value": "completed", "label": "Completado" }],
  "gateways": [{ "value": "placetopay", "label": "PlacetoPay" }],
  "search_fields": [{ "value": "request_id", "label": "requestId (pasarela)" }],
  "departures": [{ "value": 12, "label": "Valle de Cocora · 14 sep 2026" }],
  "default_days": 30,
  "can": { "query": true }
}
```

`statuses`, `gateways` y `search_fields` salen de los enums (`label()`), no se
escriben a mano en el front. `departures` trae solo las salidas que tienen algo
cobrado (máximo 100, más recientes primero): el desplegable sirve para conciliar,
no para listar el catálogo. `default_days` es la ventana por defecto, para que la
pantalla pueda explicarla. `can.query` decide si se pinta la acción de reconsulta.

### Orden y paginación

`processed_at DESC` con `created_at DESC` como desempate (un pago sin resolver no
tiene fecha de autorizador), `id DESC` al final para que el orden sea estable.
25 por página, `withQueryString()`.

---

## GET /admin/transactions/{payment} → page `Admin/Transactions/Show`

**Auth:** admin del tenant. **Permiso:** `payments.view`.
Una transacción de otra agencia responde **404**, no 403: el panel no confirma
que exista.

### Props

```json
{
  "transaction": {
    "id": 17,
    "reference": "MTR-17",
    "request_id": "4242",
    "internal_reference": "1234567",
    "gateway": "placetopay",
    "gateway_label": "PlacetoPay",
    "status": "completed",
    "status_label": "Completado",
    "gateway_status": "APPROVED",
    "status_message": "Aprobada",
    "amount": "450000.00",
    "currency": "COP",
    "type": "partial",
    "type_label": "Abono",
    "authorization": "000000",
    "receipt": "5551234",
    "franchise": "CR_VS",
    "payment_method": "visa",
    "payment_method_name": "Visa",
    "issuer_name": "BANCOLOMBIA",
    "last_digits": "4242",
    "is_queryable": true,
    "processor_fields": { "lastDigits": "4242" },
    "session_expires_at": null,
    "processed_at": "2026-09-12T10:04:00-05:00",
    "created_at": "2026-09-12T10:01:00-05:00",
    "booking": {
      "booking_number": "MT-0042",
      "holder_name": "Ana Pérez",
      "holder_email": "ana@example.com",
      "total_amount": "900000.00",
      "paid_amount": "450000.00",
      "due_amount": "450000.00",
      "status": "confirmed",
      "tour_name": "Valle de Cocora",
      "tour_date_id": 12,
      "starts_at": "2026-09-14T06:00:00-05:00"
    }
  },
  "can": { "query": true }
}
```

- `last_digits` sale de `processor_fields.lastDigits` cuando está; si no, `null`.
  **Nunca** se expone el BIN completo ni nada que se parezca a un número de tarjeta
  más allá de los últimos cuatro dígitos.
- `process_url` **no** viaja al front: es un enlace de cobro vivo.
- `gateway_response` tampoco: es el volcado crudo, puede traer datos del pagador.
  Si hace falta depurar, se lee del log o de la base.
- Un pago `manual` trae los campos de pasarela en `null` y la referencia que
  escribió quien lo registró en `reference`.

### Botón copiar

Van con acción de copiar: `reference`, `request_id`, `internal_reference`,
`authorization`, `receipt`.

---

## POST /admin/transactions/{payment}/query

**Auth:** admin del tenant. **Permiso:** `payments.query`.
Sin body. Responde **302** de vuelta al detalle con flash.

| Caso | flash | Mensaje |
|---|---|---|
| La consulta resolvió el pago | `success` | "La pasarela respondió: {estado}." |
| El pago ya estaba resuelto | `success` | "El pago ya estaba resuelto como {estado}." |
| Pago sin `request_id` o que no es de pasarela | `error` | "Este pago no se puede consultar en la pasarela." (422 si se pide JSON) |
| Pasarela caída | `error` | "No pudimos conectar con la pasarela. Intentá de nuevo." |
| Agencia sin credenciales | `error` | "La agencia no tiene pasarela configurada." |

Reusa `ResolvePaymentAction`, que ya es idempotente (`lockForUpdate` + corte por
`isResolved()`). Si la resolución confirma la reserva, se aplica la misma regla de
asiento que el retorno del comprador y se notifica igual: no hay un camino
distinto para el pago que se resuelve desde el panel.

---

## Planilla de pasajeros — cambios

### `GET /api/v1/guide/tour-dates/{tourDate}/passengers`

Cada pasajero suma, **solo si quien pide tiene `payments.view`**, el historial de
transacciones de su reserva:

```json
{
  "payments": [
    {
      "id": 17,
      "reference": "MTR-17",
      "gateway": "placetopay",
      "status": "completed",
      "amount": "450000.00",
      "processed_at": "2026-09-12T10:04:00-05:00"
    }
  ]
}
```

Se resuelve con `whenLoaded('payments')`: el controller carga la relación solo
cuando el permiso está, así el Resource no decide por rol (constitución §3.2).
Para el guía la clave no viaja y el drawer no pinta el bloque.

### Enlace sobre la planilla

«Ver transacciones de esta salida» → `/admin/transactions?tour_date_id={id}`.
Visible solo con `payments.view`. Se arma con Wayfinder, no a mano.

---

## Eventos / Side-effects

- El listado y el detalle no producen efectos.
- La reconsulta puede cambiar `payments.status` y, por la regla de asiento,
  `bookings.paid_amount`/`status`, y disparar `BookingConfirmedNotification` si la
  reserva acaba de confirmarse.

## Changelog

- `2026-09-12` — Creación.
- `2026-09-12` — Los filtros se rehacen siguiendo microsites: se retira la
  búsqueda libre sobre todas las columnas y entra `search_by` con igualdad
  exacta, más ventana por defecto de 30 días y el bypass del rango cuando se
  busca por identificador. Se suman las props `search_fields`, `departures` y
  `default_days`.
