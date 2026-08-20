# multilanguage-es-en — Plan técnico

> Decisiones técnicas para implementar este feature.
> Backend, frontend, base de datos, tests.

---

## 1. Resumen

Un solo catálogo de traducciones, el de Laravel (`lang/es.json` + `lang/en.json` + los archivos PHP
del framework), resuelto en el backend y compartido al frontend como prop de Inertia. Un middleware
`SetLocale` fija `app()->setLocale()` en cada request resolviendo usuario → cookie → `Accept-Language`
→ default. Un endpoint `PATCH /locale` persiste la elección. En Vue, un `$t` global (registrado en
`app.ts`) lee esa prop, de modo que traducir un template es cambiar `Texto` por `{{ $t('Texto') }}`
sin agregar un import por archivo. No se instala ninguna dependencia npm.

## 2. Backend

### Modelos

- `User` — se agrega `locale` a `#[Fillable]` y el accessor `preferredLocale()` (contrato
  `HasLocalePreference` de Laravel), que hace que las notificaciones encoladas se rendericen en el
  idioma del destinatario sin tocar cada Notification.

### Migrations

- `2026_08_19_000000_add_locale_to_users_table.php` — `string('locale', 5)->nullable()` después de
  `phone`. Nullable a propósito: `null` significa "seguí el idioma del navegador", que no es lo mismo
  que "elegí español".

### Config

- `config/app.php` — `locale => env('APP_LOCALE', 'es')`, `fallback_locale => env('APP_FALLBACK_LOCALE', 'es')`.
  Hoy ambos dicen `en`, que es la causa de que las validaciones salgan en inglés.
- `config/montree.php` — bloque `locales` con el catálogo soportado (`code`, `name` por idioma, `native`).
  Es la única fuente de verdad: la regla `in:` del Form Request, el selector del frontend y el
  middleware leen de ahí.

### Middleware

- `App\Http\Middleware\SetLocale` — registrado en `$middleware->web(prepend: [...])` **después** de
  `ResolveTenant` (necesita el tenant ya resuelto para no interferir con el redirect de subdominio) y
  antes de `HandleInertiaRequests` (que comparte el catálogo ya resuelto). Orden de resolución:
  1. `$request->user()?->locale`
  2. cookie `locale`
  3. `$request->getPreferredLanguage(array_keys(config('montree.locales')))`
  4. `config('app.locale')`
  Cualquier valor fuera del catálogo se descarta y se pasa al siguiente escalón.
- `encryptCookies(except: ['appearance', 'sidebar_state', 'locale'])` — la cookie se escribe también
  desde el frontend en el mismo formato, igual que `appearance`.

### Form Requests

- `App\Http\Requests\Settings\UpdateLocaleRequest` — `authorize(): true` (es preferencia de
  presentación de quien hace el request, no hay recurso ajeno que autorizar; la regla `in:` es la que
  cierra el input) y `rules()` con `['locale' => ['required', 'string', Rule::in(array_keys(config('montree.locales')))]]`.

### Controllers

- `App\Http\Controllers\Settings\LocaleController` — acción única `__invoke()`, ≤10 líneas: invoca la
  action y devuelve `back()` con la cookie encolada.

### Actions

- `App\Actions\Settings\UpdateLocaleAction::handle(?User $user, string $locale): void` — persiste
  `locale` en el usuario si hay sesión. La cookie la encola el controller (es HTTP, no dominio).

### Resources

- Ninguno nuevo. Las props se arman en `HandleInertiaRequests::share()`:
  `locale`, `locales` y `translations` (lectura y decode de `lang/{locale}.json`, cacheada en
  `Cache::rememberForever("translations.{$locale}")` fuera de `local`, invalidada por el
  comando `translations:flush`).

### Catálogos de idioma

- `lang/es.json` — conserva las 3 claves de Fortify que ya tiene. No necesita entradas nuevas: la clave
  **es** el texto en español (§5).
- `lang/en.json` — la traducción al inglés de cada clave usada en la aplicación. Es el archivo grande.
- `lang/es/validation.php` — traducción completa de los mensajes de validación de Laravel + el bloque
  `attributes` con los nombres de campo del dominio (`email` → "correo electrónico", `subdomain` →
  "subdominio", etc.). **No existe hoy**: por eso las validaciones salen en inglés aunque la UI esté
  en español.
- `lang/es/auth.php`, `lang/es/passwords.php` — ya existen, se revisan.
- Para inglés no se crean archivos PHP: Laravel trae sus propios mensajes en inglés y los usa como
  fallback natural.

