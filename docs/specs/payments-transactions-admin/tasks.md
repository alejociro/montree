# Transacciones en el panel — Tasks

> Checklist atómico derivado de `plan.md`. No hay sección DB: este feature no
> toca el esquema.

---

## Backend (`montree-backend-dev`)

### RBAC
- [x] `RolesAndPermissionsSeeder::PERMISSIONS`: módulo `payments` con `payments.view` y `payments.query`
- [x] `RolesAndPermissionsSeeder::ROLE_PERMISSIONS`: `sales` suma `payments.view`; `operator` y `guide` no
- [x] `PermissionCatalog::MODULE_LABELS`: `'payments' => 'Pagos'`
- [x] `PermissionCatalog::LABELS`: etiquetas de los dos permisos
- [x] `resources/js/config/permissions.ts`: agregar los dos slugs
- [x] `PermissionCatalogSeederTest`: `CATALOG_SIZE` de 38 → 40 y enumeración actualizada
- [x] Revisar `RoleManagementTest` e `InertiaAuthUserPropTest`, que heredan el conteo

### Modelo y consulta
- [x] `Payment`: scopes `matching()`, `settledBetween()` (con fallback a `created_at`), `forTourDate()`
- [x] `Payment`: accessor `last_digits` desde `processor_fields.lastDigits`
- [x] `Payment`: `isQueryable(): bool`

### Listado y detalle
- [x] `App\Http\Requests\Admin\Transaction\TransactionIndexRequest` con `authorize()`, `rules()` y accessores tipados + `filters()`
- [x] `App\Http\Controllers\TransactionPagesController` con `index()` y `show()` (máx. 10 líneas por método)
- [x] `App\Http\Resources\Admin\TransactionResource` (fila)
- [x] `App\Http\Resources\Admin\TransactionDetailResource` (detalle) — sin `process_url` ni `gateway_response`; de `processor_fields` solo `lastDigits`
- [x] Rutas `transactions.index` y `transactions.show` en el grupo admin de `routes/web.php`
- [x] Orden `processed_at DESC, created_at DESC, id DESC`, 25 por página, `withQueryString()`
- [x] `with(['booking.tour', 'booking.tourDate'])` y verificar con query log que no hay N+1

### Reconsulta
- [x] `App\Data\TransactionQueryResult` (readonly)
- [x] `App\Actions\Payment\QueryTransactionAction` reusando `ResolvePaymentAction`
- [x] `PaymentException::notQueryable()` (422, `PAYMENT_NOT_QUERYABLE`)
- [x] `App\Http\Controllers\QueryTransactionController` (`__invoke`), redirect con flash según `contracts.md`
- [x] Ruta `transactions.query` con `can:payments.query`

### Planilla
- [x] `TourDatePassengerController`: cargar `booking.payments` solo si el usuario tiene `payments.view`
- [x] `PassengerResource`: exponer `payments` con `whenLoaded`

### Tests
- [x] `tests/Feature/Admin/TransactionPagesTest.php`: happy, filtros (estado, medio, salida, fechas, búsqueda por referencia y por requestId), 403 sin permiso, edge del pago colgado sin `processed_at`, aislamiento por tenant (404)
- [x] Test de seguridad: la respuesta no trae `process_url` ni `gateway_response` ni el BIN
- [x] `tests/Feature/Admin/QueryTransactionTest.php`: happy, 403, pago manual rechazado sin llamar a la pasarela, pago ya resuelto no duplica saldo, pasarela caída
- [x] Planilla: el guía no recibe `payments`, el admin sí

