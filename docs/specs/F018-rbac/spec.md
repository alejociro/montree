# F018 — RBAC por permisos

> Spec funcional. Lo que el feature hace desde la óptica del usuario.
> Cambios a este archivo requieren actualizar [`tasks.md`](./tasks.md) y registrar en `## Changelog`.

---

## Descripción

Reemplaza la autorización actual de `admin/*` — hoy escrita rol por rol en 11+ sitios distintos (policies, middlewares, form requests y `hasRole()` inline en controllers, ver §"Huecos" abajo) — por un catálogo de 37 permisos `modulo.accion` gestionado con `spatie/laravel-permission`. Los roles dejan de ser la unidad de autorización y pasan a ser un empaque comercial de permisos: nada en el código pregunta por rol, todo pregunta por permiso (`can('tours.delete')`), salvo `super_admin`, que usa `Gate::before`.

El cambio introduce además un rol nuevo, `sales` (Vendedor), separando lo que hoy hace `operator` en dos: quien vende (reservas, promociones, newsletter, reseñas) y quien opera producto (tours, salidas, logística). Un mismo usuario puede tener ambos roles.

Es el primer feature de la Fase 1 del plan de trabajo (`proyectos/montree/tablero/paginas/planfase.md` en el kit p2p-kits). Antes de escribir código de este feature, la Fase 0 congeló el comportamiento actual en `tests/Feature/Rbac/CurrentAdminAccessMatrixTest.php` — esa suite es la fuente de verdad de la columna "Hoy" de este documento, no una descripción a mano.

## User stories

- Como `admin` (dueño de agencia), quiero conservar todos los permisos de mi agencia (equipo, configuración, dinero, producto, ventas), para no perder ninguna capacidad que ya tengo hoy.
- Como `sales` (Vendedor), quiero gestionar reservas, promociones, newsletter y reseñas (moderar y responder juntas), para atender al cliente sin necesitar acceso a producto ni a configuración.
- Como `operator` (Operador), quiero gestionar tours, salidas, logística e imágenes, y publicar productos y cancelar salidas como hago hoy, sin ver dinero, equipo ni configuración.
- Como `guide`, quiero seguir viendo solo mi agenda y los viajeros de mis salidas, sin acceso a ninguna pantalla de administración.
- Como `customer`, mi acceso a mis propios recursos se resuelve por propiedad, no por permisos — este feature no me agrega ni me quita nada.
- Como equipo de desarrollo, quiero que un rol nuevo sea una línea de seeder, no tocar 16 archivos.

## Acceptance criteria

### Catálogo de permisos (37, `modulo.accion`)

- **Given** el seeder de roles y permisos corre, **when** se listan los permisos, **then** existen exactamente estos 37, agrupados por módulo:
  - Dashboard: `dashboard.view`, `reports.view`, `reports.export`
  - Productos: `tours.view`, `tours.create`, `tours.update`, `tours.publish`, `tours.delete`, `tours.images.manage`
  - Salidas: `departures.view`, `departures.create`, `departures.update`, `departures.cancel`, `departures.delete`, `departures.assign_guide`
  - Logística: `logistics.view`, `logistics.manage`
  - Reservas: `bookings.view`, `bookings.update`, `payments.refund`
  - Promociones: `promotions.view`, `promotions.create`, `promotions.update`, `promotions.delete`
  - Newsletter: `newsletter.view`, `newsletter.send`
  - Reseñas: `reviews.view`, `reviews.moderate`, `reviews.respond`
  - Equipo: `team.view`, `team.invite`, `team.role.update`, `team.suspend`
  - Configuración: `tenant.view`, `tenant.update`, `tenant.settings.update`
  - Guía: `guide.schedule.view`, `guide.travelers.view`

### Matriz rol × permiso (aprobada, ver `rbacbase.md` §5 en el kit p2p-kits para el detalle completo con evidencia de código)

