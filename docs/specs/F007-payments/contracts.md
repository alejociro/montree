# F007 — Contratos

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.
> Solo se listan los endpoints que F007 crea o cambia. El resto (booking
> create, manifiesto) vive en su propio feature.

---

## POST /bookings/{bookingNumber}/pay

**Auth:** required (customer dueño de la reserva)
**Tipo:** ruta web Inertia. Se llama con `router.post` desde `Booking/Show` y
`Booking/Create`. El servidor responde `Inertia::location(processUrl)`: el
navegador sale al checkout de PlacetoPay.

### Request

```json
{ "type": "partial", "amount": "150000.00" }
```

| Campo | Tipo | Reglas |
|---|---|---|
| `type` | string | required, in: `full`, `partial` |
| `amount` | string numérico | nullable, required_if type=partial, numeric, min: `booking.min_payment_amount`, max: `booking.due_amount` |

Con `type=full` el monto se ignora y se cobra `due_amount`.

### Response

- `409 Conflict` con header `X-Inertia-Location: <processUrl>` (es lo que
  produce `Inertia::location`).
- Errores de validación → `302` back con `errors.amount` (Inertia estándar).
- Reglas de negocio → `302` back con flash `error`:

| Caso | error_code | Mensaje |
|---|---|---|
| Reserva cancelada/expirada/reembolsada | `BOOKING_PAYMENTS_LOCKED` | "La reserva no admite pagos." |
| Saldo en cero | `PAYMENT_NOTHING_DUE` | "La reserva ya está pagada." |
| Comercio sin credenciales (ni propias ni de plataforma) | `PAYMENT_GATEWAY_NOT_CONFIGURED` | "La agencia no tiene pasarela configurada." |
| PlacetoPay rechazó la sesión | `PAYMENT_SESSION_REJECTED` | mensaje de la pasarela |
| PlacetoPay no responde (tras reintentos) | `PAYMENT_GATEWAY_UNAVAILABLE` | "No pudimos conectar con la pasarela. Intentá de nuevo." |

### Side-effects

- Crea `payments` con `status=processing`, `request_id`, `process_url`,
  `session_expires_at`, `reference = MTR-{id}`.
- Doble clic: si hay una sesión viva (`processing`, mismo monto,
  `session_expires_at > now`) se reutiliza su `process_url`.

---

## GET|POST /payments/{payment}/return

**Auth:** none. **Middleware:** `signed` (firma de Laravel sobre id + host).
Es la `returnUrl` que se envía a PlacetoPay.

### Comportamiento

1. Busca el pago en el tenant del host. Firma inválida → `403`. Pago
   inexistente → `404`.
2. Consulta la sesión (`query(requestId)`) y resuelve (idempotente).
3. Redirige a `booking.show` con flash:

| `payment.status` tras resolver | flash | Mensaje |
|---|---|---|
| `completed` | `success` | "¡Pago aprobado! Tu reserva está confirmada." El saldo pendiente no va en el flash: `Booking/Show` muestra un banner permanente «Te queda un saldo de {due_amount}» mientras `due_amount > 0`. |
| `failed` | `error` | `status_message` de la pasarela |
| `processing` | `success` | "Estamos confirmando tu pago con el banco." |

---

## POST /payments/notification

**Auth:** none. Sin CSRF. Firma propia de PlacetoPay.

### Request (lo manda PlacetoPay)

```json
{
  "requestId": 4242,
  "reference": "MTR-17",
  "signature": "sha256:9f2c…",
  "status": { "status": "APPROVED", "message": "…", "reason": "00", "date": "2026-09-12T10:00:00-05:00" }
}
```

| Campo | Reglas |
|---|---|
| `requestId` | required |
| `reference` | required, string |
| `signature` | required, string. Formato `hash` (sha1 implícito) o `algo:hash` con algo ∈ sha1, sha256, sha512 |
| `status.status`, `status.date` | required, string |

Firma esperada: `hash(algo, requestId . status . date . tranKey)` con el
`tranKey` del tenant dueño del pago (resuelto por `request_id` +
`reference`, sin depender del host).