### Mensajes de dominio

Las excepciones de `app/Exceptions/` y los `abort()`/`ValidationException` con texto literal en
español se envuelven en `__()`. La clave sigue siendo el texto en español, así que el comportamiento
en `es` no cambia ni una coma.

## 3. Frontend

### Composables

- `useTranslations()` — `t`, `tChoice`, `locale`, `locales`, `setLocale`. Lee de `usePage().props`.
  Sin estado propio: la fuente de verdad es la prop compartida, así que un cambio de idioma se
  refleja solo cuando el backend responde (nada de estado optimista que pueda divergir del backend).

### Registro global

- `app.ts` — `app.config.globalProperties.$t = translate` antes de `.mount()`. Es la decisión que
  hace tratable migrar 265 archivos: los templates no necesitan ningún import.

### Molecules

- `LocaleSwitcher.vue` — nuevo. Dos variantes por prop `variant`: `dropdown` (header público y menú de
  usuario) y `tabs` (pantalla de ajustes, espejo de `AppearanceTabs.vue`).

### Puntos de montaje

- `AppHeader.vue` — header de la app autenticada.
- `PublicLayout.vue` — header del sitio público del tenant.
- `UserMenuContent.vue` — menú de usuario (cubre admin, super admin y guía, que lo reusan).
- `AuthLayout.vue` — pantallas de login/registro, donde todavía no hay usuario.
- `pages/settings/Appearance.vue` — sección "Idioma" junto a la de apariencia.

### Types

- `types/locale.ts` — `LocaleOption`, `Translator`.
- `types/global.d.ts` — extiende `sharedPageProps` y `ComponentCustomProperties` (`$t`).

### Wayfinder

- Tras el backend: `php artisan wayfinder:generate` → `@/routes/locale` con `update()`.

### Migración de los literales

Se hace con **un script de una pasada** (Python, fuera del repo: es una herramienta de un solo uso,
no código del producto) sobre `resources/js`, no con 265 ediciones a mano. Reglas del script:

1. Nodos de texto de un template que sean íntegramente un literal → `{{ $t('…') }}`.
2. Atributos de texto conocidos (`title`, `label`, `placeholder`, `description`, `alt`, `aria-label`,
   `empty-message`, `submit-label`, `confirm-label`, `cancel-label`, `heading`, `subtitle`) →
   `:attr="$t('…')"`.
3. En `<script setup>`, valores de claves de copy (`label:`, `title:`, `description:`, `message:`,
   `placeholder:`) y argumentos de `toast.*()` → `t('…')`, agregando el import de
   `useTranslations` solo en esos archivos.
4. Nunca toca: `class`, `:class`, nombres de ruta, claves de objeto, imports, valores de enum,
   literales sin ninguna letra, ni strings que ya estén dentro de `$t(`/`t(`.

Lo que el script no puede cubrir con seguridad se corrige a mano después, guiado por el test de
paridad (§4): los nodos de texto con interpolación (`Mostrando 1–20 de 43 tours`), que pasan a una
clave con placeholders, y los catálogos declarados como constante —`config/navigation.ts`,
`config/permissions.ts`, `PermissionCatalog::LABELS`, los breadcrumbs de `defineOptions`— que no
pueden llamar a `t()` donde se declaran y por eso se traducen en su punto de render
(`Breadcrumbs.vue`, `NavMain.vue`, `PermissionPicker.vue`, `AuthSplitLayout.vue`), un solo lugar
cada uno en vez de N páginas.

Mismo tratamiento para el catálogo de categorías por defecto (`config/montree.php` →
`default_categories`): son datos en la base, pero el texto lo escribe la aplicación y sale igual en
todas las agencias, así que se traducen en el render con `categoryLabel()`
(`resources/js/lib/categories.ts`), usado en las diez pantallas que muestran el nombre de una
categoría. Las categorías que crea una agencia salen tal cual, porque `t()` devuelve la clave
cuando no hay entrada. El archivo declara además la lista literal de nombres: `TranslationCatalogTest`
solo mira `app/`, `resources/js` y `resources/views`, y sin ese literal marcaría las entradas como
huérfanas por vivir su única fuente en `config/`.

## 4. Tests

### Feature tests (backend)

