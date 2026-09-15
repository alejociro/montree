# Términos de la agencia — Tasks

---

## Backend (`montree-backend-dev`)

### Schema y modelo
- [x] `tenant_configurations`: `terms_body` `longText()->nullable()` tras `custom_css`, **editando la migración base**
- [x] `TenantConfiguration`: `terms_body` en `$fillable`
- [x] `php artisan migrate:fresh --seed`

### Texto por defecto y render
- [x] `resources/policies/terms.es.md` y `terms.en.md` con un texto base razonable para una agencia de tours (cancelaciones, pagos, responsabilidades, datos del viajero)
- [x] Resolver el texto: el propio de la agencia o, si está vacío, el de por defecto del idioma activo con fallback al idioma por defecto
- [x] Convertir con `Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false])`

### Página pública
- [x] `PolicyPagesController::terms()` con `Inertia::render('Policies/Terms', …)`
- [x] Ruta `terminos` con nombre `policies.terms`, **fuera** del grupo `Route::domain(platform_host)`
- [x] Props según `contracts.md`: `terms.html`, `terms.is_default`, `terms.updated_at`, `agency.name`

### Panel
- [x] `UpdateTenantConfigurationRequest`: `terms_body` nullable string max:20000; vacío o solo espacios → `null`
- [x] `UpdateTenantConfigurationAction` persiste el campo
- [x] `TenantConfigurationResource`: `terms_body` (crudo) y `terms_is_default`

### Tests
- [x] `tests/Feature/Policies/TermsPageTest.php`: texto propio, texto por defecto, aislamiento entre agencias
- [x] Seguridad: `<script>`, `<img onerror=…>` y `[x](javascript:…)` no llegan al HTML, y un enlace `https://` sí
- [x] `TenantConfigurationControllerTest`: guardar, vaciar vuelve al default, 403 sin permiso, 422 pasando el máximo

### Cierre
- [x] `php artisan wayfinder:generate --with-form`
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact`
- [x] Checkboxes + notas + self-review

## Frontend (`montree-frontend-dev`)

- [x] `pages/Policies/Terms.vue` con `PublicLayout`, nombre de la agencia y `v-html` del HTML ya saneado (no re-procesar en el cliente)
- [x] `organisms/TermsEditor.vue`: área de texto monoespaciada, contador, ayuda breve de Markdown
- [x] Sección «Términos y condiciones» en `Admin/Tenant/Configuration.vue`, con aviso cuando rige el texto por defecto y enlace a la página pública
- [x] `Booking/Create.vue`: reemplazar `href="/terms"` por Wayfinder y quitar la mención a la política de cancelación del copy
- [x] Claves nuevas en `lang/en.json`
- [x] `npm run types:check`, `lint`, `format`, `build`
- [x] Navegador: abrir los términos desde el checkout (sin 404), editarlos en el panel y ver el cambio en la página pública
- [x] Checkboxes + self-review

## Review (`montree-reviewer`)

- [ ] Tests, Pint, types y lint pasan
- [ ] Sin XSS: el saneo es del servidor y hay test con payloads reales
- [ ] La página responde en el subdominio del tenant y no en el de plataforma
- [ ] Aislamiento entre agencias
- [ ] Sin URLs escritas a mano
- [ ] Reporte go/no-go

---

## Notas durante implementación

- `2026-09-13` (principal): el 404 venía de `href="/terms"` escrito a mano en `Booking/Create.vue` **sin ruta detrás**. Es exactamente el error que la regla de Wayfinder existe para evitar.
- `2026-09-13` (principal): `league/commonmark` ya viene con Laravel; `Str::markdown()` con `html_input => strip` y `allow_unsafe_links => false` quedó verificado contra `<script>`, `<img onerror>` y `javascript:`. **No hay dependencia nueva.**
- `2026-09-13` (principal): decisión del usuario — **un solo documento**, no cuatro. Cancelaciones y pagos son secciones dentro de los términos.

- `2026-09-13` (backend): la columna quedó en la migración base de `tenant_configurations`, justo después de `custom_css`. `migrate:fresh --seed` corrido.
- `2026-09-13` (backend): `App\Services\Tenant\TermsRenderer` resuelve el texto (propio o por defecto), lo cachea por idioma en memoria del proceso y convierte con `Str::markdown(['html_input' => 'strip', 'allow_unsafe_links' => false])`. El `TenantConfigurationResource` le pregunta `isDefault()` para no duplicar la regla de «vacío = sin personalizar».
- `2026-09-13` (backend): la ruta `terminos` quedó junto a las públicas del tenant, fuera de `Route::domain(platform_host)`, y el controller devuelve 404 si no hay tenant resuelto (el host de plataforma no sirve términos de agencia). Hay test para ambos lados.
- `2026-09-13` (backend): `TermsPageTest` lee las props desde `viewData('page')` en vez de `assertInertia`, porque `Policies/Terms.vue` todavía no existe y el helper de Inertia exige el archivo. Cuando el frontend cree la page, se puede migrar a `assertInertia`.
- `2026-09-13` (backend): el mensaje de validación del máximo sí va a `lang/en.json` (es una línea corta); el documento por defecto vive en `resources/policies/terms.{es,en}.md`, como manda la spec.
- `2026-09-13` (principal): `terms_body` salía en `tenantConfiguration`, que es prop compartida de **toda** respuesta Inertia: 20.000 caracteres potenciales en cada carga del catálogo público. Se excluye del payload compartido con `Arr::except` y la pantalla que lo edita lo recibe como prop propia desde `TenantConfigurationPagesController` (la ruta deja de ser `Route::inertia`). La respuesta del `PUT` sí sigue devolviéndolo. Fijado con `test_the_raw_terms_never_travel_in_the_shared_props`.

- `2026-09-13` (frontend): `Policies/Terms.vue` pinta el HTML del servidor con `v-html` y sin parser en el cliente; los estilos de prosa salen de variantes arbitrarias de Tailwind (`[&_h2]:…`) porque el proyecto no tiene el plugin typography y la constitución prohíbe CSS custom fuera de `app.css`.
- `2026-09-13` (frontend): `TermsEditor.vue` no tiene vista previa renderizada, por decisión del plan: contador contra 20.000, ayuda de Markdown y enlace a la página pública como única señal del resultado.
- `2026-09-13` (frontend): `Admin/Tenant/Configuration.vue` lee el cuerpo de la prop propia `terms.body` y mantiene `terms_is_default` en un `ref` que se actualiza con la respuesta del `PUT`, que es la única que devuelve ese estado.
- `2026-09-13` (frontend): el copy del checkout perdió `y la política de cancelación.`; la clave se borró de `lang/en.json` para no dejarla huérfana ante `TranslationCatalogTest`.