- **Given** el seeder corre, **when** se listan los permisos de `admin`, **then** tiene los 37 (todos).
- **Given** el seeder corre, **when** se listan los permisos de `sales`, **then** tiene: `dashboard.view`, `reports.view`, `tours.view`, `departures.view`, `bookings.view`, `bookings.update`, `promotions.*` (view/create/update, no delete), `newsletter.*` (view/send), `reviews.*` (view/moderate/respond).
- **Given** el seeder corre, **when** se listan los permisos de `operator`, **then** tiene: `tours.view/create/update/publish/images.manage` (no `delete`), `departures.*` (todos incluido `assign_guide`, no `delete`), `logistics.*` (view/manage).
- **Given** el seeder corre, **when** se listan los permisos de `guide`, **then** tiene solo: `tours.view`, `departures.view`, `guide.schedule.view`, `guide.travelers.view`.
- **Given** un usuario autentica como `super_admin`, **when** se evalúa cualquier `can()`, **then** `Gate::before` retorna `true` sin consultar la tabla de permisos.

### Migración de `operator`

- **Given** un usuario tiene hoy el rol `operator` (local: `operator@demo.montree.test`; demo/prod: pendiente de inventariar, ver "Decisiones abiertas"), **when** corre el seeder de corte, **then** el usuario recibe **ambos** roles (`sales` + `operator`) por defecto, de forma que no pierde ningún acceso el día del despliegue.

### Cierre de huecos de autorización (confirmados por `CurrentAdminAccessMatrixTest.php`)

Hoy `operator` pasa sin ninguna Policy propia (solo el middleware de grupo) en: `bookings.index`, `tour-dates.index`, `reviews.index`, `newsletter.subscribers`, `users.index`, todas las rutas de logística (routes/providers/hotels, incluido destroy), todas las rutas de tour-dates (incluido destroy y guide), `tours.images.*` (incluido destroy). Tras este feature:

- **Given** un usuario con solo `sales`, **when** intenta `GET /admin/logistics/*` o `DELETE /admin/tour-dates/{id}`, **then** recibe 403 (hoy pasaría como operator; ya no aplica porque sales no tiene esos permisos).
- **Given** un usuario con solo `operator`, **when** intenta `GET /admin/users` o `GET /admin/newsletter/subscribers`, **then** recibe 403 (hoy pasa; deja de pasar porque operator no tiene `team.view` ni `newsletter.view`).
- **Given** un usuario con `operator` pero no `sales`, **when** intenta `GET /admin/reviews` o `GET /admin/bookings`, **then** recibe 403 (bug B1 del panel, cerrado).
- **Given** un usuario con `admin` o `operator`, **when** accede a `guide/schedule` o `guide/tour-dates/{id}/travelers`, **then** recibe 403 salvo que también tenga el permiso `guide.schedule.view` / `guide.travelers.view` — hoy `tenant_guide.only` deja pasar a `admin` y `operator` aunque no sean guías (bug B2), y esto se cierra reemplazando el middleware por `can:`.

## Edge cases

- **Usuario con dos roles** (`sales` + `operator`): sus permisos son la unión de ambos (comportamiento nativo de Spatie); no hay conflicto porque ningún permiso es exclusivo negativo.
- **`role_has_permissions` sin `tenant_id`** (migración `2026_05_17_161119_create_permission_tables.php`): los 6 roles base son globales — el set de permisos de `admin` es idéntico para las 8 agencias existentes. Este feature NO migra esa tabla ni permite personalizar permisos por agencia; queda para cuando una agencia pida su propio rol (rol con nombre propio + `tenant_id`, aditivo).
- **`super_admin` vive en el sentinel `team_id = 0`**: cualquier chequeo de permiso sobre un `super_admin` debe pasar por `Gate::before`, no por `hasPermissionTo()` con team scope, porque no tiene fila en ningún tenant.
- **Dashboard muestra ingresos a quien no tiene `reports.view`**: `operator` tiene `dashboard.view` pero no `reports.view`, y el dashboard actual (`Dashboard.vue:35-45`) renderiza la tarjeta de ingresos sin condicionarla. Este feature entrega el flag `can_export_reports`-style por permiso vía Inertia; el frontend (fuera de este spec, ver F011 update) decide si oculta el widget o si operator recibe una vista propia — decisión de producto, no de este feature.

