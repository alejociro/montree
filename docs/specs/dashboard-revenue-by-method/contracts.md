# Ingresos por medio de pago — Contratos

> Shapes exactos. Backend y frontend se basan en este archivo.

---

## Enum `PaymentGateway`

```php
case PlaceToPay = 'placetopay';  // __('PlacetoPay')
case Cash       = 'cash';        // __('Efectivo')
case Transfer   = 'transfer';    // __('Transferencia')
```

`Manual` desaparece. Todo lo que hoy compara contra `manual` se actualiza:

- `Payment::isQueryable()` ya compara contra `PlaceToPay`: sin cambios.
- `Admin/Transactions/Show.vue`: `isManual` pasa a `gateway !== 'placetopay'`.
- `TransactionPagesController::options(PaymentGateway::cases())` toma los tres
  valores solo: el filtro del listado gana Efectivo y Transferencia sin tocarse.

Método nuevo, para no repetir la comparación:

```php
public function isGateway(): bool   // true solo para PlaceToPay
```

---

## GET /admin/dashboard → page `Admin/Dashboard`

**Auth:** admin del tenant. **Permiso:** `can:dashboard.view`.
Deja de ser `Route::inertia(...)`: pasa a `DashboardPagesController::__invoke`.

### Query string

| Parámetro | Tipo | Reglas |
|---|---|---|
| `period` | string | nullable, in: `last_7_days`, `last_30_days`, `last_90_days`, `this_month`, `last_month`, `this_year`. Default `last_30_days` |
| `tz` | string | nullable, zona horaria válida. Default: la del tenant |

Un `period` inválido responde **302 de vuelta con los errores en sesión**, no
422: es una ruta web con Inertia y ese es el manejo estándar de Laravel. El 422
es de los endpoints JSON. Ausente no es inválido: cae al default.

### Props

Las mismas que devolvía el endpoint de API, más `revenue.by_method`, y con dos
props nuevas de página:

```json
{
  "snapshot": {
    "period": { "key": "last_30_days", "start": "…", "end": "…" },
    "revenue": {
      "gross": "4200000.00",
      "net": "4100000.00",
      "previous_gross": "3800000.00",
      "growth_pct": 10.5,
      "currency": "COP",
      "series": [{ "date": "2026-09-01", "amount": "120000.00" }],
      "by_method": [
        { "method": "placetopay", "label": "PlacetoPay",    "amount": "3100000.00", "share_pct": 74 },
        { "method": "cash",       "label": "Efectivo",      "amount": "780000.00",  "share_pct": 19 },
        { "method": "transfer",   "label": "Transferencia", "amount": "320000.00",  "share_pct": 7 }
      ]
    },
    "bookings": { "…": "sin cambios" },
    "rating": { "…": "sin cambios" },
    "occupancy": { "…": "sin cambios" },
    "top_tours": [],
    "upcoming_dates": [],
    "pending_reviews_count": 0,
    "permissions": { "can_export_reports": true }
  },
  "periods": [{ "value": "last_30_days", "label": "Últimos 30 días" }],
  "filters": { "period": "last_30_days" }
}
```

Reglas de `by_method`:

- **Siempre trae los tres medios**, en el orden del enum, aunque alguno esté en
  cero. Un medio ausente sería indistinguible de un medio sin movimientos.
- `amount` es el bruto de ese medio: pagos `completed` y `refunded` por
  `processed_at` dentro del periodo. La suma de los tres iguala `gross`.
- `share_pct` es entero redondeado sobre `gross`. Con `gross` en `"0.00"` todos
  los `share_pct` son `0` y la page muestra el estado vacío.
- `label` sale de `PaymentGateway::label()`, no se escribe en el front.

`periods` sale de `PeriodFilter::SUPPORTED_KEYS` con su etiqueta: hoy el
selector las duplica a mano en el front y se desincroniza.

### Navegación del selector de periodo

`router.get(dashboard().url, { period }, { preserveState: true, replace: true, only: ['snapshot', 'filters'] })`.
**No** `useApi()` ni `useHttp()`: es una ruta web que devuelve Inertia.

---

## POST /api/v1/admin/bookings/{booking}/payments

Sigue en API: lo llama el drawer de la planilla con `useApi()`, y ese contrato no
cambia de capa. Suma **un campo obligatorio**:

| Campo | Tipo | Reglas |
|---|---|---|
| `method` | string | **required**, in: `cash`, `transfer` |
| `amount` | string numérico | required, numeric, gt:0, no mayor que `due_amount` (sin cambios) |
| `reference` | string | nullable, max:180 (sin cambios) |
| `paid_at` | date | nullable, before_or_equal:today (sin cambios) |

El pago se crea con `gateway` = el `method` elegido. La referencia sigue
guardándose en su columna y en `gateway_response`.

Error nuevo:

| Status | Caso | Mensaje |
|---|---|---|
| 422 | `method` ausente o inválido | "Elegí si el pago fue en efectivo o por transferencia." |

### UI del drawer

Selector de dos opciones antes del monto. Sin valor por defecto: obliga a elegir
y evita que todo quede marcado como efectivo por inercia. El campo de referencia
cambia de etiqueta según el medio: con transferencia sugiere el comprobante, con
efectivo queda como nota opcional.

---

## Eventos / Side-effects

Ninguno nuevo. El asiento del pago sigue pasando por
`BookingSettlementService`, que no mira el medio.

## Changelog

- `2026-09-13` — Creación.