### Cierre
- [x] `php artisan wayfinder:generate --with-form`
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact`
- [x] Marcar checkboxes + notas + self-review (3 preguntas)

## Frontend (`montree-frontend-dev`)

- [x] `resources/js/types/transaction.ts` con los shapes de `contracts.md`
- [x] `molecules/CopyableValue.vue` (copiar con confirmación por toast, fallback si no hay `navigator.clipboard`)
- [x] `organisms/TransactionFilters.vue` — selector «Buscar por» + campo de valor (se aplica al enviar, sin debounce), estado, medio, salida, rango de fechas, limpiar
- [x] `organisms/TransactionsTable.vue` (fila clickeable, chip de estado, medio, monto, fecha)
- [x] `pages/Admin/Transactions/Index.vue` — `router.get` con `preserveState`, `replace` y `only`; **no** `useApi()`
- [x] `pages/Admin/Transactions/Show.vue` — detalle + acción de reconsulta con `router.post`
- [x] Ítem «Transacciones» en el sidebar admin, condicionado a `payments.view`
- [x] `PassengerDrawer.vue`: bloque de transacciones de la reserva, solo si vienen en el payload
- [x] `PassengerManifest.vue`: enlace «Ver transacciones de esta salida» con Wayfinder, solo con `payments.view`
- [x] Estados: cargando, vacío (explicando qué se puede buscar) y error
- [x] Campos de monto y fechas con el formato del proyecto (`lib/format`)
- [x] Claves nuevas en `lang/en.json` (`TranslationCatalogTest` falla si faltan)
- [x] Responsive: la tabla scrollea horizontal en móvil sin romper la página
- [x] `npm run types:check`, `npm run lint`, `npm run format`, `npm run build`
- [~] Navegador: listar, filtrar, abrir un detalle, copiar el requestId, reconsultar un pago — verificado por HTTP real contra `demo.montree.test` (props, filtros, detalle y reconsulta); **sin Playwright MCP en el toolset**, así que el clic del botón de copiar y el toast no se probaron en un navegador
- [x] Marcar checkboxes + self-review (3 preguntas)

### Ajustes 2026-09-12 (filtros + márgenes)

- [x] Se retira la búsqueda libre: entra el selector «Buscar por» con las opciones de `search_fields`
- [x] `filters.search_by` en `types/transaction.ts`, más `search_fields`, `departures` y `default_days` en las props
- [x] Select «Salida» alimentado por `departures` (reemplaza el chip no editable)
- [x] Selector preseleccionado en «Referencia»: `search` nunca viaja sin `search_by`
- [x] Al buscar por un identificador exacto se deshabilitan «Desde»/«Hasta» con la nota «Al buscar por :field se busca en todo el historial.»
- [x] Sin rango elegido, «Desde» muestra la fecha que devolvió el servidor y se explica «Mostrando los últimos :days días.» (se mira la query de la URL, no el «hoy» del navegador)
- [x] «Limpiar filtros» vuelve al estado por defecto
- [x] Márgenes: filtros, tabla, contador y paginación viven en la misma tarjeta `rounded-2xl border bg-card`, igual que `Admin/Departures/Index.vue`
- [x] Columna «Reserva» acotada a `w-40` con `truncate` + `title`; montos a la derecha con `tabular-nums`; scroll horizontal dentro del contenedor
- [x] Claves huérfanas del input viejo eliminadas de `lang/en.json`
- [x] `npm run types:check`, `npm run lint`, `npm run format`, `npm run build`, `php artisan test --compact --filter=TranslationCatalogTest`
- [~] Navegador: sin Playwright MCP en el toolset; verificado por HTTP real contra `demo.montree.test` (props nuevas, bypass del rango con `search_by=request_id`, ventana por defecto, filtro por salida)

## Review (`montree-reviewer`)

- [ ] Tests, Pint, types-check y ESLint pasan
- [ ] Spec cubierta 100%
- [ ] Constitución: Form Request valida, controllers delgados, Resource sin lógica, sin `useApi()` contra rutas web
- [ ] Seguridad: `process_url`, `gateway_response` y el BIN no salen al cliente
- [ ] N+1 medido en el listado
- [ ] Aislamiento por tenant en las tres rutas
- [ ] Reporte go/no-go

---

## Bloqueos / Decisiones pendientes

- (ninguna: las 4 decisiones se tomaron el 2026-09-12, ver `spec.md`)

## Notas durante implementación

- `2026-09-12` (principal): feature abierto sobre F007 ya terminado. No requiere schema: `payments` ya guarda la foto completa de la transacción.
- `2026-09-12` (backend): el catálogo quedó en **40** permisos. `CATALOG_SIZE` pasó de 38 a 40 y `RoleManagementTest` dejó de hardcodear el número: ahora lee la constante.
- `2026-09-12` (backend): `contracts.md` §detalle muestra `processor_fields` con `bin`, pero el texto de al lado (y `plan.md`) prohíben exponerlo. Manda la prohibición: el Resource emite `{"lastDigits": "…"}` o `{}`. **Pendiente para `montree-spec-updater`**: corregir ese ejemplo.
- `2026-09-12` (backend): el detalle suma `is_queryable` (derivado de `gateway` + `request_id`), aditivo al contrato, para que el front no reimplemente la regla al decidir si pinta el botón.
- `2026-09-12` (backend): los mensajes de «pasarela caída» y «sin credenciales» reusan `PaymentException::gatewayUnavailable()` y `gatewayNotConfigured()`, que ya existen desde F007. La redacción difiere de la tabla de `contracts.md`; se prefirió un solo mensaje por situación antes que duplicar textos casi iguales.
- `2026-09-12` (backend): `PaymentStatus::label()` devuelve inglés (`Completed`) desde F007, así que el flash y los chips salen en inglés dentro del panel en español. Es previo a este feature; se deja anotado para un PR de i18n de enums.
- `2026-09-12` (backend): el bloque de transacciones de la planilla se cargó también en `TourPassengerController` (panel), no solo en el endpoint del guía: el `PassengerDrawer` es el mismo componente en las dos zonas y con un solo endpoint el bloque nunca se pintaría para el admin.
- `2026-09-12` (frontend): los filtros van con `router.get(..., { preserveState: true, replace: true, only: ['transactions', 'filters', 'errors'] })`. Nada de `useApi()`: son rutas web que responden Inertia.
- `2026-09-12` (frontend): `plan.md` decía reusar `PaymentStatusChip`, pero ese chip habla del saldo de un pasajero (`paid`/`due`), no del estado de una transacción. Se creó `molecules/TransactionStatusChip.vue` y el original quedó intacto. **Pendiente para `montree-spec-updater`**: corregir §3 de `plan.md`.
- `2026-09-12` (frontend): la planilla manda `status`/`gateway` crudos (`PaymentSummaryResource` no trae `label`), así que se agregaron `formatPaymentStatus()` y `formatPaymentGateway()` en `lib/format.ts`, con el precedente de `formatBookingStatus()`. Efecto lateral conocido: esas etiquetas salen en español y las del listado/detalle en inglés, porque `PaymentStatus::label()` devuelve inglés desde F007.
- `2026-09-12` (frontend): el flash de la reconsulta lo levanta la page (`onSuccess` → `toast`). `HandleInertiaRequests` comparte `flash.success`/`flash.error` pero nadie los consumía de forma global; `lib/flashToast.ts` solo escucha `flash.toast`.
- `2026-09-12` (frontend): el filtro por salida no tiene selector propio (el listado no recibe el catálogo de salidas): llega por URL desde la planilla y se muestra como chip removible.
- `2026-09-12` (frontend): la base de desarrollo tenía el catálogo RBAC viejo (`payments.refund`) y `/admin/transactions` respondía 403 hasta correr `php artisan db:seed --class=RolesAndPermissionsSeeder`. Hay que repetirlo en cualquier entorno ya desplegado.
- `2026-09-12` (backend): N+1 medido con `DB::listen` sobre una página de 25 filas → menos de 20 consultas (test `test_a_full_page_of_transactions_costs_a_bounded_number_of_queries`).
- `2026-09-12` (principal): i18n de enums arreglado acá y no diferido. `PaymentStatus::label()` y `PaymentType::label()` tenían la cadena en inglés dentro de `__()`, y el idioma de origen del proyecto es el español: el chip, el filtro y el flash del módulo salían en inglés. Pasan a cadena en español + entrada en `lang/en.json`. `Pending`, `Completed` y `Refunded` se conservan en el catálogo porque `ReviewStatus`, `TenantStatus` y `BookingStatus` siguen usándolas (mismo bug, alcance mayor, queda para un PR de i18n de enums).
- `2026-09-12` (principal, verificación en navegador): cubierto el ítem que el agente de frontend dejó en `[~]`. Listado, filtro por estado, búsqueda por `requestId`, detalle, copiar y reconsultar, con sesión de `admin@demo.montree.test`. El copiado se verificó de verdad: `demo.montree.test` va por http, así que `navigator.clipboard` no existe y corre el fallback de `execCommand`; se pegó el valor en el buscador y salió `3858778`, el mismo requestId copiado. La reconsulta de un pago ya resuelto devolvió «El pago ya estaba resuelto como Completado.» sin llamar a la pasarela.
- `2026-09-12` (principal): en un entorno ya desplegado hay que correr `php artisan db:seed --class=RolesAndPermissionsSeeder` o el módulo queda invisible: el catálogo RBAC viejo no tiene `payments.view`.
- `2026-09-12` (principal, filtros): se rehicieron siguiendo microsites. La búsqueda libre sobre todas las columnas se retiró: ahora hay un selector «Buscar por» (referencia, requestId, referencia interna, autorización, recibo, número de reserva, nombre del pagador) y el valor se compara por igualdad, que es lo que usa los índices. `payer` es el único `LIKE` y el único sin índice. Ventana por defecto de 30 días, y un identificador exacto la ignora: quien pega un requestId busca ese pago, no ese pago dentro del rango. Props nuevas: `search_fields`, `departures` (solo salidas con algo cobrado, máx. 100) y `default_days`. `contracts.md` actualizado, más las correcciones que el reviewer pidió (`processor_fields` sin BIN, `links` como objeto, `is_queryable` documentado).
