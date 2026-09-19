# administration-upgrades — Ajustes de administración (super admin + admin tenant)

> Spec funcional. Rama: `feature/implement-administration-upgrades`.
> Todo lo nuevo o modificado aquí se sirve por **rutas web + Inertia** (props, `useForm`/`<Form>`,
> redirect con flash). No se agregan endpoints `/api/v1/*`; los que se toquen se migran y se eliminan.

---

## Descripción

Conjunto de mejoras y correcciones de los dos paneles administrativos. Del lado de plataforma:
acceso directo a cada tenant desde la tabla, modelo de cobro por reserva configurable por tenant
con su registro de cargos, y gráficas de seguimiento. Del lado del tenant: corrección del branding
(logo, colores del footer, imagen principal), y un rediseño de la relación producto → rutas →
salidas. De paso se retira la exportación a CSV (vuelve en un feature posterior) y se corrige el
"Recordarme" del login.

## User stories

### Super admin
- Como super admin, quiero entrar al panel de un tenant desde su fila en la tabla, sin pasar por el detalle.
- Como super admin, quiero configurar por tenant cómo cobra la plataforma (porcentaje o monto fijo por reserva) y ver el registro de cargos generados.
- Como super admin, quiero ver en el dashboard gráficas de tenants registrados por mes, ingresos por tenant por mes y ganancias de la plataforma.

### Admin del tenant
- Como admin, quiero que el logo, la imagen principal y los colores que configuro se vean en el panel, en la landing y en el footer.
- Como admin, quiero subir el logo y la imagen principal desde mi propia configuración, sin depender del super admin.
- Como admin, quiero definir varias rutas para un producto y, al crear una salida, elegir con cuál se hace; la salida hereda la información del producto y puedo ajustarla.
- Como viajero, quiero que al elegir una salida el detalle del tour muestre la ruta y condiciones de esa salida.

### Transversal
- Como usuario, quiero que "Recordarme" mantenga mi sesión aunque el login me lleve a otro subdominio.

## Acceptance criteria

### A. Entrar al tenant desde la tabla
- **Given** la tabla de tenants, **then** ya no existe la columna con el botón "Detalle"; el nombre del tenant enlaza a su detalle.
- **Given** una fila de un tenant `active`, **when** hago clic en la fila (o en el botón "Entrar"), **then** se abre en una pestaña nueva el panel admin del tenant (`https://<slug>.<platform_host>/admin/dashboard`) con mi sesión de super admin.
- **Given** un tenant `suspended` o `pending`, **then** la fila no permite entrar y muestra el motivo.
- **Given** un super admin en el host de un tenant, **then** los middlewares de panel lo dejan pasar sin membresía y ve el panel completo; un usuario normal sin membresía sigue recibiendo 403.

### B. Cobro a las agencias
- **Given** el detalle de un tenant, **when** configuro tipo `percentage` con valor `X` o tipo `fixed` con monto `Y`, **then** queda guardado y se muestra en el detalle y en la tabla.
- **Given** un tenant con cobro configurado, **when** una reserva de ese tenant queda **confirmada** (primer pago completado), **then** se genera exactamente un cargo de plataforma para esa reserva con el monto calculado sobre `total_amount` (porcentaje) o el monto fijo. Reservas ya cargadas no generan segundo cargo.
- **Given** un tenant sin cobro configurado, **when** se confirma una reserva, **then** no se genera cargo.
- **Given** el registro de cargos de un tenant, **then** lista fecha, reserva, base, tipo, valor aplicado, monto cobrado y moneda, paginado, con filtro por rango de fechas y totales.
- **Given** el dashboard de plataforma, **then** "Ganancias" es la suma de cargos del período (ya no el 3% hardcodeado).

### C. Gráficas del super admin
- **Given** el dashboard, **then** muestra: (1) tenants registrados por mes (últimos 12 meses) con el promedio mensual, (2) ingresos por tenant por mes (pagos completados, últimos 6 meses, top 8 tenants), (3) ganancias de la plataforma por mes (cargos) y el acumulado.
- **Given** el detalle de un tenant, **then** muestra reservas por mes y cargos por mes (últimos 12 meses) más los KPIs (reservas, ingresos, cargos acumulados).
- Las gráficas son componentes SVG propios (sin dependencia nueva).

