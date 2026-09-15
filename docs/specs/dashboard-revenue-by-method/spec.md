# Ingresos por medio de pago — Desglose en el dashboard

## Descripción

Tres cambios que van juntos porque dependen del mismo dato:

1. El medio de pago deja de ser «pasarela o manual» y pasa a distinguir
   **efectivo** de **transferencia**.
2. El dashboard de la agencia muestra, además del total, **de dónde vino la
   plata**: pasarela, efectivo y transferencia, con monto y porcentaje.
3. El dashboard deja de pedir sus datos por API y pasa a **Inertia con props**,
   como el resto del panel nuevo.

Hoy no hay forma de saber si un pago manual entró en efectivo o por
transferencia: la referencia es texto libre que nadie interpreta. Sin ese dato
el desglose es imposible.

## User stories

- Como admin, quiero ver cuánto cobré por pasarela, en efectivo y por
  transferencia en el periodo, para cuadrar la caja contra el banco.
- Como admin, quiero saber qué porcentaje del ingreso entra por cada medio.
- Como guía o admin, quiero elegir si el pago que registro fue en efectivo o por
  transferencia, en vez de escribirlo en un campo libre.
- Como admin, quiero filtrar las transacciones por efectivo o por transferencia.

## Acceptance criteria

- **Given** pagos cobrados por los tres medios en el periodo, **when** se abre el
  dashboard, **then** el bloque «De dónde vino» muestra una fila por medio con
  monto y porcentaje, y la suma de los montos iguala el ingreso bruto.
- **Given** un medio sin movimientos en el periodo, **then** su fila igual
  aparece, en cero, para que el cero se distinga del dato ausente.
- **Given** el periodo sin ingresos, **then** el bloque muestra un estado vacío,
  no tres filas en cero con porcentajes indefinidos.
- **Given** el registro de un pago manual, **when** se abre el formulario,
  **then** hay que elegir efectivo o transferencia; sin eso no se puede guardar.
- **Given** un pago en transferencia, **then** la referencia sigue disponible
  para anotar el comprobante; en efectivo es opcional.
- **Given** el listado de transacciones, **then** el filtro por medio ofrece
  PlacetoPay, Efectivo y Transferencia.
- **Given** un usuario sin `dashboard.view`, **when** entra a `/admin/dashboard`,
  **then** recibe 403, igual que hoy.

## Edge cases

- El porcentaje se calcula sobre el bruto del periodo. Con bruto en cero no se
  divide: se muestra el estado vacío.
- Un pago reembolsado cuenta en el bruto de su medio, igual que en el total: se
  cobró y después se devolvió. El neto sigue restándolo una sola vez.
- Los porcentajes se redondean a entero y pueden no sumar exactamente 100. No se
  fuerza el cuadre: el monto es el dato, el porcentaje es la lectura.
- Cambiar el periodo no debe recargar la página entera ni perder el scroll.

## Dependencias

- F007 (pagos) y F011 (dashboard), ambos terminados.
- `payments-transactions-admin`, que ya filtra por medio.

## Endpoints involucrados

```
GET  /admin/dashboard                             (can:dashboard.view)  page Inertia con props
GET  /api/v1/admin/reports/revenue                (can:reports.view)    se mantiene: es una descarga
POST /api/v1/admin/bookings/{booking}/payments    (can:bookings.update) suma el campo `method`
```

Se **elimina** `GET /api/v1/admin/dashboard`: la página ya no lo consume y nadie
más lo hace.

## Componentes UI

- Pages: `Admin/Dashboard` (pasa a recibir props)
- Organisms: `RevenueByMethod` (nuevo), `DashboardStatGrid` (sin cambios)
- Molecules: `PeriodSelector` (pasa a navegar con Inertia), `PassengerDrawer`
  (selector de medio en el formulario de pago)

## Datos requeridos

Ninguna columna nueva. Cambian los **valores** de `payments.gateway`:

| Antes | Ahora |
|---|---|
| `placetopay` | `placetopay` |
| `manual` | `cash` o `transfer` |

El proyecto está en desarrollo y las migraciones se editan en su lugar, así que
no hay migración de datos: `migrate:fresh --seed`.

## Out of scope

- Gráfico apilado por medio: la serie temporal sigue siendo del total.
- Desglose por medio en la exportación del reporte: se hará cuando exista un
  flujo contable real que lo pida.
- Otros medios (datáfono, PayPal, cripto).
- El dashboard de super admin, que calcula comisiones de plataforma y es
  independiente.

## Decisiones tomadas

- **Se parte el enum** en vez de agregar una columna. Razón: agrupar por un solo
  campo ya indexado, y el filtro de transacciones gana las dos opciones sin
  tocar nada.
- **El nombre `gateway` se conserva** aunque ahora guarde medios que no son
  pasarelas. Razón: renombrar columna y enum toca unos treinta archivos y no
  aporta a lo que se pidió. Queda anotado como deuda en `plan.md`.
- **Total arriba, desglose debajo del gráfico.** Razón: la fila de indicadores ya
  tiene cinco tarjetas; tres más la rompen en pantallas medianas.
- **El dashboard pasa a Inertia y se retira el endpoint de API.** Razón: un solo
  camino, y es el patrón del panel nuevo.

## Changelog

- `2026-09-13` — Creación.
