# F018 — Plan técnico

> Decisiones técnicas para implementar este feature.
> Backend, frontend, base de datos, tests.
> Decisiones tomadas con la skill `laravel-best-practices` cargada
> (§3 Security, §10 Routing, §15 Architecture, §6 Validation) —
> ver razones puntuales marcadas `[LBP §N]` abajo.

---

## 1. Resumen

Puebla el catálogo de 38 permisos y el rol `sales` vía seeder, reemplaza todo
`hasRole()`/rol-hardcodeado en Policies, Form Requests, middlewares y rutas
por `can('modulo.accion')`, cierra los huecos de autorización confirmados por
`CurrentAdminAccessMatrixTest.php`, y expone los permisos del usuario al
frontend por Inertia. No hay endpoints nuevos, no hay migraciones de schema
nuevas — ver `contracts.md` para el mapa completo ruta → permiso.

## 2. Backend

### Modelos

- `User` — sin cambios de columnas. Se le agregan dos métodos ya previstos por
  `multi-tenancy.md` §10.5 si no existen: nada nuevo que crear, F018 los
  reutiliza (`loadRolesForTeam`, `isSuperAdmin`).

### Migrations

- Ninguna. `permissions`, `roles`, `role_has_permissions`, `model_has_roles`,
  `model_has_permissions` ya existen (spatie/laravel-permission instalado en
  Fase 0 del setup del schema, `roles.tenant_id` ya es parte de la PK
  compuesta). F018 solo puebla filas vía seeder.

### Enums

- `App\Enums\UserRole` — agrega el case `Sales = 'sales'` con `label()` →
  `'Vendedor'`. Los 5 labels existentes pasan a español si no lo están ya
  (consistencia con la constitución §3.5, `label()` es para UI).

### Actions

F018 es autorización, no casos de uso de negocio nuevos — la constitución
(§3.3, tabla de patrones) NO pide Action para "operaciones triviales de
autorización". La única lógica con ramas reales es el cierre de B3:

- `App\Actions\Team\UpdateMemberRoleAction` — si no existe ya como Action,
  se extrae de `TeamController::updateRole` (hoy con lógica inline, ver
  huecos de `spec.md`). Verifica que el usuario objetivo pertenece al tenant
  actual (`$user->membershipFor($tenant)`) ANTES de tocar sus roles; si no,
  lanza `CrossTenantAccessException` (dominio, no `\Exception` genérica,
  constitución §3.2). El controller la atrapa y devuelve 403 con
  `error_code: CROSS_TENANT_ACCESS` (ver `contracts.md` §4).
- Mismo patrón para `suspend`/`reactivate` si están en el mismo controller
  con la misma falla — un solo chequeo de pertenencia compartido, no
  triplicado (regla del 3 de la constitución: si se repite 3 veces, se
  extrae a un método privado del Action o a `User::isActiveMemberOf`, que
  ya existe).

### Form Requests

Cambian `authorize()` de rol-hardcodeado/string suelto a `can()`:

- `Admin/Team/UpdateMemberRoleRequest` (hoy compara string en línea 23) →
  `$this->user()->can('team.role.update')`.
- `Admin/Team/InviteMemberRequest` (línea 25) → `can('team.invite')`.
- `Review/ModerateReviewRequest` (línea 13) → `can('reviews.moderate')`.
- `Review/RespondReviewRequest` (línea 13) → `can('reviews.respond')`.
- `Newsletter/SendCampaignRequest` (línea 13) → `can('newsletter.send')`.
- `SuperAdmin/StoreTenantUserRequest` (línea 38) — este SÍ sigue siendo
  rol-consciente porque super_admin asigna roles a usuarios de cualquier
  tenant; no se toca (`Gate::before` ya lo cubre, fuera del alcance de este
  feature).
- Ninguno de estos Form Requests usa `return true` ciego [LBP §3/§6]: los
  que hoy lo hacen (si los hay) quedan cerrados por este feature, no es
  opcional.

### Controllers

Sin controllers nuevos. Los existentes bajo `admin/*` no cambian de firma —
solo el middleware de ruta y, donde aplique, el `authorize()` del Form
Request. Ningún método pasa de las 10 líneas que ya tenía [LBP §10];
F018 no agrega lógica a controllers, la saca de ellos.

### Resources

- `AuthUserResource` — `tenantRole: string` → `permissions: array` (shape
  exacto en `contracts.md` §3). Sin relaciones nuevas que cargar, sin riesgo
  de N+1: `getAllPermissions()` ya viene cacheada por Spatie por request.

