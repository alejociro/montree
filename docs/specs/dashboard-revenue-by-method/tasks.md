# Ingresos por medio de pago — Tasks

> Derivado de `plan.md`. Sin sección DB: no hay columnas nuevas, solo cambian
> los valores de `payments.gateway`.

---

## Backend (`montree-backend-dev`)

### Enum
- [x] `PaymentGateway`: `PlaceToPay`, `Cash` (`__('Efectivo')`), `Transfer` (`__('Transferencia')`); eliminar `Manual`
- [x] Agregar `isGateway(): bool` y usarlo donde hoy se compara contra `manual`
- [x] `grep` de `PaymentGateway::Manual` y `'manual'`: actualizar Action, factory, seeders y tests
- [x] `php artisan enums:typescript`

### Desglose de ingresos
- [x] `RevenueBreakdown` suma `array $byMethod`
- [x] `RevenueCalculator::byMethod()` agrupa por `gateway`, rellena en cero los medios sin movimientos y respeta el orden del enum
- [x] `share_pct` entero sobre el bruto, con corte cuando el bruto es `0.00`
- [x] El Resource/serialización del snapshot expone `revenue.by_method`

### Dashboard a Inertia
- [x] Crear `App\Http\Controllers\DashboardPagesController` (`__invoke`) con `Inertia::render('Admin/Dashboard', [...])`
- [x] Props `snapshot`, `periods` (de `PeriodFilter::SUPPORTED_KEYS` con etiqueta) y `filters`
- [x] `routes/web.php`: reemplazar `Route::inertia('dashboard', …)` por la del controller
- [x] Eliminar `Api\V1\Admin\DashboardController` y su ruta; `DashboardResource` solo si no queda consumidor
- [x] **No** tocar `RevenueReportController`: es una descarga y se queda en API
- [x] `git grep` de la ruta y de la acción de Wayfinder para confirmar que nadie más la consume

### Pago manual con medio
- [x] `RegisterManualPaymentRequest`: `method` required, acotado a `cash` y `transfer`, con mensaje propio
- [x] `RegisterManualPaymentAction` recibe el medio y lo usa como `gateway`
- [x] `BookingPaymentController` pasa el argumento nuevo

### Tests
- [x] `RevenueCalculatorTest`: la suma del desglose iguala el bruto; medio sin movimientos en cero; bruto cero sin división; reembolsado cuenta en su medio
- [x] `tests/Feature/Admin/DashboardPageTest.php` con `assertInertia` (reemplaza al de API): payload, periodo inválido 302 con errores en sesión, 403 sin permiso, aislamiento por tenant
- [x] `ManualPaymentTest`: efectivo y transferencia quedan con su `gateway`; sin `method` → 422
- [x] `TransactionPagesTest`: filtrar por `cash` no trae los de `transfer`
- [x] Verificar que `PlatformMetricsAggregatorTest` (super admin) sigue verde