- `tests/Feature/Locale/LocaleSwitchTest.php`
  - `test_guest_can_switch_locale_and_gets_cookie`
  - `test_authenticated_user_locale_is_persisted`
  - `test_unsupported_locale_is_rejected_with_422`
  - `test_user_preference_wins_over_cookie`
  - `test_accept_language_header_is_used_when_no_cookie_and_no_user`
  - `test_falls_back_to_default_locale`
- `tests/Feature/Locale/InertiaLocalePropsTest.php`
  - `test_share_includes_locale_locales_and_translations`
  - `test_translations_payload_matches_active_locale`
- `tests/Feature/Locale/ValidationMessagesTest.php`
  - `test_validation_messages_are_spanish_when_locale_is_es`
  - `test_validation_messages_are_english_when_locale_is_en`

### Unit tests

- `tests/Unit/Locale/TranslationCatalogTest.php` — **paridad de catálogos**: recorre `resources/js` y
  `app/` extrayendo las claves de `$t(' … ')`, `t(' … ')` y `__(' … ')`, y falla si alguna no está en
  `lang/en.json`. Es la red que detecta el texto sin traducir, y por eso corre en CI: sin esto, la
  cobertura del feature se degrada en el primer PR que agregue una pantalla.

## 5. Decisiones tomadas

- **Un catálogo o dos**: uno solo, el de Laravel. Razón: `vue-i18n` obligaría a mantener un segundo
  set de JSON para el frontend mientras el backend sigue necesitando el suyo para validaciones,
  correos y excepciones — cada clave existiría dos veces, en dos formatos, con dos formas de
  desincronizarse. Lo que se pierde (pluralización e interpolación del lado del cliente) lo cubre
  `trans_choice`, reimplementado en 20 líneas dentro de `useTranslations`.
- **Forma de la clave**: el texto en español es la clave (`__('Guardar cambios')`), no una clave
  semántica (`__('common.save')`). Razones: (a) permite migrar 265 archivos con un script mecánico,
  porque el reemplazo es envolver el literal que ya está ahí; (b) el español —el idioma en el que se
  escribe la aplicación— nunca necesita catálogo, así que solo hay un archivo que mantener,
  `lang/en.json`; (c) una clave faltante degrada a texto en español legible, no a `common.save` en
  pantalla. Contra: cambiar el copy en español invalida la entrada en inglés. Se acepta porque el test
  de paridad convierte ese olvido en un build rojo, no en un bug silencioso.
- **`$t` global vs. import por archivo**: global en `app.ts`. Razón: 265 templates no pueden depender
  de un import correcto en cada uno; el import se reserva para `<script setup>`, donde sí hace falta.
- **Dónde vive la preferencia**: columna en `users` + cookie. La cookie sola no sobrevive al cambio de
  dispositivo; la columna sola no sirve para invitados, que son la mayoría del tráfico público.
- **`locale` nullable**: `null` = "seguí mi navegador". Distinto de `'es'`, que es una elección
  explícita que debe ganarle al `Accept-Language`.
- **Contenido del tenant sin traducir**: traducirlo exigiría columnas por idioma en `tours`,
  `categories`, `tour_itineraries` y un editor multilenguaje. Es un feature de producto propio, no una
  consecuencia técnica de este.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| El script de migración rompe un template (comilla dentro del literal, interpolación parcial) | alta | `npm run types:check` + `npm run lint` + `npm run build` tras la pasada; el diff se revisa por archivo y los casos raros se arreglan a mano |
| Queda texto en español sin envolver | alta | test de paridad de catálogos (`TranslationCatalogTest`) recorriendo el código fuente |
| El copy en español cambia y la entrada en inglés queda huérfana | media | mismo test: la clave nueva no está en `lang/en.json` y el build falla |
| El payload de `translations` crece y pesa en cada respuesta | baja | es un JSON plano de pocos KB; si escala, pasa a prop diferida cargada una vez por sesión |
| Conflictos de merge con features en vuelo (F018 tocó admin) | media | la rama sale de `develop` actualizado y el feature se mergea completo, no por partes |
| Traducción al inglés de baja calidad en dominio turístico | media | el catálogo queda en un solo archivo revisable de punta a punta, no disperso en 265 componentes |

## 7. Out of scope explícito

- `vue-i18n` y cualquier dependencia npm nueva.
- Formatos regionales de fecha/número/moneda por idioma. Hoy el formateo está hardcodeado a
  `es-CO`/`COP` en varios componentes; unificarlo es un feature aparte y se anota como deuda.
- URLs localizadas y `hreflang`.
- Idioma por defecto por tenant.
- Traducción del contenido cargado por las agencias.
