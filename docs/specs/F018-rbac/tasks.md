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

## Frontend (`montree-frontend-dev`)

- [x] `resources/js/types/auth.ts` — `AuthUser.tenantRole: string` → `AuthUser.permissions: string[]`
- [x] `npm run types:check` (va a fallar en los 4 sidebars que leen `tenantRole` — esperado, se resuelve en Fase 2; documentar el fallo esperado en el reporte, no silenciarlo) — **no falló; ver nota `2026-08-16` (`montree-frontend-dev`) abajo: son 3 archivos, no 4, y ninguno rompe la compilación por dos razones acumuladas**

Nada más de frontend en este feature — composable `can()` y reescritura de sidebars quedan en Fase 2 (`spec.md` §"Out of scope").

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
