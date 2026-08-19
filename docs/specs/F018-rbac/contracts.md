# F018 — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.
>
> F018 no agrega endpoints nuevos: cierra el catálogo de permisos y cambia
> la regla de autorización de rutas `admin/*` y `guide/*` ya existentes.
> El contrato de este feature es doble: (1) qué permiso exige cada ruta,
> y (2) el shape nuevo que el backend expone al frontend para que la UI
> sepa qué puede mostrar.

---

## 1. Mapa ruta → permiso (`routes/api.php:93-135`, grupo `admin/*`, y `guide/*`)

Reemplaza el gate de grupo (`tenant_admin.only` / `tenant_guide.only`) por
`can:<permiso>` por ruta. Fuente: matriz cerrada en `rbacbase.md` (kit
p2p-kits) y huecos confirmados en `CurrentAdminAccessMatrixTest.php`.

| Método | Ruta | Permiso |
|---|---|---|
| *(todas)* | **`/admin/*` — grupo completo, api y web** | **`dashboard.view`** (gate de entrada, se evalúa ANTES del permiso de la fila) |
| GET | `/admin/dashboard` | `dashboard.view` |
| GET | `/admin/reports` | `reports.view` |
| GET/POST | `/admin/reports/export` | `reports.export` |
| GET | `/admin/tours` | `tours.view` |
| POST | `/admin/tours` | `tours.create` |
| PUT/PATCH | `/admin/tours/{tour}` | `tours.update` |
| PATCH | `/admin/tours/{tour}/publish` | `tours.publish` |
| DELETE | `/admin/tours/{tour}` | `tours.delete` |
| POST/DELETE | `/admin/tours/{tour}/images/*` | `tours.images.manage` |
| GET | `/admin/tour-dates` | `departures.view` |
| POST | `/admin/tour-dates` | `departures.create` |
| PUT/PATCH | `/admin/tour-dates/{tourDate}` | `departures.update` |
| PATCH | `/admin/tour-dates/{tourDate}/cancel` | `departures.cancel` |
| DELETE | `/admin/tour-dates/{tourDate}` | `departures.delete` |
| PATCH | `/admin/tour-dates/{tourDate}/guide` | `departures.assign_guide` |
| GET | `/admin/logistics/{routes,providers,hotels}` | `logistics.view` |
| POST/PUT/DELETE | `/admin/logistics/{routes,providers,hotels}/*` | `logistics.manage` |
| GET | `/admin/bookings` | `bookings.view` |
| PATCH | `/admin/bookings/{booking}` | `bookings.update` |
| POST | `/admin/bookings/{booking}/refund` | `payments.refund` |
| GET | `/admin/promotions` | `promotions.view` |
| POST | `/admin/promotions` | `promotions.create` |
| PUT/PATCH | `/admin/promotions/{promotion}` | `promotions.update` |
| DELETE | `/admin/promotions/{promotion}` | `promotions.delete` |
| GET | `/admin/newsletter/subscribers` | `newsletter.view` |
| POST | `/admin/newsletter/campaigns` | `newsletter.send` |
| GET | `/admin/reviews` | `reviews.view` |
| PATCH | `/admin/reviews/{review}/moderate` | `reviews.moderate` |
| POST | `/admin/reviews/{review}/respond` | `reviews.respond` |
| GET | `/admin/team` | `team.view` |
| POST | `/admin/team/invite` | `team.invite` |
| PATCH | `/admin/team/{user}/role` | `team.role.update` |
| PATCH | `/admin/team/{user}/{suspend,reactivate}` | `team.suspend` |
| GET | `/admin/tenant/settings` | `tenant.view` |
| PUT/PATCH | `/admin/tenant/settings` | `tenant.update` / `tenant.settings.update` |
| GET | `/guide/schedule` | `guide.schedule.view` |
| GET | `/guide/tour-dates/{tourDate}/travelers` | `guide.travelers.view` |

`super_admin` no se lista: `Gate::before` lo autoriza siempre, antes de
llegar al chequeo `can:`.

**Gate de grupo `can:dashboard.view`** (agregado al contrato el `2026-08-16`;
ya implementado en Fase 1, decisión de producto ratificada). Toda ruta del
grupo `admin/*` — **tanto la de API como la web/Inertia** — exige **dos**
chequeos, en este orden:

1. `can:dashboard.view` — llave de entrada al panel, aplicada al grupo.
2. El `can:<permiso>` específico de su fila en la tabla de arriba.

Es decir, `dashboard.view` no es solo el permiso de `GET /admin/dashboard`:
es el conjunto "quien trabaja en el panel" (`admin`, `sales`, `operator`; **no**
`guide`). Sin él, `guide` —que por matriz tiene `tours.view` y `departures.view`
para sus propias salidas— entraba a `GET /admin/tours` y `GET /admin/tour-dates`,
contra la user story "guide … sin acceso a ninguna pantalla de administración"
(`spec.md`). Se usó un permiso ya existente en vez de inventar un permiso 39
que habría vuelto a mover el conteo del catálogo.

Las rutas `guide/*` (últimas dos filas) **no** pasan por este gate: siguen con
su `can:` propio y la comprobación de propiedad por `guide_id` en el controller.

## 2. `HandleInertiaRequests` — prop compartida `auth.permissions`

**Hoy** (`resources/js/types/auth.ts`):
```ts
interface AuthUser {
  // ...
  tenantRole: string
}
```

**Después de F018**:
```ts
interface AuthUser {
  // ...
  permissions: string[] // ej. ["tours.view", "tours.create", "bookings.view", ...]
}
```

- `tenantRole` se elimina del shape compartido — ningún componente de este
  feature debe seguir leyéndolo (los que hoy lo leen son parte del scope de
  Fase 2, no de este feature; ver "Out of scope" en `spec.md`).
