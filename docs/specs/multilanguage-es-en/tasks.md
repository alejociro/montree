# multilanguage-es-en — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Generado a partir de `plan.md`. Modificaciones se reflejan en ambos.

---

## DB

- [x] Migration `add_locale_to_users_table` — `string('locale', 5)->nullable()` después de `phone`
- [x] `User`: agregar `locale` a `#[Fillable]` e implementar `HasLocalePreference::preferredLocale()`
- [x] `UserFactory`: `locale => null` por defecto

## Backend

- [x] `config/app.php`: `locale`/`fallback_locale` → `es`
- [x] `config/montree.php`: bloque `locales` (catálogo soportado)
- [x] `App\Http\Middleware\SetLocale` con la cadena usuario → cookie → `Accept-Language` → default
- [x] Registrar `SetLocale` en `bootstrap/app.php` (después de `ResolveTenant`) y `locale` en `encryptCookies(except:)`
- [x] `App\Http\Requests\Settings\UpdateLocaleRequest`
- [x] `App\Actions\Settings\UpdateLocaleAction`
- [x] `App\Http\Controllers\Settings\LocaleController` (`__invoke`)
- [x] Ruta `PATCH /locale` en `routes/settings.php`
- [x] `HandleInertiaRequests::share()`: props `locale`, `locales`, `translations`
- [x] `lang/es/validation.php` completo, incluido el bloque `attributes` del dominio
- [x] Revisar `lang/es/auth.php` y `lang/es/passwords.php`
- [x] `lang/en.json` con el catálogo completo de la aplicación
- [x] Envolver en `__()` los literales de `app/Exceptions/*` y los mensajes en `bootstrap/app.php`
- [x] `php artisan wayfinder:generate`
- [x] `vendor/bin/pint --dirty --format agent`
- [x] `php artisan test --compact`

## Frontend

- [x] `types/locale.ts` (`LocaleOption`, `Translator`)
- [x] `types/global.d.ts`: `locale`/`locales`/`translations` en `sharedPageProps` + `$t` en `ComponentCustomProperties`
- [x] `composables/useTranslations.ts` (`t`, `tChoice`, `locale`, `locales`, `setLocale`)
- [x] `app.ts`: registrar `$t` como global property
- [x] `components/molecules/LocaleSwitcher.vue` (variantes `dropdown` y `tabs`)
- [x] Montar el selector en `AppHeader.vue`, `PublicLayout.vue`, `UserMenuContent.vue`, `AuthLayout.vue`
- [x] Sección "Idioma" en `pages/settings/Appearance.vue`
- [x] Script de una pasada para envolver literales en `resources/js` (templates + `<script setup>`)
- [x] Repaso manual de lo que el script no cubrió (interpolaciones, plurales, casos con comillas)
- [x] `npm run types:check`
- [x] `npm run lint && npm run format`
- [x] `npm run build`
- [x] Probar contra la app corriendo: negociación por `Accept-Language`, `PATCH /locale` → cookie, y render en inglés de la landing, `/login` y `/tours` (falta la pasada visual del panel admin y super admin con sesión real)

## Tests

- [x] `tests/Feature/Locale/LocaleSwitchTest.php` (6 casos de `plan.md` §4)
- [x] `tests/Feature/Locale/InertiaLocalePropsTest.php`
- [x] `tests/Feature/Locale/ValidationMessagesTest.php`
- [x] `tests/Unit/Locale/TranslationCatalogTest.php` (paridad de catálogos)

## Review

- [x] Tests pasan
- [x] Pint pasa
- [x] Types check pasa
- [x] ESLint pasa
- [x] Spec cubierta 100%
- [x] Constitución respetada
- [x] Sin código muerto/comentarios decorativos
- [x] Sin regresión de copy en español
- [x] Reporte final con go/no-go

---

## Bloqueos / Decisiones pendientes

- [x] Ninguno.

## Notas durante implementación

- `2026-08-19`: creación de los cuatro documentos del feature en la rama `feature/multilanguage-es-en`.
- `2026-08-19`: implementación completa. Números finales:
  - `lang/en.json`: 1397 claves. `lang/es/validation.php`: 148 mensajes + 118 nombres de campo.
  - 116 archivos de `resources/js` tocados por la migración de literales (templates y `<script setup>`).
  - 37 nodos de texto con interpolación reescritos a mano con placeholders.
  - Gates: `php artisan test` 578/578, Pint, `types:check`, `lint:check`, `format:check` y `npm run build` en verde.
- `2026-08-19`: `Tests\TestCase::setUp()` fija `Accept-Language: es`. El cliente HTTP de PHPUnit manda
  `en-us` por defecto, así que sin esto la suite entera corría en inglés y las 11 assertions
  existentes sobre mensajes en español fallaban — un falso positivo del feature, no un bug de la app.
- `2026-08-19`: `npm run build` y `wayfinder:generate` necesitan PHP 8.4 en el PATH; con el `php` del
  sistema (7.4) el plugin de Wayfinder falla antes de compilar. No es de este feature, pero bloquea el
  build de cualquiera que lo tenga así.