### D. Branding del tenant
- **Given** un tenant con logo, **then** el logo se ve en el sidebar del panel, en el header de la landing pública, en el footer y en las pantallas de auth; si el archivo no carga, se muestra el nombre del tenant.
- **Given** la configuración del tenant, **when** subo logo, favicon o imagen principal, **then** se guardan y se reflejan de inmediato (landing usa la imagen principal como hero).
- **Given** colores primario/secundario configurados, **then** el footer público los usa (fondo derivado del primario, textos legibles), igual que el header y el panel.
- **Given** la página de configuración, **when** la abro y guardo sin tocar colores, **then** no se reescriben colores que no cambié; los colores del panel se mantienen consistentes con el resto de pantallas `Admin/*`.
- **Given** el panel de plataforma, **then** el sidebar muestra "MONTREE Platform" siempre; el sidebar del tenant muestra el nombre o logo del tenant.
- La configuración del tenant se envía por ruta web (multipart) con `useForm`; se elimina el endpoint API correspondiente.

### E. Recordarme
- **Given** login con "Recordarme" en el host de plataforma que termina en un subdominio, **then** el subdominio recibe la cookie recaller y la sesión sobrevive a expirar la sesión.
- **Given** login sin "Recordarme", **then** no se emite recaller en ningún host.

### F. Quitar exportación
- Se eliminan los tres endpoints de exportación (manifiesto admin, manifiesto guía, reporte de ingresos), sus actions, requests, botones, composables, claves de idioma y el permiso `reports.export`. Tests asociados se eliminan o podan.

### G. Producto con varias rutas y salidas que heredan
- **Given** el formulario de producto, **then** tiene una sección "Rutas" donde asocio 0..N rutas del catálogo de logística y marco una como predeterminada; el detalle del producto lista sus rutas.
- **Given** una ruta asociada a un producto, **when** intento eliminarla en logística, **then** 409 con los productos/salidas que la usan.
- **Given** creo una salida, **then** el formulario llega precargado con: guía por defecto, `capacity = default_capacity`, ruta predeterminada, precio base como referencia y % mínimo de la agencia; el selector de ruta solo ofrece las rutas del producto (o "Sin ruta"). Puedo modificar cualquiera antes de guardar.
- **Given** una salida existente, **when** la edito, **then** puedo cambiar la ruta entre las del producto; una ruta que no pertenece al producto es rechazada (422).
- **Given** el detalle público del tour, **when** elijo una salida con ruta, **then** la sección de ruta/mapa y la ficha logística muestran las paradas y datos de **esa** ruta; sin ruta, muestran las paradas del producto.
- Las pantallas de producto, salidas y logística que se toquen pasan a rutas web + Inertia.

### H. Moneda única por tenant
- **Given** la configuración del tenant (panel admin y panel super admin), **then** la moneda es un campo configurable con lista soportada; el super admin la define al crear el tenant y ambos pueden cambiarla después.
- **Given** un producto, **then** ya no tiene moneda propia en el formulario: hereda la del tenant en cada alta/edición y todas las pantallas (catálogo, detalle, home, reservas, salidas, transacciones, panel) formatean con la moneda del tenant.
- **Given** proveedores y hoteles con tarifas, **then** su moneda por defecto es la del tenant.
- **Given** un cambio de moneda del tenant, **then** los productos pasan a mostrar la nueva moneda (los importes no se convierten); reservas y pagos históricos conservan la moneda con la que se registraron.
- **Given** el dashboard de plataforma con tenants en monedas distintas, **then** ingresos y ganancias se presentan agrupados por moneda, nunca sumados entre monedas.

### I. Rutas dentro del producto (reemplaza a G en lo relativo a rutas)
- **Given** el formulario de edición de un producto, **then** tiene una sección "Rutas" donde creo, edito y elimino rutas **del producto** (nombre, descripción, tipo, dificultad, distancia, duración, notas de seguridad, equipo, paradas con hora y coordenadas) y marco una como predeterminada. Una ruta pertenece a un solo producto.
- **Given** un producto recién creado, **then** la sección "Rutas" queda disponible al guardarlo (en el alta se muestra el aviso "guarda el producto para agregar rutas").
- **Given** el módulo Logística, **then** ya no tiene pestaña "Rutas"; conserva proveedores y hoteles.
- **Given** una ruta usada por salidas futuras no canceladas, **when** intento eliminarla, **then** se rechaza con el detalle de las salidas; una ruta usada solo por salidas pasadas o canceladas se puede eliminar y esas salidas quedan sin ruta.
- **Given** una salida, **then** el selector de ruta ofrece "Sin ruta" y las rutas del producto; una ruta de otro producto es rechazada (422).