### Cierre
- [x] `php artisan wayfinder:generate --with-form`
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan migrate:fresh --seed` y `php artisan test --compact`
- [x] Marcar checkboxes + notas + self-review

## Frontend (`montree-frontend-dev`)

- [x] `types/dashboard.ts`: `RevenueByMethodRow`, `by_method`, props de la página
- [x] `types/passenger.ts`: `method` en `ManualPaymentInput`
- [x] `Admin/Dashboard.vue`: props en vez de fetch; quitar carga inicial y error de red
- [x] `PeriodSelector.vue`: opciones desde la prop `periods`, sin lista duplicada
- [x] `organisms/RevenueByMethod.vue`: fila por medio con etiqueta, monto, porcentaje y barra proporcional; estado vacío con bruto en cero
- [x] Ubicarlo debajo del gráfico de ingresos
- [x] `PassengerDrawer.vue`: selector de medio sin valor por defecto, antes del monto; la etiqueta de referencia cambia según el medio
- [x] Claves nuevas en `lang/en.json`
- [x] `npm run types:check`, `lint`, `format`, `build`
- [ ] Navegador: cambiar de periodo sin recargar, ver el desglose, registrar un pago en efectivo y otro por transferencia — **hecho por HTTP, no en un navegador**: sin Playwright MCP en el toolset del agente
- [x] Marcar checkboxes + self-review

## Review (`montree-reviewer`)

- [ ] Tests, Pint, types y lint pasan
- [ ] La suma del desglose iguala el bruto
- [ ] Sin `useApi()`/`useHttp()` contra la ruta web del dashboard
- [ ] No quedan referencias a `manual` ni al endpoint de API eliminado
- [ ] Aislamiento por tenant en la página
- [ ] Reporte go/no-go

---

## Notas durante implementación

- `2026-09-13` (principal): decisiones tomadas con el usuario — partir el enum, desglose debajo del gráfico, y retirar el endpoint de API del dashboard.

- `2026-09-13` (backend): `PaymentGateway::Manual` eliminado. `PaymentFactory` no
  necesitó cambios: su default ya era `PlaceToPay` y ningún seeder crea pagos.
  Los tests que usaban `Manual` pasaron a `Cash` o `Transfer` según el caso.
- `2026-09-13` (backend): el endpoint `GET /api/v1/admin/dashboard` se eliminó.
  `git grep` de la ruta y de la acción de Wayfinder dio un solo consumidor,
  `pages/Admin/Dashboard.vue`, que el frente reescribe con props. La acción de
  Wayfinder ya no se genera: **hasta que el frente migre la page, `npm run
  build` y `types:check` fallan** por ese import.
- `2026-09-13` (backend): `DashboardResource` se conserva — ahora lo resuelve
  `DashboardPagesController` para armar la prop `snapshot`.
- `2026-09-13` (backend): las etiquetas de periodo se movieron a
  `PeriodFilter::label()` / `::options()` para que la prop `periods` y el front
  no se desincronicen. `PeriodSelector.vue` debe consumirlas y borrar su lista.
- `2026-09-13` (backend): con `gross` en `0.00` no se divide; los tres medios
  vienen igual en cero. Los porcentajes redondean a entero y pueden sumar 101
  (`310/78/32` sobre `420` → `74/19/8`), que es lo que dice la spec.
- **Deuda anotada, no tocada en este PR:** `TopToursResolver` suma solo pagos
  `Completed` mientras `RevenueCalculator` suma `Completed` + `Refunded`. Con un
  reembolso en el periodo, el ingreso del tour en «top tours» no cuadra con el
  bruto del dashboard. Es una inconsistencia previa al feature.
- **Deuda anotada:** `Rule::enum(...)->only([Cash, Transfer])` obliga a declarar
  el mensaje bajo la clave `method.Illuminate\Validation\Rules\Enum`, que es
  frágil si Laravel cambia la resolución de mensajes de rule objects. Hay test
  que lo cubre.
- `2026-09-13` (backend): tres fallos ajenos y preexistentes en
  `TeamRequestMessagesTest` por `APP_LOCALE=en` en el `.env` local. Por eso las
  aserciones nuevas de etiqueta usan `PaymentGateway::*->label()` y `__()` en
  vez de literales en español.
- **Desvío del contrato, a confirmar:** `tasks.md` pedía «periodo inválido 422»
  para `/admin/dashboard`, pero es una ruta web que responde Inertia: Laravel
  redirige 302 con los errores en sesión, no 422 (el 422 solo sale en peticiones
  JSON/XHR, que es como llega la visita de Inertia desde el navegador). El test
  afirma 302 + `assertSessionHasErrors(['period'])`. Si se quiere el 422 en el
  contrato escrito, hay que pasarlo por `montree-spec-updater`.

- `2026-09-13` (frontend): el dashboard pasó a props. El selector de periodo
  navega con `router.get(dashboard().url, { period }, { only: ['snapshot',
  'filters', 'errors'] })`; verificado por HTTP que la visita parcial responde
  200 con esas tres props y nada más, y que un `period` inválido responde 302.
- `2026-09-13` (frontend): `PeriodSelector` ya no duplica los periodos: llegan
  por la prop `periods`. `Admin/Transactions/Show.vue` ya venía corregido por el
  backend (`gateway !== 'placetopay'`), no hizo falta tocarlo.
- `2026-09-13` (frontend): `RevenueByMethod` usa el bruto (no la suma de filas)
  para decidir el estado vacío, porque el backend manda igual los tres medios en
  cero.
- `2026-09-13` (frontend): claves nuevas en `lang/en.json` y **borradas** las
  cuatro que quedaron huérfanas al retirar el fetch del dashboard
  (`No se pudo cargar el dashboard.`, `Bienvenido al dashboard`, su texto de
  apoyo y el placeholder `Transferencia, recibo, efectivo…`).
  `TranslationCatalogTest` verde.
- `2026-09-13` (frontend): **sin Playwright MCP en el toolset**. La verificación
  fue por HTTP contra `demo.montree.test` con sesión real de
  `admin@demo.montree.test`: props del dashboard, visita parcial al cambiar de
  periodo, 422 sin `method`, y un pago en efectivo y otro por transferencia que
  quedaron con su `gateway` (33 %/67 % en el desglose). El render en pantalla no
  se miró en un navegador.
- `2026-09-13` (principal, verificación en navegador): cubierto el ítem que el agente dejó sin marcar. Dashboard con el desglose (la suma de los tres medios iguala el bruto), cambio de periodo comprobado como visita parcial —una sola petición y ningún asset redescargado—, y el drawer registrando un pago por transferencia que quedó en base con `gateway = transfer`.
- `2026-09-13` (principal, bug encontrado en pantalla): elegir el medio no limpiaba el error de validación de ese campo, así que el mensaje «Elegí si el pago fue en efectivo o por transferencia» seguía visible después de resolverlo. Se extrajo `selectPaymentMethod()` en `PassengerDrawer.vue`, que fija el valor y borra `errors.method`. No lo veían ni el type-check ni los tests: solo se nota usando el formulario.
- `2026-09-13` (principal): el idioma de la interfaz es una preferencia **por usuario** (`users.locale`, resuelta en `App\Support\Locale::resolveFor()`), no `APP_LOCALE`. Eso solo afecta a los tests, que corren sin usuario.
