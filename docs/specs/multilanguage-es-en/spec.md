# multilanguage-es-en — Soporte multilenguaje (español / inglés)

> Spec funcional. Lo que el feature hace desde la óptica del usuario.
> Cambios a este archivo requieren actualizar [`tasks.md`](./tasks.md) y registrar en `## Changelog`.

---

## Descripción

Toda la interfaz de MONTREE está hoy escrita en español, literal por literal, dentro de los 265
componentes Vue y de los mensajes de excepción del backend. `config/app.php` declara `locale => 'en'`
y `fallback_locale => 'en'`, y `lang/` solo tiene tres archivos (`es.json` con 3 claves de Fortify,
`es/auth.php`, `es/passwords.php`), así que ni siquiera las validaciones salen en español: el usuario
ve "The name field is required." mientras el resto de la pantalla está en castellano.

Este feature introduce un único sistema de traducciones —el nativo de Laravel (`lang/es.json`,
`lang/en.json`, más los archivos PHP del framework)— compartido al frontend por Inertia, con un
selector de idioma visible en la interfaz que cambia el idioma de toda la aplicación (UI, validaciones,
mensajes de error de dominio y correos) y recuerda la preferencia entre sesiones.

No se instala `vue-i18n`: mantener dos catálogos en paralelo (uno para backend, otro para frontend)
duplicaría cada clave y abriría la puerta a que se desincronicen. La contrapartida —renunciar a la
pluralización avanzada e interpolación de `vue-i18n`— se cubre con `trans_choice`, que Laravel ya
implementa y que el composable expone al frontend.

## User stories

- Como visitante del sitio público de una agencia, quiero cambiar el idioma a inglés desde el header, para leer el catálogo y reservar sin saber español.
- Como visitante, quiero que mi idioma se mantenga al navegar entre páginas y al volver otro día, para no tener que elegirlo cada vez.
- Como usuario autenticado (viajero o staff), quiero que mi idioma quede guardado en mi cuenta, para verlo igual desde cualquier navegador o dispositivo.
- Como administrador de agencia, quiero que el panel de administración completo esté disponible en inglés, para operar con equipo que no habla español.
- Como cualquier usuario, quiero que los mensajes de validación de los formularios y los errores de negocio salgan en mi idioma, para entender qué corregir.
- Como equipo de desarrollo, quiero un único catálogo de traducciones para backend y frontend, para no mantener dos sistemas en paralelo ni duplicar claves.

## Acceptance criteria

### Selección de idioma desde la interfaz

- **Given** un visitante no autenticado en cualquier página pública, **when** abre el selector de idioma del header y elige "English", **then** la página se recarga en inglés y el selector queda marcando "English".
- **Given** un visitante que ya cambió el idioma a inglés, **when** navega a otra página o cierra y reabre el navegador (dentro de un año), **then** la aplicación sigue en inglés sin volver a elegirlo.
- **Given** un usuario autenticado, **when** cambia el idioma, **then** la preferencia se guarda en su cuenta (`users.locale`) y se aplica al iniciar sesión desde otro navegador.
- **Given** un usuario autenticado en el panel de administración, **when** abre el menú de usuario, **then** ve el selector de idioma con las mismas dos opciones y el mismo efecto.
- **Given** un usuario cambia el idioma desde `/settings/appearance`, **when** guarda, **then** el cambio aplica igual que desde el header (misma ruta backend, un solo mecanismo).

### Cobertura de la traducción

- **Given** el idioma activo es inglés, **when** el usuario recorre cualquier página pública (home, catálogo, detalle de tour, checkout de reserva, políticas, errores), **then** no queda texto visible en español.
- **Given** el idioma activo es inglés, **when** el usuario recorre el panel de administración, el área de super admin, la de guía, "Mi cuenta", onboarding de agencia y las pantallas de autenticación, **then** no queda texto visible en español.
- **Given** el idioma activo es español, **when** el usuario recorre la aplicación, **then** ve exactamente los mismos textos que antes de este feature (sin regresiones de copy).
- **Given** cualquier pantalla, **when** se inspecciona el `<title>` del documento, **then** también está traducido.

