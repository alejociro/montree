# F017 — Productos y Salidas

> Spec funcional. Lo que el feature hace desde la óptica del usuario.
> Cambios a este archivo requieren actualizar [`tasks.md`](./tasks.md) y registrar en `## Changelog`.

---

## Descripción

Reestructura el concepto de tours en dos niveles: el **Producto** (la experiencia base que la agencia agrega a su catálogo: nombre, descripción, precio base, itinerario, imágenes, dificultad, requisitos) y la **Salida** (el evento programado de ese producto, con sus condiciones especiales: fecha, capacidad, precio override, guía, ruta, proveedor y hoteles). Un producto puede existir y mostrarse en el catálogo sin salidas programadas; las salidas se agregan y modifican en cualquier momento desde el panel admin.

Decisión de modelado: se **evoluciona** el modelo existente en lugar de crear una entidad paralela — `tours` ES el producto y `tour_dates` ES la salida. Esto conserva intactas las relaciones con bookings, reviews y favoritos. Lo nuevo: entidades de soporte (`routes`, `providers`, `hotels`), condiciones especiales en la salida, el CRUD de salidas que hoy no existe (hallazgo P0-1 del review 2026-07-12: sin él un admin no puede activar un producto nuevo), y el desacople entre "producto activo en catálogo" y "tiene salidas futuras".

## User stories

- Como admin/operator, quiero gestionar mi catálogo de productos (hoy "tours") con toda su información base, aunque aún no tengan salidas programadas.
- Como admin/operator, quiero crear, editar y cancelar salidas de un producto con fecha, capacidad y precio propio.
- Como admin/operator, quiero asociar a cada salida sus condiciones especiales: guía, ruta, proveedor y hoteles.
- Como admin/operator, quiero administrar mis catálogos de rutas, proveedores y hoteles para reutilizarlos entre salidas.
- Como admin/operator, quiero un listado global de TODAS las salidas de mi tenant (cross-producto) para gestionarlas operativamente sin entrar producto por producto.
- Como viajero, quiero ver productos del catálogo aunque no tengan fechas disponibles, con un aviso claro de "Sin fechas disponibles".
- Como viajero, quiero ver en el home del tenant una sección "Próximas salidas" con las salidas concretas más cercanas (fecha, cupos, precio) para poder reservar directamente, distinguiéndolas del catálogo de productos base (`/tours`).

## Acceptance criteria

### Activación y visibilidad del producto
- **Given** un producto `draft` con ≥1 imagen y **cero salidas**, **when** cambia status a `active`, **then** se activa y aparece en el catálogo público con badge "Sin fechas disponibles" (se elimina el requisito de ≥1 fecha futura; el de ≥1 imagen se mantiene).
- **Given** un producto activo sin salidas futuras, **when** un viajero abre su detalle, **then** ve la información completa y un aviso de "Sin fechas disponibles" en lugar del selector de fecha (sin botón de reserva habilitado).

### CRUD de salidas
- **Given** un producto, **when** el admin crea una salida con `starts_at` futura y `capacity` ≥ 1, **then** la salida queda `open` y visible en el detalle público del producto.
- **Given** una salida con reservas activas, **when** el admin intenta eliminarla, **then** recibe `409`; debe cancelarla, lo que notifica el estado (las reservas quedan según F006/F007).
- **Given** una salida, **when** el admin la edita (fecha, capacidad, precio, condiciones), **then** los cambios se reflejan; `capacity` no puede quedar por debajo de `booked_count`.
- **Given** una salida `open` con fecha pasada, **when** se lista en el admin, **then** se muestra como pasada y no aparece en el detalle público.

### Condiciones especiales de la salida
- **Given** una salida, **when** el admin le asigna guía, ruta, proveedor y/o hoteles, **then** quedan asociados y visibles en el panel de la salida (todas las condiciones son opcionales).
- **Given** una ruta/proveedor/hotel usados por una salida, **when** el admin intenta eliminarlos, **then** recibe `409` con el detalle de salidas que los usan.

### Catálogos de soporte (rutas, proveedores, hoteles)
- **Given** un admin, **when** crea/edita/elimina rutas, proveedores u hoteles, **then** el CRUD funciona scoped a su tenant (nombre requerido; campos de contacto/detalle opcionales).

### Listado global de salidas ("Tours" en el admin)
- **Given** un admin/operator, **when** abre la sección "Tours" del sidebar, **then** ve una tabla con TODAS las salidas de su tenant cross-producto (producto, fecha, ocupación, precio efectivo, guía, condiciones, estado).
- **Given** el listado global, **when** aplica filtros de estado, producto o rango de fechas, **then** la tabla se filtra en servidor con paginado estándar.
- **Given** una fila del listado global, **when** el admin edita o cancela, **then** reutiliza los mismos flujos del panel por producto (`TourDateFormDialog` / cancelación de `TourDatesPanel`).
- **Given** el listado global, **when** cada fila muestra su estado, **then** usa el **estado de presentación derivado** (ver más abajo), no solo el `status` almacenado.