## Módulo de categorías

Las categorías eran datos sembrados que solo se podían tocar por SQL. Pasan a tener
panel propio (`/admin/categories`), con ícono o imagen, orden y estado.

- **Given** el panel, **when** abro "Categorías", **then** veo las categorías del tenant
  en su orden de exhibición, cada una con su cara visible (imagen si la subí, si no el
  ícono), nombre, descripción, cuántos productos la usan y si está activa.
- **Given** el diálogo de alta o edición, **then** elijo el ícono de una grilla con el set
  curado de 16 íconos Lucide, o subo una imagen (PNG, JPG, SVG o WEBP de hasta 1 MB).
  Si hay imagen, manda la imagen. "Quitar imagen" borra el archivo del disco al guardar.
- **Given** dos categorías con el mismo nombre, **then** la segunda recibe un slug con
  sufijo numérico (`aventura-2`): el slug es único por tenant y es la llave del filtro
  público.
- **Given** una categoría con productos, **when** intento eliminarla, **then** se rechaza
  nombrando cuántos productos la usan y hasta tres de ellos, y el mensaje propone
  desactivarla. Sin productos, se elimina junto con su imagen.
- **Given** la lista, **when** arrastro una fila o uso los botones subir/bajar, **then**
  el nuevo orden se guarda (`PATCH admin/categories/reorder`) y es el que ve el viajero
  en el home, el catálogo y los filtros.
- **Given** una categoría inactiva, **then** desaparece del home, del catálogo y de sus
  filtros, y del selector del formulario de producto; los productos que ya la tienen la
  conservan y el formulario de edición la sigue ofreciendo para que guardar no la borre.
- **Given** los roles, **then** `admin` y `operator` pueden verla y gestionarla, `sales`
  solo verla y `guide` no la ve. El catálogo de permisos pasa de 39 a 41.

## Módulos desactivables

Newsletter y Promociones existen en el producto pero no son foco. Se apagan con un
interruptor de configuración (`config/montree.php` → `modules`, alimentado por
`MONTREE_MODULE_NEWSLETTER` y `MONTREE_MODULE_PROMOTIONS`, ambas en `false` por
defecto). Apagar **no** borra nada: el código, el seeder de permisos y las filas de
`permissions` y `model_has_permissions` se quedan como están, así que encender el
módulo es cambiar la variable y limpiar la caché de configuración.

- **Given** un módulo apagado, **when** pido cualquiera de sus rutas web o API
  —pública o de panel—, **then** recibo **404**, aunque tenga el permiso. Es 404 y no
  403 porque un 403 confirmaría que la pantalla existe.
- **Given** un módulo apagado, **then** su ítem no aparece en el menú y sus permisos
  no se listan en la pantalla de roles.
- **Given** un rol propio que ya tenía permisos del módulo apagado, **when** lo edito
  desde la pantalla de roles, **then** esos permisos se conservan (la pantalla no los
  muestra, así que tampoco los puede quitar sin querer).
- **Given** `promotions` apagado, **then** la home pública no trae la prop
  `promotions` (ni ejecuta su consulta) y no pinta la sección "Promociones
  especiales"; el checkout rechaza `promotion_code` con 422 y `CreateBookingAction`
  no consulta `ValidatePromotionAction`.
- **Given** `newsletter` apagado, **then** el enlace público de baja
  (`/unsubscribe/{token}`) y el alta/baja por API responden 404, y la campaña no se
  puede enviar desde el panel.

Qué apaga cada interruptor, exactamente:

| Flag | Rutas que pasan a 404 | Otros efectos |
| --- | --- | --- |
| `MONTREE_MODULE_NEWSLETTER` | `newsletter.unsubscribe.page`, `api.v1.newsletter.{subscribe,unsubscribe}`, `admin.newsletter.index`, `api.v1.admin.newsletter.{subscribers,send,send-test,subscribers.unsubscribe}` | Ítem "Newsletter" fuera del menú; permisos `newsletter.*` fuera del catálogo de roles |
| `MONTREE_MODULE_PROMOTIONS` | `admin.promotions.index`, `api.v1.promotions.validate`, `api.v1.admin.promotions.*` (index/store/show/update/destroy) | Ítem "Promociones" fuera del menú; permisos `promotions.*` fuera del catálogo; home sin sección ni prop `promotions`; `promotion_code` prohibido en el checkout |

La suite corre con los dos módulos **encendidos** (`phpunit.xml`), que es el producto
completo; apagarlos es el caso especial y lo declara `tests/Feature/Modules/ModuleFlagsTest`
con `config()->set`.

## Edge cases

- Tenant con cobro `fixed` en moneda distinta a la de la reserva: el cargo se registra en la moneda del tenant (`tenant_configurations.currency`) sin conversión.
- Cambio de porcentaje después de reservas cargadas: los cargos anteriores no se recalculan (se guarda el valor aplicado en cada cargo).
- Cancelación de reserva después del cargo: el cargo se conserva (sin reversos en este feature).
- Ruta predeterminada eliminada del producto: la próxima salida se crea sin ruta preseleccionada.
- Producto sin rutas: el selector de ruta de la salida solo ofrece "Sin ruta".
- Super admin que además es miembro del tenant: entra como super admin igual.
- Ruta desasociada de un producto que ya tiene salidas creadas con ella: al guardar
  las rutas del producto, las salidas **futuras y no canceladas** quedan con
  `route_id = null` (vuelven a "Sin ruta"); las pasadas y las canceladas conservan
  la ruta con la que se operaron, porque ahí el dato es histórico.

## Dependencias

- F015 (super admin), F016 (handoff cross-host), F017 (productos/salidas), payments-transactions-admin (`ResolvePaymentAction`).

## Out of scope

- Facturación/cobro real a la agencia (el registro es contable, no transaccional).
- Migrar a Inertia los endpoints API de módulos que este feature no toca (promociones, reseñas, newsletter, equipo, roles, favoritos, cuenta). Queda como feature siguiente `api-to-inertia-migration`.
- Exportación a CSV (feature posterior).
- Coordenadas obligatorias en paradas de ruta (se agregan como opcionales para poder pintar el mapa).

## Changelog

- `2026-09-19` — Nueva sección "Módulo de categorías": CRUD del panel con ícono
  (enum `CategoryIcon`, 16 nombres Lucide) o imagen (`categories.image_path`, disco
  `public`), orden arrastrable y estado activo; permisos `categories.view` /
  `categories.manage` (catálogo 39 → 41). Razón: pedido del usuario — las categorías
  eran datos sembrados sin pantalla, y el ícono no se podía cambiar sin tocar la base.

- `2026-09-18` — Nueva sección "Módulos desactivables": interruptor por módulo para
  apagar `newsletter` y `promotions` mientras no son foco (404 en sus rutas, fuera del
  menú y del catálogo de permisos, sin tocar el seeder ni las filas de `permissions`).
  Razón: pedido del usuario — los dos módulos están implementados pero no se van a
  ofrecer todavía, y borrarlos costaría más que volver a montarlos.

- `2026-09-16` — Moneda única por tenant (criterio H): el producto deja de tener moneda propia; se agrupa por moneda en plataforma. Rutas dentro del producto (criterio I): `routes.tour_id` reemplaza al pivote `route_tour`; Logística pierde la pestaña de rutas. Razón: decisión del usuario tras el primer cierre — un tenant nunca combina monedas y una ruta nunca pertenece a dos productos.

- `2026-09-15` — Creación a partir del pedido de ajustes de administración y del diagnóstico del código actual.
- `2026-09-15` — Edge case nuevo: desasociar una ruta del producto limpia `route_id`
  en las salidas futuras y no canceladas. Razón: el review post-implementación (P2-5)
  encontró que `SyncTourRoutesAction` dejaba salidas apuntando a una ruta que el
  producto ya no ofrece, y la spec no decía qué debía pasar.
