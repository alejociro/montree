# Transacciones en el panel — Seguimiento y conciliación de pagos

## Descripción

Un módulo en el panel para ver **todas las transacciones** de la agencia con la
foto completa que devolvió la pasarela: referencia, `requestId`, estado crudo,
autorización, recibo, franquicia, medio y emisor. Existe para un caso muy
concreto: cuando el comercio tiene que escribirle a PlacetoPay por un pago, hoy
no tiene de dónde sacar esos datos sin entrar a la base.

`payments` ya guarda todo esto desde F007. Este feature es **solo lectura más
una acción**: no agrega columnas ni cambia cómo se cobra.

## User stories

- Como admin, quiero buscar una transacción por su referencia o `requestId` para
  pasársela al soporte de la pasarela.
- Como admin, quiero copiar los datos del autorizador sin transcribirlos a mano.
- Como admin, quiero un enlace a la transacción para pegarlo en un correo o ticket.
- Como admin, quiero filtrar por estado, medio de pago y rango de fechas para conciliar.
- Como admin, quiero reconsultar en la pasarela un pago que quedó colgado, sin
  esperar los 10 minutos del comando programado.
- Como admin, quiero ver las transacciones de una salida concreta mientras la reviso.

## Acceptance criteria

- **Given** un admin con `payments.view`, **when** entra a `/admin/transactions`,
  **then** ve las transacciones de su agencia paginadas, más recientes primero.
- **Given** una búsqueda por `MTR-17` o por el `requestId`, **then** el listado
  devuelve esa transacción.
- **Given** filtros de estado, medio y fechas, **when** se combinan, **then** el
  listado los aplica todos y los conserva en la URL (se puede compartir y recargar).
- **Given** una transacción aprobada, **when** se abre su detalle, **then** se ven
  `requestId`, referencia interna, autorización, recibo, franquicia, medio, emisor,
  fecha del autorizador y los campos del procesador, con botón de copiar en los
  identificadores.
- **Given** un pago en efectivo, **then** aparece en el listado marcado como tal y
  su detalle no muestra campos de pasarela vacíos, sino la referencia que escribió
  quien lo registró.
- **Given** un pago `processing` de PlacetoPay, **when** el admin pulsa «Consultar
  en la pasarela», **then** se consulta la sesión y el estado queda actualizado; la
  operación es idempotente y no cobra de nuevo.
- **Given** un usuario sin `payments.view`, **when** entra a cualquier ruta del
  módulo, **then** recibe 403 y el ítem no aparece en el menú.
- **Given** una transacción de otra agencia, **when** se pide por id, **then** 404.

## Edge cases

- Pago en efectivo: no tiene `request_id` ni sesión, así que no se puede
  reconsultar. El botón no se muestra, y la ruta lo rechaza aunque se invoque a mano.
- Pago ya resuelto (`completed`/`failed`/`refunded`): reconsultar no lo cambia
  (`ResolvePaymentAction` corta por `isResolved()`), pero se permite igual porque
  sirve para refrescar los datos del autorizador. El mensaje dice qué pasó.
- Pasarela caída al reconsultar: se informa el error sin tocar el pago.
- Agencia sin credenciales propias ni de plataforma: reconsultar falla con el
  mensaje de configuración, no con un error genérico.
- Búsqueda que no matchea nada: estado vacío explicando qué se puede buscar.
- Una reserva puede tener varias transacciones (reintentos, abonos): el listado
  las muestra todas y el detalle enlaza a la reserva que las agrupa.

## Dependencias

- F007 (los pagos y su esquema ya existen).
- F018 (RBAC: el módulo se apoya en permisos).

## Endpoints involucrados

Rutas **web con Inertia**, no API: el panel recibe los datos como props y los
filtros viajan en la query string. No se expone JSON a terceros.

```
GET   /admin/transactions                    (can:payments.view)   listado + filtros
GET   /admin/transactions/{payment}          (can:payments.view)   detalle
POST  /admin/transactions/{payment}/query    (can:payments.query)  reconsulta en la pasarela
```

Permisos nuevos: `payments.view`, `payments.query`, bajo un módulo `payments`
(«Pagos») en el catálogo de RBAC.

## Componentes UI

- Pages: `Admin/Transactions/Index`, `Admin/Transactions/Show`
- Organisms: `TransactionFilters`, `TransactionsTable`
- Molecules: `CopyableValue` (valor monoespaciado + botón copiar), `PaymentStatusChip` (ya existe)
- En la salida: bloque de transacciones dentro de `PassengerDrawer` y enlace
  «Ver transacciones de esta salida» sobre la planilla.

## Datos requeridos

Ninguna columna nueva. Se leen `payments` (todo el bloque de pasarela que F007 ya
persiste) y, por relación, `bookings`, `tours`, `tour_dates` y el titular.

Los índices que ya existen cubren los filtros: `(tenant_id, status)`,
`(tenant_id, gateway, status)`, `(tenant_id, gateway_status)`,
`(tenant_id, processed_at)` y `reference`.

## Out of scope

- Reembolsos desde el panel: sigue fuera hasta que el negocio lo pida (F007).
- Exportar a CSV: se hará cuando exista un flujo de conciliación contable real.
- Ver transacciones desde el rol guía: el módulo es del admin.
- Reenviar comprobantes al viajero: eso es de F008.
- Gráficas o métricas de pagos: eso vive en el dashboard (F011).

## Decisiones tomadas

- **Alcance**: entran todos los pagos, con columna de medio y filtro por pasarela.
  Razón: conciliar exige ver lo que entró por una salida sin importar el medio.
- **Detalle en página propia** con URL estable. Razón: el enlace se pega en el
  correo al soporte de la pasarela o en un ticket.
- **Acción de reconsulta** en el panel, reusando `ResolvePaymentAction`. Razón: un
  pago colgado se resuelve en el momento en vez de esperar al barrido.
- **Inertia + rutas web**, sin API. Razón: es panel interno, no hay terceros.
- **Integración con la salida por dos lados**: transacciones del viajero en el
  drawer de la planilla, y enlace al listado filtrado por salida.

## Changelog

- `2026-09-12` — Creación.