### Validaciones y mensajes del backend

- **Given** el idioma activo es español, **when** un formulario falla validación (campo requerido, email inválido, mínimo de caracteres, valor único), **then** el mensaje llega en español.
- **Given** el idioma activo es inglés, **when** el mismo formulario falla, **then** el mensaje llega en inglés.
- **Given** el idioma activo es inglés, **when** se dispara una excepción de dominio (`BookingException`, `PromotionInvalidException`, `TeamException`, `RoleException`, cupo insuficiente, límite de plan alcanzado…), **then** el `message` del response está en inglés.
- **Given** un usuario con idioma inglés recibe un correo transaccional (verificación de email, reset de contraseña, confirmación de reserva), **when** lo abre, **then** el correo está en inglés.
- **Given** el atributo de un campo aparece en un mensaje de validación, **when** el idioma es español, **then** el nombre del campo también está traducido ("El campo correo electrónico es obligatorio", no "El campo email…").

### Resolución del idioma

- **Given** una petición sin cookie ni usuario autenticado, **when** el navegador envía `Accept-Language: en-US`, **then** la aplicación responde en inglés.
- **Given** una petición sin cookie, sin usuario y sin `Accept-Language` reconocible, **when** se resuelve el idioma, **then** se usa el idioma por defecto de la aplicación (español).
- **Given** un usuario autenticado con `locale = 'en'` y una cookie con `es`, **when** hace una petición, **then** manda la preferencia de la cuenta (inglés).
- **Given** se solicita un idioma no soportado (`fr`, `xx`, vacío), **when** llega al endpoint de cambio de idioma, **then** responde 422 y el idioma activo no cambia.

## Edge cases

- **Clave sin traducir en inglés**: `__()` devuelve la clave, que es el texto en español. La pantalla no se rompe; el hueco se detecta con el test de paridad de catálogos, que falla el build.
- **Textos con interpolación** (`"Quedan :count lugares"`): se traducen con placeholders `:name`, nunca concatenando fragmentos, para que el orden de las palabras pueda cambiar entre idiomas.
- **Plurales** (`"1 reserva" / "5 reservas"`): se resuelven con `trans_choice` / `tChoice(key, count)`; el inglés y el español tienen las mismas dos formas, pero el mecanismo queda listo para un tercer idioma.
- **Contenido cargado por la agencia** (nombre y descripción de tours, categorías, itinerarios, políticas propias del tenant): NO se traduce. Es dato del tenant, no copy de la aplicación. Se muestra tal cual lo cargó la agencia en ambos idiomas.
- **Cookie y usuario en conflicto**: gana el usuario; el cambio de idioma reescribe también la cookie para que ambos queden alineados.
- **Cambio de idioma en un formulario a medio llenar**: el cambio recarga la página; el usuario pierde lo escrito. Se acepta (mismo comportamiento que el cambio de apariencia) y por eso el selector no se coloca dentro de formularios largos.
- **SSR / primer render**: las traducciones viajan como prop compartida de Inertia, así que el primer render ya sale en el idioma correcto — no hay parpadeo de idioma.

## Dependencias

- F001 (Auth) — la preferencia se persiste en `users`, y las pantallas de autenticación son parte de la cobertura.
- F002 (Tenant resolution) — el middleware de idioma corre después de resolver el tenant, para no interferir con la resolución de subdominio.
- Ninguna otra: el feature es transversal, no bloquea ni es bloqueado por features de dominio.

## Endpoints involucrados

```
PATCH  /locale
```

(Detalle en [`contracts.md`](./contracts.md))

## Componentes UI

- Pages: `settings/Appearance.vue` (agrega la sección de idioma). El resto de las 50 páginas se traducen, no cambian de estructura.
- Organisms: `AppHeader.vue`, `UserMenuContent.vue`, `AdminSidebar.vue`, `SuperAdminSidebar.vue` (alojan el selector).
- Molecules: `LocaleSwitcher.vue` (nuevo).
- Composables: `useTranslations()` (nuevo) y `$t` global en templates.

## Datos requeridos

Tablas: `users` (columna nueva `locale`).

---

## Out of scope (explícitamente NO se hace)