### Response

- `200 { "message": "Notification queued." }` → se encola `ResolvePaymentJob`.
  **Nunca** se confía en el `status` del body: el job re-consulta la sesión.
- `422 { "message": "Invalid notification." }` si el pago no existe, la
  `reference` no coincide o la firma no cuadra. Se loguea `warning`.

---

## Comando `payment:check`

```
php artisan payment:check [--date-from=Y-m-d] [--date-to=Y-m-d]
```

Ventana por defecto: creados entre `now - P2P_CHECK_LOOKBACK_HOURS` (72) y
`now - P2P_CHECK_MARGIN_MINUTES` (15). Toma pagos `placetopay` con
`request_id` y `status ∈ {pending, processing}` de **todos** los tenants, y
encola un `ResolvePaymentJob` por cada uno. Programado cada 10 minutos con
`withoutOverlapping()`.

Salida: `N/M pagos encolados.` o `No hay pagos pendientes de resolver.`

---

## POST /api/v1/admin/tours/{tour}/dates · PUT /api/v1/admin/tour-dates/{tourDate}

Cambio: se agrega un campo. El resto del contrato está en `tours-admin`.

| Campo | Tipo | Reglas |
|---|---|---|
| `min_payment_pct` | int \| null | nullable, integer, min:1, max:100 |

`null` (o ausente en update) limpia el override: la salida vuelve al
porcentaje de la agencia.

### Response (fragmento de `TourDateDetailResource`)

```json
{
  "id": 12,
  "price_override": null,
  "effective_price": "450000.00",
  "min_payment_pct": 50,
  "effective_min_payment_pct": 50
}
```

`effective_min_payment_pct = min_payment_pct ?? tenant.min_partial_payment_pct`.

---

## GET /bookings/{bookingNumber} (page `Booking/Show`)

Cambio en los props de `booking`:

```json
{
  "total_amount": "900000.00",
  "paid_amount": "0.00",
  "due_amount": "900000.00",
  "min_payment_amount": "450000.00",
  "min_payment_pct": 50
}
```

- Se renombra `min_partial_payment_pct` → `min_payment_pct` y pasa a ser el
  porcentaje **efectivo de la salida**.
- `min_payment_amount = min(total_amount × min_payment_pct / 100, due_amount)`.
  El front no recalcula nada: usa estos dos valores para el selector.

---

## POST /api/v1/admin/bookings/{booking}/payments (pago en efectivo)

Sin cambios de shape (ver `docs/specs/tours-admin-passengers/contracts.md`).
Cambia el **resultado**: `status` en `BookingBalanceResource` vuelve
`confirmed` cuando `paid_amount` alcanza el mínimo de la salida, no solo el
total.

```json
{ "data": { "booking_number": "MT-…", "total_amount": "900000.00", "paid_amount": "450000.00", "due_amount": "450000.00", "status": "confirmed" } }
```

---

## Eliminado

- `POST /api/v1/admin/payments/{payment}/refund` y el permiso
  `payments.refund`. Ver `plan.md` §9.

---

## Regla de asiento (`BookingSettlementService`)

Aplica a pasarela y efectivo. Dada una reserva bloqueada y un pago
`completed`:

| Condición tras sumar el pago | `status` | `expires_at` | `confirmed_at` |
|---|---|---|---|
| `paid >= total_amount` | `pending_payment → confirmed`; otros no cambian | `null` | se fija si estaba vacío |
| `paid >= total × pct/100` (pct efectivo de la salida) | igual que arriba | `null` | igual |
| ninguna | no cambia | no cambia | no cambia |

`BookingConfirmedNotification` se envía solo cuando el estado **acaba de**
pasar a `confirmed` y el pago vino por pasarela.

---

## Eventos / Side-effects

- Al resolver un pago `completed` se aplica la regla de asiento y, si la
  reserva acaba de confirmarse, se notifica al viajero.
- Un pago `failed` no toca la reserva; el `expires_at` del hold sigue
  corriendo.

## Changelog

- `2026-09-12` — Creación.