### Policies

`hasRole('admin')`/`hasRole('operator')` inline → `can('modulo.accion')`:

- `DashboardPolicy` → `dashboard.view`, `reports.view`, `reports.export`.
- `TourPolicy` → `tours.view/create/update/publish/delete`,
  `tours.images.manage`. **No se toca** `isStaff`/las reglas ya correctas
  para `guide` (out of scope explícito en `spec.md`).
- `TenantPolicy` → `tenant.view/update/settings.update`.
- `PromotionPolicy` → `promotions.view/create/update/delete`.
- `ReviewPolicy` → `reviews.view/moderate/respond`.
- Ningún Policy nuevo: los 5 ya existen, se editan sus métodos, no se crean
  clases (constitución §3.2 "una Policy por modelo que requiera
  autorización" — el modelo ya tiene la suya).
- Huecos sin Policy propia (`BookingController`, `TourDateController`,
  `RevenueReportController`, `TourImageController::destroy`,
  `NewsletterController::index`, `ReviewController::index`, logística) se
  cierran agregando el chequeo `can()` que falta — algunos entran en
  `BookingPolicy`/`TourDatePolicy` nuevas si el modelo aún no tiene una
  (sí aplica "una Policy por modelo", constitución §3.2), otros (logística,
  reportes) van directo en el controller vía `authorize()` porque no tienen
  modelo Eloquent propio con Policy — patrón ya usado en el repo para
  recursos sin modelo dedicado.

### Middlewares

- `EnsureTenantAdmin` / `EnsureTenantGuide` — se retiran del grupo de rutas
  `admin/*`/`guide/*` (reemplazados por `can:` por ruta, ver §2 Routes). La
  clase no se borra: sigue validando membresía `active` (multi-tenancy.md
  §10.2), que es ortogonal al permiso y sigue corriendo ANTES del `can:`.

### Rutas

- `routes/api.php:93-135` y las de `guide/*`: cada ruta recibe `->middleware('can:<permiso>')`
  según el mapa de `contracts.md` §1, en vez del gate de grupo actual.
- **El grupo `admin/*` (api y web) conserva un gate de grupo, pero por permiso:
  `can:dashboard.view`** (agregado al plan el `2026-08-16`, ya implementado).
  Corre antes del `can:` de cada ruta. Sin él, `guide` —con `tours.view` y
  `departures.view` por matriz— entraba al panel. Ver `contracts.md` §1.
- `Gate::before` en `AppServiceProvider::boot()`:
  ```php
  Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);
  ```
  Retorna `null` (no `false`) para no-super_admin — deja que Spatie evalúe
  el permiso real, no bloquea a nadie más [LBP §15, single-purpose: este
  gate hace una sola cosa].

### Jobs / Notifications / Events

- Ninguno. Autorización no dispara side-effects asíncronos.

## 3. Frontend

Fuera del alcance de este feature (ver `spec.md` §"Componentes UI" y "Out of
scope"): F018 solo cambia el tipo `AuthUser.permissions` y expone el dato.
El composable `can()` y la reescritura de los 4 sidebars que hoy hardcodean
el switch por rol son Fase 2 del plan (`planfase.md`), feature aparte.

### Types

- `resources/js/types/auth.ts` — `AuthUser.tenantRole: string` →
  `AuthUser.permissions: string[]`. Único cambio de frontend en este
  feature; los consumidores de `tenantRole` no se tocan aquí (quedan
  rotos hasta Fase 2, aceptado y documentado como riesgo abajo).

## 4. Tests

### Feature tests (backend)

- `tests/Feature/Rbac/CurrentAdminAccessMatrixTest.php` (ya existe, Fase 0)
  se actualiza celda por celda: cada `assertForbidden()`/`assertOk()` que
  cambia de valor por este feature se ajusta, y se agrega el motivo en un
  comentario `// WHY:` solo donde el cambio no es obvio por el nombre del
  test [constitución §2.3, comentarios solo si el WHY no es obvio].
- `tests/Feature/Rbac/PermissionCatalogSeederTest.php` (nuevo) — 1 happy:
  corre el seeder, cuenta 38 permisos agrupados por módulo; 1 edge: corre
  el seeder dos veces (`idempotente`, no duplica filas).
- `tests/Feature/Rbac/RoleMigrationSeederTest.php` (nuevo) — 1 happy: el
  usuario `operator` local recibe ambos roles tras el seeder de corte;
  1 failure: un usuario sin rol `operator` no se toca.
- `tests/Feature/Team/UpdateMemberRoleActionTest.php` (nuevo, cierre de B3)
  — 1 happy: admin del tenant A cambia rol de usuario del tenant A; 1
  failure: admin del tenant A intenta cambiar rol de usuario del tenant B
  → `CROSS_TENANT_ACCESS`, 403; 1 tenant isolation (mismo caso, aserción
  explícita de aislamiento por `testing-policy.md` §2).
- Por cada endpoint del mapa de `contracts.md` §1: mínimo 1 failure test
  que verifica 403 sin el permiso, agregado a los tests de Feature del
  módulo correspondiente si no existen ya (cobertura mínima de
  `testing-policy.md` §2: happy + failure + edge por endpoint).

### Unit tests

- Ninguno. No hay clases puras nuevas — `UpdateMemberRoleAction` se cubre
  con Feature test (constitución: Actions se testean vía Feature salvo
  lógica pura aislable, que no es el caso acá).

## 5. Decisiones tomadas

- **Seeder de permisos separado del seeder de asignaciones rol→permiso**:
  como pidió el usuario explícitamente. `RolesAndPermissionsSeeder` gana un
  método privado `seedPermissions()` (38 filas) y otro `seedRolePermissions()`
  (matriz rol→permiso), llamados en orden desde `run()`. Un solo archivo,
  dos responsabilidades separadas en métodos — no dos seeders, porque
  `DatabaseSeeder` ya los orquesta juntos y partirlos en clases separadas
  violaría la regla de "sin sobreingeniería" (constitución §2.1) sin
  beneficio real.
- **Autorización de recursos sin Policy dedicada** (logística, reportes):
  `authorize()` directo en el controller contra el permiso, no una Policy
  vacía por cumplir la forma. Constitución §3.3: Policy es "recurso con
  autorización" — logística/reportes no son un modelo Eloquent con
  ownership, son vistas de agregación.
- **`Gate::before` retorna `null`, no `false`, para no-super_admin**: si
  retornara `false` bloquearía a TODOS los usuarios sin importar su
  permiso real, rompiendo cualquier `can()` posterior. `null` delega en
  Spatie.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| `operator` actual pierde acceso el día del despliegue si el seeder de corte no corre antes del deploy de código | alta si se hace mal el orden | Orden de despliegue explícito en `planfase.md` §Transversal: seeder de corte primero, código de rutas `can:` después, en demo antes que en producción |
| Frontend rompe donde lee `tenantRole` (4 sidebars) hasta que Fase 2 los reescriba | media, ya conocido | Aceptado y documentado como out of scope; Fase 2 es la siguiente feature en el plan, no queda huérfano |
| Falta de inventario `operator` en demo/producción (0.4 bloqueada por acceso) | media | No bloquea este feature (corre en local con el único usuario conocido); si al desplegar aparecen más `operator` sin inventariar, la migración de roles los cubre igual porque es "todo usuario con rol operator", no una lista fija |
| Migrar `hasRole()` en 16 archivos en un solo PR grande | media | Constitución §8: "PRs pequeños... si el feature es grande, sub-PRs por capa" — este feature se puede partir en sub-PR de Policies+Requests y sub-PR de rutas+seeder si el diff crece demasiado, decisión al implementar |

## 7. Out of scope explícito

- Composable `can()` en Vue y reescritura de los 4 sidebars (Fase 2).
- Roles propios por agencia / UI de edición de roles (Fase 3B, bloqueada por
  decisión pendiente sobre `role_has_permissions` sin `tenant_id`).
- Vista propia de operación para `operator` en el dashboard (decisión de
  producto pendiente).
- **Permisos con scope** (`guide` limitado a *sus* salidas en vez de
  `tours.view`/`departures.view`/`guide.*.view` globales). Registrado como deuda
  al cerrar Fase 1; hoy la propiedad se chequea aparte, en el controller, por
  `guide_id`. Feature futuro, ver `spec.md` §"Cierre de huecos de autorización".

---

## Changelog

- `2026-08-16` — "37 permisos" → **38** en §1, §4 y §5 (conteo real del catálogo
  y de la tabla `permissions`; la enumeración de `spec.md` no cambió). §2 Rutas:
  documentado el gate de grupo `can:dashboard.view` sobre `admin/*`, que se
  implementó y no estaba planificado. §7: agregada la deuda de permisos con
  scope. Razón: alinear el plan con lo implementado y revisado (GO, 490/490) al
  cerrar Fase 1. Sin cambios funcionales a la spec.