- Traducir el contenido que carga la agencia (tours, categorías, itinerarios, imágenes, políticas del tenant).
- Un tercer idioma (portugués u otro). La arquitectura lo permite, pero este feature entrega es/en.
- Traducción automática de contenido con servicio externo.
- URLs localizadas (`/en/tours`, `/es/tours`) ni `hreflang` para SEO multilenguaje.
- Formatos regionales de fecha, número y moneda por idioma (queda como deuda anotada en `plan.md` §6).
- Idioma por defecto configurable por tenant.

## Decisiones abiertas

- [ ] Ninguna. Las dos que había —catálogo único vs. `vue-i18n`, y clave semántica vs. texto fuente— quedaron resueltas en `plan.md` §5.

---

## Changelog

- `2026-08-19` — Creación inicial.
- `2026-08-19` — Segunda pasada: auditoría vista por vista con la app en inglés
  (navegación real del panel de agencia, super admin, guía, cuenta y público). Cierra
  cinco huecos que la primera pasada no cubría:
  1. **Formato de fecha, hora, moneda y número** estaba clavado en `es-CO`
     (`lib/format.ts` y ~20 llamadas sueltas a `Intl`): una pantalla en inglés mostraba
     "24 de agosto de 2026" y "hace 2 minutos". Ahora `intlLocale()` resuelve la etiqueta
     BCP-47 desde el idioma activo.
  2. **Copy crudo dentro de interpolaciones** (`{{ saving ? 'Guardando…' : 'Guardar cambios' }}`):
     69 textos en 34 componentes. Cubierto por un test nuevo.
  3. **Catálogos traducidos en el punto de render** sin entrada en `lang/en.json`
     (las 17 etiquetas de respaldo de `config/permissions.ts`, `Subdominio`, `Menú`,
     los badges de estado de tour y tenant, las dificultades `Moderado`/`Extremo`).
  4. **Gramática del castellano armada en código**: `LogisticsCrudPanel` componía
     "Nueva"/"Nuevo" + `singular` y el sufijo `eliminad{a|o}`, que en inglés daba
     "Nueva route". Reemplazado por copy completo por recurso.
  5. **Páginas de error**: una ruta inexistente no pasa por el grupo `web`, así que ni
     `SetLocale` ni `HandleInertiaRequests` corrían y el 404 salía siempre en español.
     La cadena de resolución se mudó a `Locale::resolveFor()` y `GenericErrorController`
     comparte `locale`/`locales`/`translations`.

  Además: `trans_choice()` no se comporta como `__()` — si la clave no existe para el
  idioma activo, Laravel resuelve el mensaje en el `fallback_locale`, así que una clave
  plural ausente de `lang/es.json` salía **en inglés dentro de la app en español**. Las
  claves de `trans_choice` llevan entrada identidad en `lang/es.json` y hay un test que
  lo verifica.
- `2026-08-20` — Tercera pasada: recorrido con navegador real sobre la app compilada,
  con sesión iniciada por cada rol (agencia, super admin, guía, cliente) y el idioma
  forzado a inglés. Recorridas 30 vistas entre los dos dominios (`demo.montree.test`
  y el central). Quedaban tres textos crudos, todos dentro de interpolaciones o
  atributos, que la pasada anterior no alcanzó:
  1. `Admin/Dashboard.vue` — el subtítulo `Resumen de la operación de ${tenant.name}`
     se armaba como template literal. Ahora es `$t('Resumen de la operación de :agency.')`
     con el nombre como reemplazo.
  2. `Admin/Newsletter/Index.vue` — el rótulo del botón de envío contaba suscriptores
     en un template literal. Pasa a `$tc` con las tres formas (cero, singular, plural).
  3. `molecules/TourCard.vue` — el `aria-label` del botón de favorito era literal en
     castellano. Un atributo no se ve en pantalla, así que ninguna revisión visual lo
     detecta: solo lo encontró el volcado de `aria-label`/`placeholder`/`title`.

  El resto de lo que aparece en castellano con la app en inglés es **contenido
  sembrado** (nombres de tours de demo, la descripción del tenant, los hoteles), no
  copy de interfaz: no se traduce.