## Dependencias

- Ninguna. Es fundacional: bloquea el cierre de los bugs A1 (menú por rol) y B1 (operador entra a pantallas de admin) documentados en `bugsdash.md` (kit p2p-kits), que se resuelven construyendo menú y rutas por permiso en vez de parcharlos por rol.

## Endpoints involucrados

Los 31 endpoints de `routes/api.php:93-135` bajo el grupo `admin/*` más `guide/schedule` y `guide/tour-dates/{tourDate}/travelers` (hoy bajo `tenant_guide.only`). Detalle completo, verbo por verbo, en [`contracts.md`](./contracts.md) — **pendiente de escribir**, se hace al arrancar la implementación (Fase 1), no en esta spec.

## Componentes UI

- Sin componentes nuevos en este feature. Toca la fuente de la que leen los existentes:
  - `resources/js/types/auth.ts` — pasa de `tenantRole: string` a `permissions: string[]`.
  - `AuthUserResource` — expone `permissions` en vez de (o además de) `tenantRole`.
  - Composable nuevo `can(permiso: string): boolean` en Vue (hoy no existe ninguna llamada a `can()` en `resources/js`).
- Los sitios que hoy reescriben el mismo `switch` por rol a mano (`AppSidebar.vue:53`, `AdminSidebar.vue:29-70`, `UserMenuContent.vue:40`, `PublicLayout.vue:26`) se consumen en un feature de frontend aparte (Fase 2 del plan), no en este.

## Datos requeridos

Tablas: `permissions`, `roles`, `role_has_permissions`, `model_has_roles`, `model_has_permissions` (todas ya existen vía `spatie/laravel-permission`, hoy con `permissions = 0` filas y `roles = 5`). Este feature puebla `permissions` (37 filas), agrega el rol `sales` (6 roles totales) y llena `role_has_permissions` según la matriz de arriba. No requiere migraciones de schema nuevas.

---

## Out of scope (explícitamente NO se hace)

- Roles propios por agencia / personalización de permisos por tenant (bloqueado por `role_has_permissions` sin `tenant_id`; ver "Decisiones abiertas").
- Rehacer el menú/sidebar por permiso en el frontend (Fase 2 del plan).
- Vista propia de operación para `operator` en el dashboard (decisión de producto pendiente).
- Tocar `TourPolicy::isStaff`/reglas de `tours.view` y `departures.view` para `guide` — ya funcionan correctamente hoy y la matriz las conserva igual.

## Decisiones abiertas

- [ ] Política de edición de roles cuando se construya el módulo de Fase 3B (roles y permisos vía UI): ¿roles base de solo lectura + cada agencia crea los suyos, o clonar el rol base al editarlo? No bloquea este feature (que solo siembra el catálogo), pero si no se resuelve antes de 3B, esa pantalla no se puede especificar.
- [ ] Inventario de usuarios con rol `operator` en demo y producción (fuera del alcance de este repo/kit: requiere acceso al entorno del equipo, `demo.montree.com.co`). Hasta tenerlo, el seeder de corte se corre primero en local con la única asignación conocida (`operator@demo.montree.test`).

---

## Changelog

- `2026-08-16` — Creación inicial. Contenido derivado de la matriz cerrada en `rbacbase.md` (kit p2p-kits, confirmada 2026-08-16) y de la congelación de comportamiento actual en `tests/Feature/Rbac/CurrentAdminAccessMatrixTest.php` (48 tests, 240 assertions, verde).
