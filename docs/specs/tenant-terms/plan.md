# Términos de la agencia — Plan técnico

## 1. Resumen

Una columna, una página pública en el subdominio del tenant, una sección en el
panel y el arreglo del enlace roto del checkout. Sin dependencias nuevas:
`league/commonmark` ya viene con Laravel.

## 2. Backend

### Schema
- `tenant_configurations`: `terms_body` `longText()->nullable()` después de
  `custom_css`. Se edita **la migración base**, como veníamos haciendo.
- `TenantConfiguration`: `terms_body` en `$fillable`.

### Servicio
- `App\Services\Tenant\TermsRenderer` (o método en el modelo si queda de 3
  líneas; decidir al escribirlo, no abstraer de más):
  - `bodyFor(TenantConfiguration $config): string` — el texto propio o el de por
    defecto del idioma activo.
  - `html(string $body): string` — `Str::markdown()` con `html_input => strip` y
    `allow_unsafe_links => false`.
  - El archivo por defecto se cachea por idioma con `Cache::rememberForever`
    invalidado por deploy, o simplemente `static` en memoria por request. Lo
    segundo alcanza: es un `File::get()` por request como mucho.

### Página pública
- `App\Http\Controllers\PolicyPagesController::terms()` → `Inertia::render('Policies/Terms', …)`.
- `routes/web.php`: `Route::get('terminos', …)->name('policies.terms')` **fuera**
  del grupo `Route::domain(platform_host)`, junto a las rutas públicas del tenant.

### Panel
- `UpdateTenantConfigurationRequest`: `terms_body` nullable string max:20000;
  accessor que normaliza vacío/espacios a `null`.
- `UpdateTenantConfigurationAction`: persiste el campo.
- `TenantConfigurationResource`: `terms_body` + `terms_is_default`.

## 3. Frontend

- `pages/Policies/Terms.vue` — usa `PublicLayout`, título con el nombre de la
  agencia, `v-html` del HTML ya saneado con estilos de prosa.
- `organisms/TermsEditor.vue` — área de texto monoespaciada, contador de
  caracteres, ayuda corta de Markdown y vista previa. **La vista previa no
  renderiza Markdown en el cliente**: muestra el texto tal cual o pide el HTML al
  guardar. No metemos un parser en el navegador para esto.
- `Admin/Tenant/Configuration.vue` — sección «Términos y condiciones» con el
  editor, aviso cuando rige el texto por defecto y enlace a la página pública.
- `Booking/Create.vue` — el `href="/terms"` pasa a Wayfinder y el copy pierde la
  mención a la política de cancelación.

## 4. Tests

- `tests/Feature/Policies/TermsPageTest.php`: muestra el texto propio; sin texto
  propio muestra el de por defecto; el subdominio de otra agencia muestra los
  suyos; `<script>`, `<img onerror>` y `javascript:` no llegan al HTML.
- `TenantConfigurationControllerTest`: guarda `terms_body`; vacío vuelve a
  `null` y `terms_is_default` pasa a `true`; sin permiso 403; más de 20.000 → 422.
- Que el enlace del checkout resuelva (no 404).

## 5. Riesgos

| Riesgo | Mitigación |
|---|---|
| XSS por el texto de la agencia | `html_input => strip` + test con payloads reales |
| El texto por defecto se muestra como si fuera de la agencia | Es la decisión tomada; el panel avisa que está sin personalizar |
| La página se sirve en el host de plataforma por error | La ruta va fuera de ese grupo; test desde el subdominio |

## 6. Orden

1. `montree-db-architect` — solo la columna y el fillable.
2. `montree-backend-dev` — §2 y §4.
3. `montree-frontend-dev` — §3.
4. `montree-reviewer`.

## Changelog

- `2026-09-13` — Creación.