### Estado de presentación derivado (`display_status`)
- **Given** una salida, **when** se la muestra o filtra por estado, **then** su estado de presentación se **deriva en tiempo de request** y no se persiste como columna.
- Reglas de derivación (sea `end = ends_at ?? starts_at`, `now` = reloj del request):
  - Si `status` almacenado es `cancelled` → `cancelled` (siempre gana).
  - Si `end < now` → `finished`.
  - Si `starts_at <= now <= end` → `in_progress` (solo posible cuando la salida tiene `ends_at` asignado).
  - En cualquier otro caso → se muestra el `status` almacenado (`open` | `full` | `closed`).
- Racional: los estados temporales (`in_progress`, `finished`) son función del reloj. Persistirlos exigiría jobs de transición y podría quedar stale; derivarlos es determinista, sin migración y sin costo operativo. El `status` almacenado (`open`/`full`/`closed`/`cancelled`) sigue siendo la fuente de verdad para el ciclo de vida no-temporal.

### Sección pública "Próximas salidas" (home del tenant)
- **Given** un tenant con salidas `open` y `starts_at` futuro de productos activos, **when** un viajero abre el home, **then** ve una sección "Próximas salidas" (ubicada entre "Tours destacados" y "Promociones") con hasta 6 salidas ordenadas por fecha ascendente.
- **Given** cada salida en la sección, **when** se renderiza su card, **then** muestra imagen del producto, nombre del tour (link a su detalle público), fecha en español, cupos disponibles (con badge de urgencia si ≤3), precio efectivo y CTA "Reservar" hacia el flujo de booking de esa salida.
- **Given** un tenant sin salidas próximas elegibles (o solo salidas `full`/`closed`/`cancelled`/pasadas o de productos inactivos), **when** se carga el home, **then** la sección "Próximas salidas" no se renderiza.
- **Given** la distinción producto base vs salida concreta, **when** el viajero navega, **then** el catálogo `/tours` (productos base) se mantiene sin cambios y NO existe página/tab pública dedicada de "Salidas": el home + catálogo cubren la necesidad sin fragmentar la navegación.
- **Given** la sección pública, **when** se expone cada salida al viajero, **then** NO se filtra información operativa interna (notes, guía, ruta, proveedor, hoteles, `booked_count`, `capacity` cruda, `price_override`); solo se exponen fecha, cupos disponibles, precio efectivo y datos públicos del tour.

### Aislamiento multi-tenant
- **Given** salidas/rutas/proveedores/hoteles del tenant A, **when** un admin del tenant B consulta o referencia sus IDs, **then** recibe `404`.
- **Given** la sección "Próximas salidas" del home, **when** se resuelve el tenant por subdominio, **then** solo se muestran salidas de ese tenant (aislamiento verificado en test).

## Edge cases

- Salida creada con fecha pasada: rechazar con `422`.
- Reducir `capacity` por debajo de `booked_count`: `422`.
- Cancelar salida ya cancelada: idempotente o `409` (definir en contracts).
- Hoteles duplicados en la misma salida: la asociación es un set (sin duplicados).
- Producto pausado con salidas futuras `open`: las salidas no aparecen en público (regla existente: solo tours activos).
- Guía asignado que deja de ser miembro del tenant: la salida conserva `guide_id` pero el panel lo marca como inactivo (comportamiento actual de `AssignGuideAction` se conserva).

## Dependencias

- F003 (Tour CRUD) — el editor de producto existente gana la sección de salidas.
- F004 (Catálogo) — badge "Sin fechas disponibles" ya contemplado; se elimina la exclusión implícita por falta de fechas si el producto está activo.
- F005 (Detalle de tour) — detalle público muestra aviso sin selector cuando no hay salidas futuras.
- F006 (Booking) — sin cambios: se reserva contra una salida concreta.
- F014 (Team) — asignación de guía existente se integra al panel de salida.

## Endpoints involucrados

```
GET    /api/v1/admin/tour-dates            (NUEVO: listado global cross-producto)
GET    /api/v1/admin/tours/{tour}/dates
POST   /api/v1/admin/tours/{tour}/dates
PUT    /api/v1/admin/tour-dates/{tourDate}
PATCH  /api/v1/admin/tour-dates/{tourDate}/cancel
DELETE /api/v1/admin/tour-dates/{tourDate}

GET/POST           /api/v1/admin/routes
PUT/DELETE         /api/v1/admin/routes/{route}
GET/POST           /api/v1/admin/providers
PUT/DELETE         /api/v1/admin/providers/{provider}
GET/POST           /api/v1/admin/hotels
PUT/DELETE         /api/v1/admin/hotels/{hotel}
```

