# F018 — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Generado a partir de `plan.md`. Modificaciones se reflejan en ambos.
> Skill obligatoria para todo item de este archivo: `laravel-best-practices`
> (cargarla al inicio de cada sesión de `montree-backend-dev` sobre F018).

---

## Backend (`montree-backend-dev`)

- [x] `App\Enums\UserRole` — agregar case `Sales = 'sales'`, `label()` → `'Vendedor'`; pasar los 5 labels existentes a español si no lo están
- [x] `database/seeders/RolesAndPermissionsSeeder.php` — método `seedPermissions()`: 38 permisos `modulo.accion` (catálogo completo en `spec.md` §"Catálogo de permisos") — *el ítem decía 37; corregido a 38 el `2026-08-16`, ya alineado con `spec.md`*
- [x] `database/seeders/RolesAndPermissionsSeeder.php` — método `seedRolePermissions()`: matriz rol→permiso (`rbacbase.md` §5), separado de `seedPermissions()`
- [x] `database/seeders/RolesAndPermissionsSeeder.php` — rutina de corte: todo usuario con rol `operator` recibe también `sales` (idempotente, no duplica si ya lo tiene)
- [x] `app/Providers/AppServiceProvider.php` — `Gate::before` para `super_admin`, retorna `null` (no `false`) para el resto
- [x] `DashboardPolicy` — `hasRole()` → `can('dashboard.view' | 'reports.view' | 'reports.export')`
- [x] `TourPolicy` — `hasRole()` → `can('tours.*')`, sin tocar reglas de `guide` ya correctas
- [x] `TenantPolicy` — `hasRole()` → `can('tenant.*')`
- [x] `PromotionPolicy` — `hasRole()` → `can('promotions.*')`
- [x] `ReviewPolicy` — `hasRole()` → `can('reviews.*')` (no existía; se creó)
- [x] Cerrar huecos sin Policy propia: `BookingController`, `TourDateController`, `RevenueReportController`, `TourImageController::destroy`, `NewsletterController::index`, `ReviewController::index`, logística (routes/providers/hotels) — Policy nueva si el modelo la amerita, `authorize()` directo si no hay modelo dedicado
- [x] Form Requests → `can()`: `Admin/Team/UpdateMemberRoleRequest`, `InviteMemberRequest`, `Review/ModerateReviewRequest`, `RespondReviewRequest`, `Newsletter/SendCampaignRequest`
- [x] `App\Actions\Team\UpdateMemberRoleAction` (extraer de `TeamController` si no existe) — verifica pertenencia al tenant antes de tocar roles, lanza `CrossTenantAccessException` si no (cierre de B3)
- [x] Mismo chequeo de pertenencia en `suspend`/`reactivate` de `TeamController` (reusar `User::isActiveMemberOf`, no triplicar) — vive en `UpdateMemberStatusAction`, un solo sitio para los dos verbos
- [x] `routes/api.php` (líneas 93-135) y rutas `guide/*` — `->middleware('can:<permiso>')` por ruta según el mapa de `contracts.md` §1, retirar `tenant_admin.only`/`tenant_guide.only` de esas rutas (la membresía la sigue validando `EnsureTenantAdmin`/`EnsureTenantGuide` si aplica, no se borra la clase)
- [x] `AuthUserResource` — `tenantRole: string` → `permissions: array` (shape en `contracts.md` §3)
- [x] `HandleInertiaRequests` — comparte `auth.permissions`, deja de compartir `tenantRole`
- [x] Actualizar `tests/Feature/Rbac/CurrentAdminAccessMatrixTest.php` (Fase 0) celda por celda donde este feature cambia el resultado esperado
- [x] `tests/Feature/Rbac/PermissionCatalogSeederTest.php` — happy (38 permisos agrupados) + edge (seeder idempotente)
- [x] `tests/Feature/Rbac/RoleMigrationSeederTest.php` — happy (operator recibe sales) + failure (usuario sin operator no se toca)
- [x] `tests/Feature/Team/UpdateMemberRoleActionTest.php` — happy (mismo tenant) + failure (`CROSS_TENANT_ACCESS`, cross-tenant) + tenant isolation
- [x] 1 test de failure (403 sin permiso) por cada endpoint del mapa de `contracts.md` §1 que no lo tenga ya
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact --filter=Rbac`
- [x] `php artisan test --compact` (suite completa, no solo el filtro)

## Backend — Fase 2 (`montree-backend-dev`): bugs B4 y A3

> Barrera real de "quién ve la zona de viajero" y home de rol de `/dashboard`. El frontend
> de Fase 2 (menús por permiso) es UX sobre esta misma regla, no la reemplaza.

- [x] `app/Services/Auth/RoleHomeResolver.php` — extraer `LoginResponse::roleHome()` privado a un colaborador con tres llamadores; resuelve por permiso (`dashboard.view` → `/admin/dashboard`, `guide.schedule.view` → `/guide/schedule`, resto → `/`)
- [x] `app/Http/Responses/LoginResponse.php` — delega en `RoleHomeResolver`; se borran `roleHome()` y `tenantRole()` privados (última lectura de rol del archivo)
- [x] `app/Http/Middleware/RedirectStaffFromTravelerArea.php` + alias `traveler.only` en `bootstrap/app.php` — B4: el staff que entra a `/account/*` se redirige a su home de rol
- [x] `routes/web.php` — las 5 rutas `account.*` quedan bajo `traveler.only` (dentro de `tenant_member.only`, que no se tocó)
- [x] `app/Http/Controllers/RoleHomeRedirectController.php` — A3: `GET /dashboard` deja de redirigir fijo a `/account/bookings` y usa el mismo resolutor
- [x] `tests/Feature/Rbac/TravelerAreaAccessTest.php` — 21 tests: staff (admin/sales/operator/guide) fuera de las 5 rutas de `/account/*`, cliente adentro, `/dashboard` por rol, miembro sin roles, aislamiento por tenant
- [x] `tests/Feature/DashboardTest.php` — expectativa actualizada (`/account/bookings` → `/`) por el cambio de A3
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact` (511/511 verde)

## Backend — Fase 3A (`montree-backend-dev`): módulo de usuarios

> Amplía `/admin/users` (el prefijo real de `/admin/team`, ver nota de divergencia abajo).

- [x] `App\Services\Rbac\TenantRoleCatalog` — roles visibles/asignables de una agencia (base staff + propios del tenant), `isBase()`, `labelFor()`, `isReservedName()`, `nameTaken()`; `STAFF_ROLES` se muda acá desde `TeamController`
- [x] `App\Actions\Team\UpdateMemberRoleAction` — recibe `array<string>` de roles y hace `syncRoles()` (multi-rol real); conserva el chequeo de pertenencia (B3) y el de último admin
- [x] `Admin/Team/UpdateMemberRoleRequest` — `roles: array|min:1`, cada elemento validado contra el catálogo del tenant (`Rule::in`, no lista fija)
- [x] `Admin/Team/InviteMemberRequest` — mismo catálogo dinámico, para poder invitar directo a un rol propio
- [x] `TeamController::index` — filtros `status`/`role`, búsqueda `search` (nombre/email), `paginate()` con `per_page` (default 15, máx 100)
- [x] `App\Http\Resources\Team\TeamMemberResource` — `roles[]`, `status`, `invited_at`, `joined_at`, `last_login_at`
- [x] `App\Listeners\RecordLastLogin` — escucha `Illuminate\Auth\Events\Login`, `forceFill(['last_login_at' => now()])->save()`; ignora el segundo evento del handoff cross-host (`auth.handoff`)
- [x] `App\Actions\Team\SendTeamInvitationAction` — token + `TenantUserInvitationNotification`, compartida por super admin / invitación / reenvío (regla del 3)
- [x] `InviteMemberAction` — deja la membresía en `invited` y manda la invitación cuando la persona todavía no fijó contraseña
- [x] `App\Listeners\ActivateInvitedMemberships` — `PasswordReset` → la membresía `invited` pasa a `active`
- [x] `App\Actions\Team\ResendInvitationAction` + `POST /admin/users/{user}/resend-invitation` (`can:team.invite`)
- [x] `tests/Feature/Team/MultiRoleAssignmentTest.php` (7), `TeamDirectoryTest.php` (7), `InvitationTest.php` (5), `tests/Feature/Auth/LastLoginTest.php` (3)

## Backend — Fase 3B (`montree-backend-dev`): módulo de roles y permisos

- [x] `App\Services\Rbac\PermissionCatalog` — módulo + etiqueta de cada permiso, leyendo el catálogo de `RolesAndPermissionsSeeder::PERMISSIONS` (no lo reescribe)
- [x] `App\Http\Controllers\Api\V1\Admin\RoleController` — index/show/store/update/destroy
- [x] `App\Actions\Role\{Create,Update,Delete}TenantRoleAction`
- [x] `Admin/Role/StoreRoleRequest` + `UpdateRoleRequest` — nombre único por tenant (case-insensitive), no reservado, permisos ⊆ catálogo de 38
- [x] `App\Http\Resources\Role\{RoleResource,RoleDetailResource,RoleSummaryResource}`
- [x] `App\Exceptions\RoleException` — `BASE_ROLE_READ_ONLY` (403) y `ROLE_IN_USE` (409, con el conteo en el mensaje), registrada en `bootstrap/app.php`
- [x] `AppServiceProvider::configureRouteBindings()` — `{role}` se resuelve dentro del catálogo visible del tenant (404 si es de otra agencia)
- [x] `routes/api.php` — `Route::apiResource('roles')` bajo `can:team.role.update`; `routes/web.php` — página `admin/roles`
- [x] `tests/Feature/Admin/RoleManagementTest.php` (16: listado, detalle, creación, edición, borrado, base de solo lectura, rol en uso, aislamiento por tenant, 403 sin permiso)
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact` (549/549 verde)

## Backend — Remediación review (`montree-backend-dev`): los 2 bloqueos del NO-GO

- [x] `Admin/Team/TeamIndexRequest` — `authorize()` con `team.view` (el mismo permiso que gobierna la ruta), `status` con `Rule::enum(TenantMembershipStatus::class)`, `role` con `Rule::in(TenantRoleCatalog::assignableNames($tenant))`, `search` `max:100`, `per_page` `integer|min:1|max:100`; accessors `status()`/`role()`/`search()`/`perPage()` (default 15) espejo de `TourDateIndexRequest`
- [x] `TeamController::index` — consume `TeamIndexRequest`, sin `Request::query()` ni clamp a mano; se borró el `perPage()` privado
- [x] `tests/Feature/Team/TeamDirectoryTest.php` — 3 tests de failure: filtros fuera de catálogo (422 en `status`+`role`+`per_page`), `search` > 100, rol de otra agencia (422, no 200 silencioso)
- [x] `tests/Feature/Rbac/GuideRouteAccessTest.php` — 6º Given/When/Then de "Cierre de huecos de autorización": `admin` → `GET /guide/schedule` 200 con `data` vacío, y 403 sobre los viajeros de una salida ajena
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact` (558/558 verde)

## Frontend (`montree-frontend-dev`)

- [x] `resources/js/types/auth.ts` — `AuthUser.tenantRole: string` → `AuthUser.permissions: string[]`
- [x] `npm run types:check` (va a fallar en los 4 sidebars que leen `tenantRole` — esperado, se resuelve en Fase 2; documentar el fallo esperado en el reporte, no silenciarlo) — **no falló; ver nota `2026-08-16` (`montree-frontend-dev`) abajo: son 3 archivos, no 4, y ninguno rompe la compilación por dos razones acumuladas**

Nada más de frontend en este feature — composable `can()` y reescritura de sidebars quedan en Fase 2 (`spec.md` §"Out of scope").

## Frontend — Fase 2 (`montree-frontend-dev`): menú por permiso y bugs A1-A9

> Items 2.1, 2.2, 2.3, 2.5, 2.6 y A6-A9 de `planfase.md` Fase 2. 2.4 y 2.7 son
> backend (sección de arriba). Nada aquí autoriza: la UI solo deja de mostrar lo
> que el backend ya bloquea.

- [x] **2.1** `resources/js/composables/usePermissions.ts` — `can(permiso | permisos[])` ("alguno de") y `canAll()`, leyendo `auth.permissions` (la prop que existe siempre, también para invitados); tipo `PermissionCheck` en `types/auth.ts`
- [x] **2.2** `resources/js/config/navigation.ts` — constructor único: tabla declarativa `{ title, href, icon, anyOf?, requiresPanel?, exact? }` + funciones puras `buildNavSections()`, `buildPanelItems()`, `resolveHomeUrl()`, `resolveWorkspaceLink()`, `isStaff()`
- [x] **2.2** `resources/js/composables/useNavigation.ts` — envoltorio reactivo del constructor; único consumidor de `usePermissions` en la navegación
- [x] **2.2** `requiresPanel` aplica el gate de grupo `dashboard.view` ANTES del permiso del ítem (`contracts.md` §1): `guide` tiene `tours.view` y aun así no ve ningún ítem de `admin/*`
- [x] **2.2** Los 4 sitios con `switch` por rol pasan a consumir el constructor: `AppSidebar.vue`, `AdminSidebar.vue`, `UserMenuContent.vue`, `PublicLayout.vue` — cero lecturas de `tenantRole` en `resources/js`
- [x] **2.3 / A2** Logo del sidebar e ítem "Inicio" apuntan al home del rol (`resolveHomeUrl`, espejo exacto de `App\Services\Auth\RoleHomeResolver`)
- [x] **2.5 / A5** `isCurrentOrParentUrl` para marcar el ítem activo en subrutas, con `exact` para los destinos que son prefijo de sus hermanos (`/`, `/account`)
- [x] **2.6 / A4 / A1** El staff conserva su menú de administración fuera del panel (`/settings/*`, `/guide/*`) y ya no cae al menú de cliente
- [x] **A6** "Productos"/"Tours" contradictorios → `/admin/tours` es **Tours** y `/admin/departures` es **Salidas**, mismo nombre en los dos menús
- [x] **A7** `NavMain.vue` deja de rotular "Platform"; la etiqueta del grupo es un prop y `AdminNavMain.vue` (duplicado exacto) se borra
- [x] **A8** Submenú de `/settings/*` y sus breadcrumbs a español (Perfil / Seguridad / Apariencia)
- [x] **A9** `AppSidebarHeader.vue` deduce breadcrumbs del menú cuando la página no los declara — cubre las 12 páginas de `pages/Admin/` sin tocarlas
- [x] `npm run types:check` (6 errores, los 6 preexistentes de `AppHeader.vue`/`Notifications.vue`), `npx eslint .` (3 errores preexistentes), `npx tsc --noEmit` semántico limpio, `npm run build` verde
- [ ] Verificación en navegador — **no hecha**: sin herramienta de navegador en el entorno del agente. En su lugar, matriz de menú por rol ejecutada sobre las funciones puras del constructor (ver nota abajo)

## Frontend — Fase 3A (`montree-frontend-dev`): módulo de usuarios

> Pantalla `/admin/team` (`pages/Admin/Team/Index.vue`), que consume
> `api/v1/admin/users/*`. Nada de esto autoriza: la UI solo deja de mostrar lo
> que el backend ya bloquea (`team.invite` / `team.role.update` / `team.suspend`).

- [x] **Multi-rol**: el `<select>` de rol único se reemplaza por un diálogo con checkboxes (uno por rol asignable, incluidos los propios de la agencia); envía `{ roles: string[] }` a `PATCH /admin/users/{user}/role`
- [x] **Confirmación de cambios sensibles conservada** (promover a admin / quitarle el admin), en un diálogo aparte que al cancelar devuelve el borrador intacto al selector. La tercera regla anterior —degradar a `customer`— desaparece: `customer` ya no es asignable (divergencia ⚠️ del backend)
- [x] **Filtros + búsqueda + paginación**: `search` con debounce (350 ms), selects de `status` y `role`, controles de página; mismo patrón que `pages/Admin/Departures/Index.vue` (query params por Wayfinder, `meta` del paginador, "Limpiar filtros")
- [x] **Columna "Último acceso"**: `formatRelativeDate()` nuevo en `lib/format.ts` ("hace 3 días", fecha absoluta a más de un mes) y "Nunca" si `last_login_at` es `null`
- [x] **Botón "Reenviar invitación"** solo en filas `invited`, contra `POST /admin/users/{user}/resend-invitation`, con toast de confirmación
- [x] Estados de la pantalla: skeleton de carga, error con "Reintentar", vacío distinguiendo "sin filtros" de "sin resultados"
- [x] `types/team.ts` + `types/pagination.ts` (el shape del paginador de Laravel sale de `types/logistics.ts`, que lo reexporta)
- [x] `config/roles.ts` — etiquetas de los roles base (espejo de `UserRole::label()`) y juego asignable de respaldo
- [x] `pages/Admin/Departures/Index.vue` — el filtro de guías dejaba de funcionar con `roles[]`: pasa a leer la lista de roles del miembro
- [ ] Apunte #3 del equipo ("al agregar el usuario puedo poner…") — **PENDIENTE, sin tocar**: el formulario de invitación conserva sus 3 campos (email, nombre, rol). Lo único que cambió es de dónde salen las opciones de rol

## Frontend — Fase 3B (`montree-frontend-dev`): módulo de roles y permisos

- [x] `pages/Admin/Roles/Index.vue` — listado con "Roles del sistema" (badge "Solo lectura") separado de "Roles propios de la agencia", con conteo de permisos y de miembros por rol y botón "Crear rol"
- [x] `components/organisms/RoleFormDialog.vue` — un solo componente para crear / editar / ver: carga el detalle (`GET /admin/roles/{role}`), valida en espejo del Form Request (nombre obligatorio, `max:60`, ≥1 permiso) y guarda con POST o PATCH
- [x] `components/organisms/PermissionPicker.vue` — matriz de 38 permisos agrupada por módulo, con "seleccionar todo el módulo" en tres estados (vacío / mixto / completo) y deshabilitada entera para roles base
- [x] Eliminar rol propio con confirmación; deshabilitado y con tooltip explicativo cuando `users_count > 0`
- [x] `config/permissions.ts` — agrupado y etiquetas de respaldo, espejo de `RolesAndPermissionsSeeder::PERMISSIONS`; la fuente real es `meta.available_permissions` del listado
- [x] `types/role.ts` — `RoleListItem`, `RoleDetail`, `RoleSummary`, `PermissionSummary`, `RoleListResponse`
- [x] **Nav**: ítem "Roles y permisos" en `config/navigation.ts`, junto a "Equipo", gateado por `team.role.update` + el gate de panel
- [x] **Wayfinder**: cero URLs a mano — `@/routes/admin/roles` y `@/actions/.../Api/V1/Admin/RoleController` (`php artisan wayfinder:generate --with-form`)
- [x] `npm run types:check` (6 errores, los 6 preexistentes), `npx eslint .` (2 errores preexistentes; el tercero vivía en la pantalla de equipo reescrita), `vue-tsc@3` semántico sin errores nuevos, `npm run build` verde
- [x] Verificación en navegador (Playwright headless sobre `demo.montree.test`): golden path y edge cases de las dos pantallas, cero errores de consola — detalle en la nota de abajo

## DB (`montree-db-architect`, solo si hay cambios de schema)

- No aplica. Sin migraciones nuevas (ver `plan.md` §2 Migrations).

## Review (`montree-reviewer`)

- [ ] Tests pasan (suite completa, no solo `Rbac`)
- [ ] Pint pasa
- [ ] Cero ocurrencias de `hasRole(` en `app/Policies`, `app/Http/Requests`, `app/Http/Middleware` salvo el chequeo de `super_admin` (criterio de salida de `planfase.md` Fase 1)
- [ ] `permissions` = 38 filas, `role_has_permissions` poblada, `roles` = 6, verificado por query real, no por lectura de código
- [ ] Operador → `/admin/team` responde 403, no 200 (verificado con test, no manual)
- [ ] Spec (`spec.md`) cubierta 100%, incluidos los "Given/When/Then" de "Cierre de huecos de autorización" — *eran 4; desde el `2026-08-16` son **6**: se agregó el de `guide` → 403 en `admin/*` (gate `can:dashboard.view`) y el 4º se partió en dos (`operator` → 403 / `admin` → 200 con agenda vacía)*
- [ ] Constitución respetada (§3.2 capas, §2 principios de diseño)
- [ ] Sin código muerto: `EnsureTenantAdmin`/`EnsureTenantGuide` no quedan huérfanas sin uso si se retiraron de todas las rutas admin — confirmar que siguen usadas donde corresponde o se documenta por qué no
- [ ] N+1 check en `AuthUserResource`/`HandleInertiaRequests` (permisos ya cacheados por Spatie, confirmar que no se re-consultan por request)
- [ ] Reporte final con go/no-go

---

## Bloqueos / Decisiones pendientes

- [ ] Ninguno bloqueante para arrancar. 0.4 (inventario `operator` en demo/prod) es requisito de **despliegue**, no de implementación — ver `plan.md` §6 Riesgos.
- [x] ~~**Para `montree-spec-updater`** (4 divergencias detectadas al implementar; ninguna bloquea, todas están implementadas con el criterio que se indica)~~ — **resueltas el `2026-08-16` por `montree-spec-updater`**; las 4 quedaron reflejadas en la documentación, ninguna requirió tocar código:
  1. ~~**El catálogo tiene 38 permisos, no 37.** `spec.md` §"Catálogo de permisos" y `rbacbase.md` §4 dicen "37" pero enumeran 38 (3+6+6+2+3+4+2+3+4+3+2). Manda la enumeración. Corregir el número en ambos documentos.~~ → hecho: `spec.md` (§Descripción, §Catálogo, matriz, §Datos requeridos), `contracts.md` §2, `plan.md` §1/§4/§5 y `rbacbase.md` §4/§6 dicen **38**. Enumeración intacta.
  2. ~~**`operator` no lleva `bookings.*` ni `reviews.view`.** `rbacbase.md` §5 se los da; `spec.md` … se los quita. Se siguió `spec.md`. Corregir `rbacbase.md` §5 o registrar la excepción.~~ → hecho: `rbacbase.md` §5 corregida a favor de `spec.md` (3 celdas ✅→—), con el contenido anterior conservado en una tabla de deltas y anotado en su §9 Changelog.
  3. ~~**`admin` sí tiene `guide.travelers.view`.** `rbacbase.md` §5 marca esa celda con `—` para admin…~~ → hecho: celda corregida a ✅ en `rbacbase.md` §5. Además se ajustó el 4º Given/When/Then de `spec.md` §"Cierre de huecos": `admin` sobre `GET /guide/schedule` recibe **200 con agenda vacía**, no 403 — el filtro es por propiedad (`guide_id`), no por permiso. Decisión ratificada; deuda de "permisos scoped para `guide`" registrada en `spec.md` y `plan.md` §7 para un feature futuro.
  4. ~~**Falta en `contracts.md` §1 el gate de grupo `can:dashboard.view`.**…~~ → hecho: fila + nota en `contracts.md` §1 (gate de entrada de **todo** `admin/*`, api y web, evaluado antes del `can:` de cada ruta), marcado **BREAKING** en "Cambios al contrato", replicado en `plan.md` §2 Rutas y como criterio de aceptación nuevo en `spec.md` (`guide` → 403 en `admin/*`).

## Notas durante implementación

- `2026-08-18` (`montree-backend-dev`, remediación de los 2 bloqueos del NO-GO): suite **558/558 verde, 1970 assertions** (partía de 553; +5 tests), Pint verde. Nada más de RBAC se tocó.
  - **El permiso de `TeamIndexRequest::authorize()` es `team.view`**, el mismo que ya tenía la ruta `GET /admin/users` en `routes/api.php` (`middleware('can:team.view')`). No se creó ninguno: el catálogo de 38 queda intacto. La doble puerta (middleware + `authorize()`) es la misma redundancia que ya tienen `TourDateIndexRequest` y `AdminBookingIndexRequest`, y sirve para que el Form Request siga siendo seguro si mañana se monta en otra ruta.
  - **`TenantRoleCatalog::assignableNames(Tenant $tenant): array<string>` calzó tal cual**, sin ajustes: es la misma llamada que ya hacían `UpdateMemberRoleRequest` y el propio `TeamController::index`. El filtro `role` valida contra ella, así que un rol propio de la agencia (creado en runtime desde `/admin/roles`) sigue siendo un filtro válido y el de **otra** agencia da 422, no un listado vacío.
  - **Cambio de comportamiento observable**: `?status=nope`, `?role=zzz` o `?per_page=500` pasan de 200-silencioso (filtro ignorado / `per_page` clampeado a 100) a **422**. La UI (`pages/Admin/Team/Index.vue`) no manda esos valores — omite el parámetro cuando el selector está en "all" (`buildQuery()`), y `per_page` es la constante 15 —, así que el contrato con el frontend no cambia.
  - **`sometimes|nullable` en los 4 filtros** a propósito: `nullable` cubre un `?status=` vacío que hoy nadie manda pero que un enlace pegado a mano sí produce, y evita convertir en 422 algo que antes era "sin filtro".
  - **El catálogo de roles se consulta solo si vino el filtro `role`** (`roleRules()`, la única lógica condicional del `rules()`): `rules()` se arma en cada request y `Rule::in` necesita la lista materializada, así que hacerlo siempre le sumaba una query al listado sin filtrar. **Query count medido con 13 miembros: 11 sin filtros — idéntico al que midió Fase 3A — y 7 con `role`+`status`+`search`+`per_page`** (menos porque el caché de permisos de spatie ya viene tibio). Sin N+1.
  - **La búsqueda se fue a un método privado del controller** (`searchByNameOrEmail`, pasado como first-class callable a `when()`) para que `index` quede en el orden de líneas de sus hermanos. El resto de la query no cambió: sigue el `where('tenant_user.status', ...)` calificado, con su WHY sobre `wherePivot()` dentro de un `when()`.
  - **El 6º Given/When/Then ya se comportaba bien; lo que faltaba era la red.** Los dos tests nuevos pasaron en verde sin tocar `GuideController`, confirmando que `admin` pasa el `can:guide.schedule.view` y se queda sin datos por propiedad (`guide_id`), y que `travelers` corta con 403 sobre una salida ajena. Si algún día se le quitan a `admin` los dos permisos de guía, estos dos tests son los que se pondrán rojos y hay que actualizar junto con `spec.md`.

- `2026-08-16` (`montree-frontend-dev`, Fase 3A + 3B frontend): pantallas de equipo y de roles. Decisiones que no estaban en la instrucción:
  - **Las 4 divergencias ⚠️ del backend se resolvieron a favor del código, no del contrato.** La instrucción traía el contrato previo (`/admin/team`, `roles: string[]`, sin catálogo de permisos); las rutas y resources reales llegaron a mitad de la tarea. El frontend consume lo implementado: `api/v1/admin/users/*`, `roles: [{id,name,label,is_base}]` normalizado a `{name,label}` para pintar y a `string[]` para enviar, y `meta.available_permissions` como catálogo del selector. **El espejo local (`config/permissions.ts`) no es la fuente**: se usa solo si esa respuesta no trae catálogo, y `groupPermissions()` prefiere el `module_label` del backend, así que agregar un permiso 39 no exige tocar el frontend.
  - **`customer` fuera del selector, y con él la tercera regla de confirmación.** La pantalla vieja confirmaba tres cambios sensibles: promover a admin, quitar admin y degradar a cliente. El tercero ya no puede ocurrir (`customer` no es asignable, mínimo 1 rol), así que se borró en vez de dejar código muerto; los otros dos siguen con el mismo texto. Si producto recupera la degradación, el sitio donde vuelve es `sensitiveMessage()`.
  - **Un diálogo, no un dropdown, para el multi-rol**, y **un solo componente** (`RoleFormDialog`) para crear / editar / ver permisos. El diálogo de confirmación se mantiene como paso aparte (lo pedía la instrucción): al abrirse cierra el selector y, si se cancela, lo reabre con el borrador intacto — dos diálogos abiertos a la vez pelean por el foco.
  - **El picker de permisos son botones `role="checkbox"`, no `<label for>`.** El `Checkbox` de `ui/` es un `<button>` de reka, y un `<label for>` no dispara clics sobre un botón; el patrón de fila-botón con el checkbox decorativo (`aria-hidden`, `tabindex="-1"`) ya se usaba en `TourDateFormDialog`. Cada fila queda enfocable con teclado y con `aria-checked` real (`mixed` para el módulo a medias). **El indicador del estado mixto se pinta con un guion**: el `Checkbox` siempre dibuja un tilde, así que "2 de 3 permisos" se veía idéntico a "los 3" — encontrado mirando la pantalla, no el tipo.
  - **`types/pagination.ts` es nuevo**: el shape del paginador de Laravel vivía en `types/logistics.ts` porque el primer listado paginado fue el de salidas. Se extrajo y `logistics.ts` lo reexporta, así que ningún import existente cambió.
  - **`pages/Admin/Departures/Index.vue` se tocó sin estar en el encargo**: filtraba guías con `member.role === 'guide'`, campo que el backend de Fase 3A eliminó. Sin ese arreglo el selector de guía de una salida quedaba vacío. Es el único consumidor del listado de equipo fuera de la pantalla de equipo (verificado por grep).
  - **Hallazgo de tooling 1 — `php artisan wayfinder:generate` sin `--with-form` rompe la app.** `vite.config.ts` genera con `formVariants: true`; el comando de artisan por defecto NO, y al regenerar para tomar las rutas nuevas desaparecieron los `.form` de todas las acciones: login, registro, recuperación de contraseña y ajustes quedaron con `TypeError: store.form is not a function` (visto en el navegador, no por el gate de tipos). El comando correcto en este repo es `php artisan wayfinder:generate --with-form`. Vale la pena dejarlo escrito en `local-setup.md`.
  - **Hallazgo de tooling 2 — el gate de tipos sigue ciego, y esta vez costó.** `vue-tsc@2` (el del repo) no reporta errores semánticos, confirmado otra vez: un `aria-checked` con un tipo imposible pasó `npm run types:check` y `npm run build`. Se verificó con `vue-tsc@3.0.9` instalado **fuera del repo** (`/tmp`, sin tocar `package.json`): encontró 1 error real en código nuevo (corregido) y 10 preexistentes en `Tour/Create.vue`, `Tour/Edit.vue` y `Promotion/Index.vue`. Sigue en pie la recomendación de Fase 1 de subir `vue-tsc` a la línea 3.x.
  - **Verificación en navegador, esta vez sí** (Playwright headless contra `demo.montree.test`, sesión de `admin@demo.montree.test`): listado de roles, detalle de rol base de solo lectura (11 módulos, 49 checkboxes, sin botón Guardar), creación con validación y selección por módulo, edición, eliminación con confirmación, tooltip del botón deshabilitado con 1 miembro asignado, multi-rol en equipo con su confirmación y su cancelación, búsqueda con debounce (1 sola request), estado vacío, "Limpiar filtros", "Último acceso" relativo y "Nunca". Cero errores de consola. Antes se recorrió la API con `curl` (crear/leer/editar/borrar rol, asignar dos roles, reenviar invitación, y los rechazos: nombre reservado 422, rol base 403, rol en uso 409, roles vacíos 422). **El estado de la base local quedó como estaba**: el rol y el usuario de prueba se borraron.
  - **`useApi` pisa el mensaje de los 403.** Reemplaza el cuerpo por "No tienes permisos para esta acción", así que `BASE_ROLE_READ_ONLY` —que el backend agregó justamente para distinguirlo— nunca llega a la UI. Hoy no se nota (la UI no ofrece guardar un rol base), pero si mañana se ofrece, hay que dejar pasar el `message` del backend cuando venga con `error_code`.

- `2026-08-16` (`montree-backend-dev`, Fase 3A + 3B): módulo de usuarios y módulo de roles. Suite **549/549 verde, 1951 assertions** (partía de 511; +38 tests nuevos), Pint verde. Decisiones que no estaban en la instrucción, y **divergencias respecto al contrato que se le pasó al frontend** (marcadas ⚠️):
  - ⚠️ **El prefijo real es `/admin/users`, no `/admin/team`.** El contrato hablaba de `POST /admin/team/{user}/resend-invitation`; las rutas de este repo son `api/v1/admin/users/*` (`api.v1.admin.users.*`) desde F014. El endpoint nuevo quedó en `POST /api/v1/admin/users/{user}/resend-invitation` (`TeamController::resend`) por consistencia con las 5 rutas hermanas. La página web sí es `/admin/team`.
  - ⚠️ **`TeamMemberResource` ya no expone `role` (singular).** El listado devuelve `roles: [{id, name, label, is_base}]`. Se quitó en vez de dejarlo como alias porque el campo mentía apenas un miembro tiene dos roles (`getRoleNames()->first()` era arbitrario). El `TeamMemberPayload` del frontend ya lo trata como opcional.
  - ⚠️ **`customer` dejó de ser asignable desde el equipo.** `STAFF_ROLES` (admin/sales/operator/guide) es ahora la única fuente, así que "bajar a cliente" —que la pantalla vieja ofrecía— ya no existe: un miembro del equipo necesita ≥1 rol de equipo. Para sacarle acceso a alguien se lo suspende. Si producto quiere recuperar la degradación, hay que decidir si es "quitar la membresía" o "reasignar a customer", no volver a meter `customer` en el selector de roles.
  - ⚠️ **`GET /admin/roles` devuelve además `meta.available_permissions`** (los 38 con `slug`/`module`/`module_label`/`label`). No estaba en el contrato, pero sin eso el formulario de creación no puede pintar el selector y habría que inventar un segundo endpoint. Es aditivo.
  - ⚠️ **`module_label`** se agregó a cada permiso (además de `slug`/`module`/`label`) para que el front no duplique la traducción de los 11 módulos.
  - **El estado `invited` existía en el enum pero no lo escribía nadie**, y la invitación del equipo **no mandaba ningún correo** (`InviteMemberAction` marcaba `active` y no notificaba: la persona nunca se enteraba y no podía entrar, porque su contraseña es un `Str::random(40)`). Sin cerrar eso, "reenviar invitación" no tenía qué reenviar. Ahora: invitar a alguien que nunca fijó contraseña → membresía `invited` + `TenantUserInvitationNotification` (la misma del alta por super admin, extraída a `SendTeamInvitationAction`); fijar la contraseña (`PasswordReset`) → `active` con `joined_at`. Invitar a alguien que ya tiene cuenta sigue entrando como `active`: no hay nada que aceptar. `LoginResponse` ya tenía el mensaje "Tu invitación está pendiente de aceptación" esperando este estado.
  - **`ActivateInvitedMemberships` activa TODAS las membresías `invited` del usuario**, no solo la de la agencia del enlace. `invited` significa "no probó que ese correo es suyo"; el enlace de recuperación lo prueba, y esa prueba no es por agencia.
  - **El handoff cross-host se descarta por nombre de ruta** (`auth.handoff`), no por guard ni por sesión: los dos eventos `Login` son idénticos salvo el request que los origina. Verificado a la inversa (quitando el guard el test falla), y el test viaja solo 30s entre los dos porque el token de handoff vive 60s.
  - **`wherePivot()` dentro de un `when()` no funciona.** La relación delega `when()` en el query builder, así que la clausura recibe el *Builder*: ahí `wherePivot('status', X)` cae en el manejo de `whereXxx` dinámico y filtra por una columna llamada `pivot` (0 resultados, sin error). El filtro de estado usa `where('tenant_user.status', ...)` calificado. Vale para cualquier filtro futuro sobre el pivote.
  - **Sin `RolePolicy`.** El gate es uniforme (`can:team.role.update` en la ruta, mismo criterio que logística/reportes en Fase 1) y `Spatie\Permission\Models\Role` no es un modelo del dominio. Lo único que no es "tener el permiso" —el rol base es de solo lectura— es regla de negocio y vive en las Actions como `RoleException::baseRoleIsReadOnly()` (403, `BASE_ROLE_READ_ONLY`). **No se reusó `INSUFFICIENT_PERMISSION`** a propósito: a quien edita no le falta el permiso, el recurso es inmutable; con el código genérico el frontend no podía distinguir "pedile permiso a tu admin" de "creá un rol propio".
  - **El aislamiento de `{role}` es un route binding**, no un chequeo en el controller: `roles` no es tenant-scoped (no tiene global scope), así que el binding implícito habría resuelto el rol de cualquier agencia por id. Un rol de otra agencia da **404**, no 403 (api-conventions §4: "no existe o no en este tenant").
  - **`users_count` de un rol se cuenta scopeado al tenant.** Los roles base son filas globales compartidas por las 8 agencias: sin `where model_has_roles.tenant_id`, "admin" mostraría los admins de la plataforma entera.
  - **Nombre de rol propio: único por agencia sin distinguir mayúsculas, comparado con `lower(name)` a mano.** MySQL ya lo hace por collation, pero SQLite (la BD de la suite) no: sin el `lower()` explícito el test de duplicados pasaba en verde y producción se comportaba distinto. Reservados los 6 nombres de `UserRole`, no solo los 4 asignables.
  - **`permissions` exige `min:1`** al crear/editar un rol propio. Un rol sin permisos es indistinguible de no tener rol y ensucia el listado; no estaba en el contrato.
  - **Query count medido** en `GET /admin/users` con 13 miembros: **11 queries, constantes** (los roles de todos los miembros salen en un solo eager load). 5 de las 11 son el warm-up de permisos de spatie + la resolución del tenant, iguales en cualquier request del panel.
  - **`GET /admin/roles` no pagina**: una agencia tiene 4 roles base + los propios que cree. Devuelve `{data, meta}`, no el shape paginado.
  - **Ruta web `admin/roles`** (`Route::inertia('roles', 'Admin/Roles/Index')`, gate `can:team.role.update`) agregada acá aunque el item hablaba solo de `routes/api.php`: sin ella la página del frontend no tiene URL.

- `2026-08-16` (`montree-frontend-dev`, Fase 2 frontend): menú por permiso. Decisiones que no estaban en la instrucción:
  - **El menú se reduce a datos + funciones puras.** `config/navigation.ts` no importa Vue: `buildNavSections(can)` recibe el chequeo de permisos y devuelve el menú. `useNavigation()` solo lo envuelve en `computed`. Motivo práctico además del diseño: el gate de tipos de este repo no detecta errores semánticos (hallazgo de Fase 1) y no hay runner de tests de JS, así que la única verificación posible era ejecutar la lógica; siendo pura se pudo correr contra la matriz real del seeder sin montar Vue ni navegador. Resultado por rol (admin/sales/operator/`sales+operator`/guide/customer/invitado) en el reporte de la tarea.
  - **`config/` es un directorio nuevo**, no listado en la constitución §4.1. La tabla de navegación no es un "util puro" (`lib/`) ni un tipo (`types/`) ni lógica reactiva (`composables/`): es configuración declarativa. Si el revisor prefiere no ampliar la estructura, el archivo se mueve a `lib/navigation.ts` sin cambiar una línea de su contenido.
  - **`AdminNavMain.vue` se borró en vez de arreglarlo.** El item 2.5 pedía cambiar `isCurrentUrl` → `isCurrentOrParentUrl` en su línea 27, pero era copia carácter a carácter de `NavMain.vue` salvo la etiqueta del grupo (que es justo el bug A7). Quedó un solo renderer con la etiqueta como prop; el arreglo de A5 se hizo una vez y sirve para los dos sidebars.
  - **`exact` como propiedad del ítem de menú.** Con matching por prefijo, `/` marca activo todo el menú y `/account` se marca junto con `/account/bookings`. Se declara `exact: true` en esos dos destinos en vez de tratar `/` como caso especial dentro del renderer.
  - **La zona de viajero se oculta al staff** (`travelerOnly` en la sección "Mi cuenta" + `isStaffMember` en `UserMenuContent` y `PublicLayout`). Es consecuencia directa de B4/2.7, cerrado en paralelo por `montree-backend-dev` mientras corría esta tarea: con `traveler.only` montado, "Mis Reservas" para un admin es un enlace que rebota a `/admin/dashboard`. **Si 2.7 se revierte, hay que quitar `travelerOnly: true` de `accountSection` y los dos `v-if="!isStaffMember"`** — está aislado en esos tres puntos. Efecto lateral: A4 ("al operador le falta Mis Reservas en la zona de cuenta") queda cerrado por la vía opuesta a la que describía el bug — el operador no tiene zona de cuenta; lo que se le arregló es que conserva el menú del panel en `/settings/*`.
  - **A9 se resolvió en `AppSidebarHeader.vue`, no página por página.** Las 12 páginas de `pages/Admin/` no declaran breadcrumbs; en vez de escribir 12 `defineOptions`, la barra deduce "home del rol / sección actual" del mismo menú (match más largo gana) cuando la página no manda los suyos. Lo que la página declare sigue mandando.
  - **`TenantRole` se borró de `types/auth.ts`.** Fase 1 lo dejó "para que Fase 2 decida": ya no lo importa nadie y desde el rol `sales` la unión era incorrecta. Los strings de rol que quedan vivos están en `pages/Admin/Team/Index.vue`, que es alcance de Fase 3.
  - **Sin verificación en navegador**: el entorno de este agente no tiene herramienta de navegador ni el sitio levantado en un host de tenant. Cubierto con: matriz de menú por rol sobre las funciones puras, `npm run build` verde (compila las 4 plantillas reescritas), `npx tsc --noEmit` limpio y el test `InertiaAuthUserPropTest` que confirma que `auth.permissions` llega. Falta un par de ojos sobre el render real.
  - **Deuda que se ve desde acá, no tocada:** `AppHeader.vue` + `layouts/app/AppHeaderLayout.vue` están muertos (ningún layout los importa desde `app.ts`) y ahí viven 3 de los 6 errores preexistentes de `types:check`; las páginas de `pages/settings/*` siguen en inglés por dentro (A8 pedía el submenú, no el contenido).
- `2026-08-16` (`montree-backend-dev`, Fase 2 backend): bugs **B4** y **A3** cerrados. Suite completa **511/511 verde, 1825 assertions** (partía de 490; +21 del test nuevo); Pint verde. Decisiones que no estaban escritas en la instrucción:
  - **`roleHome()` se resuelve por permiso, no por rol.** La instrucción decía "reusá `roleHome()`, ya resuelve admin/operator/sales → `/admin/dashboard`", pero el `match` por rol solo contemplaba `admin`, `operator` y `guide`: un `sales` (rol nuevo de Fase 1) caía en `default` y terminaba en la landing pública, tanto al loguearse como al ser expulsado de `/account/*`. En vez de agregarle un case, el resolutor pregunta `dashboard.view` y después `guide.schedule.view`. Tres razones: (1) F018 sacó `hasRole()` del backend y esto lo reintroducía; (2) el destino queda garantizado como una ruta que el usuario puede abrir, porque son exactamente los gates de `admin/*` y `guide/*` — con roles propios por tenant (Fase 3B) el `match` por nombre habría mandado gente a un 403; (3) `tenantRole()` leía **el primer rol** de la lista, y desde el corte de Fase 1 los `operator` existentes tienen dos (`operator` + `sales`). Cambio de comportamiento observable: solo el `sales`, que ahora aterriza en el panel.
  - **El corte vive en un middleware propio (`traveler.only`), no en `EnsureTenantMember`.** Son dos preguntas distintas: "sos miembro activo" (sigue igual) y "esta zona es tuya". `EnsureTenantMember` no se tocó; el middleware nuevo corre después y por eso puede dar por hecho el contexto de equipo de spatie y el caso `super_admin` (ya redirigido a `/`).
  - **`GET /dashboard` pasó de closure a `RoleHomeRedirectController`** (controller de acción única, constitución §3.2) para poder inyectar el resolutor. La URL y el nombre de ruta no cambian: Wayfinder no necesita regenerarse.
  - **`DashboardTest::test_active_members_are_redirected_to_account_bookings` cambió de expectativa** (`/account/bookings` → `/`). Es consecuencia directa de A3: un miembro activo sin permisos de panel es un viajero y su home es la landing de la agencia, igual que al loguearse. Renombrado a `..._without_panel_permissions_are_redirected_to_the_agency_home`.
  - **Fuera de alcance a propósito, para que Fase 2 frontend lo sepa:** (1) `GET /bookings/{bookingNumber}` (`booking.show`) NO quedó bajo `traveler.only` — el corte pedido era `/account/*`, y esa pantalla es el detalle de reserva al que llega también quien acaba de comprar; (2) los endpoints JSON `/api/v1/account/*` siguen abiertos al staff — un redirect 302 no es respuesta válida para XHR y bloquearlos con 403 excede lo pedido, pero hoy son la puerta de atrás de la misma data; (3) `resources/js/pages/Dashboard.vue` sigue existiendo aunque ya nadie la renderiza (borrarla es item 2.4 de frontend).
- `2026-08-16` (`montree-backend-dev`): sección Backend completa. Suite: **490 tests / 1786 assertions verde**; `--filter=Rbac`: 61/349 verde; Pint verde. Decisiones de implementación que no estaban en `plan.md`:
  - **`can:dashboard.view` como gate de grupo de `admin/*`** (api y web). La matriz le da a `guide` los permisos `tours.view` y `departures.view` (para sus propias salidas) y `contracts.md` §1 mapea `GET /admin/tours` → `tours.view`: aplicados al pie de la letra, el guía entraba al panel de producto. `dashboard.view` es exactamente el conjunto "quien trabaja en el panel" (admin, vendedor, operador; no guía), así que se usó como llave de entrada en vez de inventar un permiso 39 que rompería el conteo del catálogo. Cada ruta conserva además el `can:` de su módulo.
  - **`EnsureTenantAdmin`/`EnsureTenantGuide` siguen montados, sin el chequeo de rol.** Se les quitó el `hasAnyRole()` (era la causa de B1 y B2) pero NO se retiraron de las rutas: un miembro suspendido conserva sus filas en `model_has_roles`, así que sin la validación de membresía activa seguiría pasando el `can:` después de que el equipo lo suspendiera. Quedaron con cuerpos casi idénticos; con dos usos la constitución §2.1 no pide abstraerlos.
  - **`reports.view` vs `reports.export` sobre una sola ruta.** Este repo tiene `GET /admin/reports/revenue`, no las dos rutas de `contracts.md` §1. `ExportRevenueRequest::authorize()` pide `reports.export` cuando `format=csv` y `reports.view` en el resto: el vendedor lee el reporte pero no baja el CSV.
  - **`TourPolicy::archive` → `tours.delete`.** El catálogo no tiene `tours.archive` y archivar es la otra salida destructiva; `tours.delete` es el mismo conjunto de roles que aplicaba antes (`isAdmin`).
  - **403 normalizado en `AccessDeniedHttpException`, no en `AuthorizationException`.** El handler de Laravel convierte la segunda en la primera *antes* de consultar los callbacks de `withExceptions`, así que enganchar en `AuthorizationException` no dispara nunca. Solo se toca la respuesta JSON (`error_code: INSUFFICIENT_PERMISSION`); las páginas Inertia conservan el 403 por defecto.
  - **`tests/TestCase.php` siembra `RolesAndPermissionsSeeder`** (`protected $seeder`) para toda la suite: los permisos son datos de referencia, no fixtures, y sin ellos cualquier `can()` responde false. Corre una vez por proceso dentro de `migrate:fresh`, no por test.
  - **`sales` se agregó a los `in:` de `InviteMemberRequest`/`UpdateMemberRoleRequest` y a `TeamController::STAFF_ROLES`**, y `DemoTenantSeeder` siembra `sales@demo.montree.test`. Sin eso el rol nuevo no era asignable ni visible por ningún camino que no fuera el seeder de corte.
  - **`User::belongsToTenant()`** (pertenencia, no membresía *activa*) es el chequeo compartido por `UpdateMemberRoleAction` y `UpdateMemberStatusAction`. `isActiveMemberOf` no servía: `reactivate` exige justamente que el objetivo NO esté activo.
  - **Query count medido** en `GET /admin/dashboard` (Inertia, admin): 10 queries, ninguna en loop. 3 de ellas son la misma lectura de `roles` (la repiten `Gate::before`, el middleware de membresía y `AuthUserResource`, porque `loadRolesForTeam()` invalida la relación a propósito); 2 son el warm-up del caché de permisos de Spatie, una vez por request. Sin N+1; candidato a memoizar por request si alguna vez pesa.
- `2026-08-16` (`montree-frontend-dev`): sección Frontend completa (`resources/js/types/auth.ts`, único archivo tocado). Hallazgos que Fase 2 necesita saber:
  - **`npm run types:check` NO falló, ni antes ni después del cambio: 6 errores, los mismos 6 de la base, todos `TS1109`/`TS1128` preexistentes** en `resources/js/components/AppHeader.vue:273-274` y `resources/js/pages/Account/Notifications.vue:127` (un `as` dentro de una interpolación `{{ }}`, que el parser de templates no acepta). Ninguno relacionado con F018.
  - **`vue-tsc` no está reportando errores semánticos en este repo.** `vue-tsc@2.2.x` con `typescript@5.9.3` solo emite diagnósticos de sintaxis: un `export const x: number = 'texto'` en un `.ts` incluido por `tsconfig` pasa `npm run types:check` y `npx tsc --noEmit` lo reporta al instante (`TS2322`). Es decir, el gate de tipos del proyecto hoy es un check de sintaxis. **Riesgo para Fase 2**: la reescritura de sidebars no tiene red de tipos. Arreglo: subir `vue-tsc` a la línea 3.x (compatible con TS 5.9) — fuera de scope de F018, requiere aprobación de dependencia.
  - **Aunque el compilador funcionara, quitar `tenantRole` casi no rompe nada**, porque `User` tiene `[key: string]: unknown`: `user.tenantRole` sigue tipando (como `unknown`) en vez de dar "property does not exist". Verificado con `tsc` real, patrón por patrón: `AppSidebar.vue:45-46` (`?? null` hacia `computed<TenantRole | null>`) sí da `TS2322 Type '{} | null' is not assignable to type 'TenantRole | null'`; `UserMenuContent.vue:40,48` (`=== 'admin'`) **no da error** (TS permite comparar `unknown`); `PublicLayout.vue:24` **no da error** (tiene un `as TenantRole | null | undefined` que lo silencia). Traducción: en Fase 2 hay que ir a los 3 archivos a mano, la lista no la da el compilador — y hasta entonces esos 3 leen `undefined` en runtime (sidebar de guía/operador cae al menú de cliente, y el enlace "Panel de la agencia" desaparece). Son **3 archivos, no 4**.
  - **`TenantRole` se dejó exportado** en `auth.ts` aunque ya no lo use `User`: `AppSidebar.vue` y `PublicLayout.vue` lo importan y borrarlo agregaría errores de import a archivos de Fase 2 sin ganancia. Fase 2 decide si el tipo sobrevive.
  - **`Auth` también recibió `permissions: string[]`**, además de `User.permissions`. No estaba escrito en el item, pero `HandleInertiaRequests` comparte las dos (`auth.user.permissions` y `auth.permissions`) y `tests/Feature/Auth/InertiaAuthUserPropTest.php` asegura ambas; el composable `can()` de Fase 2 va a leer la de arriba.
- `2026-08-16` (`montree-spec-updater`): alineación de la documentación con lo implementado en Fase 1, tras el GO del revisor. Editados `spec.md`, `contracts.md`, `plan.md`, `tasks.md` y `rbacbase.md` (kit p2p-kits). Las 4 divergencias de "Bloqueos / Decisiones pendientes" quedaron tachadas arriba con el detalle de dónde se reflejó cada una. **Cero cambios de código**: toda la documentación se movió hacia el comportamiento ya implementado, no al revés. Único punto donde la spec funcional cambió de intención (no solo de redacción): `admin` sobre `GET /guide/schedule` pasa de "403 esperado" a "200 con agenda vacía, filtrado por propiedad" — ratificado como decisión de producto, con la deuda de permisos scoped anotada.
- `2026-08-16` (Claude principal): `contracts.md`/`plan.md`/`tasks.md` escritos al cerrar Fase 0 completa, corrigiendo que 0.2 se había marcado hecha con solo `spec.md`. Rama `feature/F018-rbac` recreada desde `develop` (estaba mal creada desde `main`, que quedó 9 commits atrás). Ningún seeder se tocó todavía — `RolesAndPermissionsSeeder.php` sigue sin modificar; poblarlo es la primera tarea de implementación de esta lista, no algo ya hecho.