- `permissions` es la unión de los permisos de **todos** los roles activos
  del usuario en el tenant actual (`$user->getAllPermissions()->pluck('name')`,
  ya resuelto por Spatie con `teams` activo).
- Para `super_admin`: `permissions` se llena con **los 38 nombres completos**
  (no un flag `is_super_admin` aparte) para que el frontend pueda usar el
  mismo helper `can()` sin caso especial. El backend sigue autorizando
  server-side vía `Gate::before`, este array es solo para UI.

## 3. `AuthUserResource`

**Response 200** (fragmento relevante, dentro de `props.auth.user` de cualquier
página Inertia de `admin/*` o `guide/*`):

```json
{
  "id": 42,
  "name": "Ana Ríos",
  "email": "ana@eco-adventures.test",
  "isSuperAdmin": false,
  "permissions": ["dashboard.view", "tours.view", "tours.create", "..."]
}
```

**`isSuperAdmin` no es redundante con `permissions`.** El `super_admin` recibe el
catálogo completo (su bypass es `Gate::before`, no la tabla), pero **no es miembro
de ningún tenant**, así que `EnsureTenantAdmin` le responde 403 en todo `admin/*`
en cualquier host. Por eso el menú **no** se puede armar solo con `can()`: quien
tenga `isSuperAdmin: true` ve únicamente la zona de plataforma
(`/super-admin/dashboard`, `/super-admin/tenants`) y ninguna sección de agencia ni
de viajero. Es la misma distinción que hace el backend con dos middlewares
distintos (`super_admin.only` vs. `tenant_admin.only`).

## 4. Errores — sin cambio de shape, cambia el motivo

Sigue el shape estándar de `api-conventions.md` §3. Lo único nuevo es que el
**motivo** de un 403 ahora es "falta el permiso X", no "el rol no es Y":

| Status | Caso | error_code | Mensaje |
|---|---|---|---|
| 403 | Usuario autenticado sin el permiso de la ruta | `INSUFFICIENT_PERMISSION` | "No tienes permiso para realizar esta acción." |
| 403 | `TeamController::updateRole/suspend/reactivate` sobre usuario de otro tenant (cierre de B3) | `CROSS_TENANT_ACCESS` | "El usuario no pertenece a esta agencia." |

`INSUFFICIENT_PERMISSION` reemplaza cualquier mensaje ad-hoc que hoy devuelven
las Policies con `hasRole()` embebido. `CROSS_TENANT_ACCESS` es nuevo (no
existía chequeo, ver `plan.md` §2 Actions / B3).

**Navegación (no JSON).** Una petición de página con el mismo 403 ya no cae en la
pantalla cruda de Symfony: `bootstrap/app.php` la renderiza como la página Inertia
`Errors/Generic` con el status intacto y dos props — `status: number` y
`homeUrl: string` (el home de rol del usuario, o `/super-admin/dashboard` para el
`super_admin`). Aplica a 403, 404, 419, 429, 500 y 503. El shape JSON de la tabla
de arriba no cambia: el callback se salta cualquier petición con `expectsJson()`.

## 5. Eventos / Side-effects

- Ninguno nuevo. F018 no dispara eventos de dominio — es autorización pura.
- El seeder de corte (`RolesAndPermissionsSeeder`, ver `plan.md` §2) es un
  side-effect de despliegue, no de request: asigna `sales` + `operator` a
  todo usuario que hoy tiene `operator`, documentado en `spec.md` §"Migración
  de operator".

---

## Cambios al contrato

- `2026-08-18` — §3 y §4, a raíz de las pruebas manuales sobre `montree.test`
  (bugs reales, no cambios de alcance). (a) El menú deja de armarse solo con
  `can()`: se agrega `isSuperAdmin` como discriminante porque `Gate::before` le
  aprobaba al `super_admin` los 38 permisos y la UI le ofrecía el panel de una
  agencia de la que no es miembro — "Panel de la agencia" y el sidebar completo
  respondían 403 en todos sus ítems. (b) Los errores HTTP de navegación pasan a
  renderizarse como `Errors/Generic`. Sin impacto en el shape JSON ni en la
  matriz de permisos.

- `2026-08-16` — **BREAKING para backend y frontend** (documenta lo ya
  implementado en Fase 1, no pide código nuevo): §1 agrega el gate de grupo
  `can:dashboard.view` sobre **todo** `admin/*` (api y web), que se evalúa antes
  del permiso específico de cada ruta. Razón: sin él `guide` entraba al panel de
  administración vía `tours.view`/`departures.view`, contra la user story de
  `spec.md`. **Endpoints afectados: todas las filas de `admin/*` de la tabla de §1**
  (dashboard, reports, tours, tour-dates, logistics, bookings, promotions,
  newsletter, reviews, team, tenant) — todos pasan de exigir 1 permiso a exigir
  2. **No afectadas**: `GET /guide/schedule` y
  `GET /guide/tour-dates/{tourDate}/travelers`. Impacto en frontend: un usuario
  sin `dashboard.view` recibe 403 en cualquier pantalla de `admin/*`, así que el
  menú de Fase 2 debe filtrar por `dashboard.view` **y** por el permiso del ítem.
- `2026-08-16` — §2: "los 37 nombres completos" → **38**. Razón: el catálogo real
  (`spec.md` §"Catálogo de permisos", enumeración sin cambios) y la tabla
  `permissions` de la DB tienen 38 filas; "37" era un error de conteo del
  documento original. Sin impacto en el shape: `permissions` sigue siendo
  `string[]`.
- `2026-08-16` — Creación inicial. Deriva de la matriz cerrada (`rbacbase.md`)
  y de los huecos confirmados por `CurrentAdminAccessMatrixTest.php`.