(Detalle en [`contracts.md`](./contracts.md))

## Componentes UI

- Pages: sección "Salidas" dentro de `Admin/Tour/Edit` (o page dedicada `Admin/Tour/Dates`), page `Admin/Logistics` (tabs Rutas/Proveedores/Hoteles), page `Admin/Departures/Index` (NUEVA: listado global de salidas del tenant)
- Organisms: `TourDatesPanel` (lista + estados), `TourDateFormDialog` (crear/editar con condiciones), `LogisticsCrudPanel`
- Molecules: selects de guía/ruta/proveedor, multi-select de hoteles, `CounterStepper` (reutilizado) para capacidad
- `Admin/Departures/Index.vue`: tabla con columnas producto (link a su show admin), fecha, ocupación (reservados/capacidad con barra), precio efectivo, guía, condiciones (ruta/proveedor/hoteles), estado (display_status). Filtros: estado, producto, rango de fechas. Acciones por fila: editar (reutiliza `TourDateFormDialog`) y cancelar (mismo flujo de `TourDatesPanel`).
- Labels y navegación del admin: dos entradas de sidebar — **Productos** (`/admin/tours`, catálogo base existente) y **Tours** (`/admin/departures`, listado global de salidas). La "Salida" es la unidad interna; el lado público conserva "Tours" para el viajero.
- Home público (`resources/js/pages/Home.vue`): sección "Próximas salidas" entre "Tours destacados" y "Promociones". Cards con imagen del producto, nombre (link a detalle público), fecha en español, cupos con badge de urgencia (≤3), precio efectivo y CTA "Reservar" → `/booking/new?tour_date_id=X`. Skeleton mientras resuelve el prop deferred; si no hay salidas la sección no se renderiza. NO se crea página/tab pública de "Salidas".
- Home público: se elimina la sección de newsletter del storefront (no hay soporte de envío de correos todavía). El módulo admin de newsletter (F013) y su endpoint API quedan intactos.

## Datos requeridos

Tablas existentes: `tours` (= producto, sin cambios de schema), `tour_dates` (= salida, + `route_id`, `provider_id` nullable)
Tablas nuevas: `routes`, `providers`, `hotels` (tenant-scoped), `tour_date_hotels` (pivot)

---

## Out of scope (explícitamente NO se hace)

- Renombrar las tablas `tours`/`tour_dates` en BD (el renombre es conceptual y de UI; el costo de migrar todas las FKs/código no aporta valor de usuario).
- Precio diferenciado por adulto/menor en la salida (pendiente de decisión de negocio, ver F006).
- Costos/tarifas de proveedores y hoteles (solo se registran como condiciones logísticas, sin módulo financiero).
- Notificaciones automáticas a viajeros al cancelar una salida (queda en F008 como mejora futura).
- Recurrencia de salidas (crear serie semanal/mensual) — iteración futura.
- Página/tab pública dedicada de "Salidas" — descartada: el home ("Próximas salidas") + el catálogo `/tours` cubren la necesidad del viajero sin fragmentar la navegación.
- Reactivar la sección de newsletter en el home público — bloqueada hasta que exista soporte de envío de correos (fuera de F017; el módulo admin F013 sigue intacto).

## Changelog

- `2026-07-12` — Creación. Origen: pedido de reestructurar tours como productos base + eventos con condiciones especiales (guía, ruta, proveedor, hoteles) y hallazgo P0-1 del review 2026-07-12 (no existe CRUD de salidas). Decisión de modelado: evolucionar `tours`/`tour_dates` en vez de tabla `products` paralela, con entidades de soporte nuevas.
- `2026-07-13` — Separación en el admin de "Productos" (catálogo base, `/admin/tours`) vs "Tours" (listado global de salidas, `/admin/departures`, con page `Admin/Departures/Index.vue`) + estado de presentación derivado (`display_status`). Razón: separación Productos vs Tours en admin + estado de presentación derivado (los estados `in_progress`/`finished` son función del reloj y se derivan en backend sin persistir ni jobs de transición).
- `2026-07-13` — Lado público: sección "Próximas salidas" en el home del tenant (user story + criterios de aceptación + componentes UI). Se descarta la página/tab pública dedicada de "Salidas" (home + catálogo cubren la necesidad). Se elimina la sección de newsletter del storefront (sin soporte de correos; F013 admin intacto). Razón: el viajero necesita distinguir productos base (catálogo `/tours`) de las salidas concretas próximas y poder reservarlas directo desde el home, sin fragmentar la navegación.
