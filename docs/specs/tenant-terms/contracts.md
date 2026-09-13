# Términos de la agencia — Contratos

---

## GET /terminos → page `Policies/Terms`

**Auth:** ninguna, es pública. **Host:** el subdominio del tenant, **no** el de
plataforma. No va dentro del grupo `Route::domain(platform_host)`.

### Props

```json
{
  "terms": {
    "html": "<h2>Cancelaciones</h2><p>Podés cancelar…</p>",
    "is_default": false,
    "updated_at": "2026-09-13T10:00:00-05:00"
  },
  "agency": { "name": "Demo Eco Adventures" }
}
```

- `html` ya viene convertido y saneado. La page lo pinta con `v-html` **sin**
  volver a procesarlo: el saneo es del servidor y no se repite en el cliente.
- `is_default` es `true` cuando la agencia no escribió los suyos. La página no lo
  muestra al viajero; sirve para la vista previa del panel.
- `updated_at` es `null` cuando son los de por defecto.

### Conversión

```php
Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
```

Verificado: `<script>` y `<img onerror>` desaparecen, `[x](javascript:…)` queda
sin `href`, y un enlace `https://` se conserva.

---

## PUT /api/v1/admin/tenant/configuration

Endpoint existente. Suma un campo:

| Campo | Tipo | Reglas |
|---|---|---|
| `terms_body` | string | nullable, max:20000 |

- `null` o solo espacios se guardan como `null`: vuelve al texto por defecto.
- El límite de 20.000 caracteres evita que alguien pegue un documento entero sin
  querer; alcanza de sobra para unos términos normales.

### Response

`TenantConfigurationResource` suma:

```json
{
  "terms_body": "## Cancelaciones\n\nPodés…",
  "terms_is_default": false
}
```

`terms_body` viaja **crudo** (es lo que se edita); `terms_is_default` dice si hoy
rige el texto de Montree.

---

## GET /admin/tenant/configuration → page `Admin/Tenant/Configuration`

Deja de ser `Route::inertia(...)`: pasa a `TenantConfigurationPagesController`,
que suma una prop propia.

```json
{ "terms": { "body": "## Cancelaciones\n\nPodés…", "is_default": false } }
```

**Por qué una prop de página y no la compartida.** `tenantConfiguration` viaja en
**toda** respuesta Inertia, incluido el catálogo público; el cuerpo admite 20.000
caracteres. El middleware lo excluye con `Arr::except`, y hay un test
(`test_the_raw_terms_never_travel_in_the_shared_props`) que lo fija. La respuesta
del `PUT` sí lo devuelve: ahí es lo que se acaba de guardar.

---

## Enlace del checkout

`Booking/Create.vue` tiene hoy `href="/terms"`, escrito a mano y **sin ruta
detrás**: de ahí el 404. Pasa a Wayfinder, como manda la constitución §4.2.

El texto del checkout dice «términos y condiciones y la política de
cancelación». Con un documento único, la segunda mención sobra: queda solo
«términos y condiciones».

---

## Texto por defecto

`resources/policies/terms.{locale}.md`, con fallback al idioma por defecto. Se
lee con `File::get()` y se cachea por idioma.

No va en `lang/en.json`: es un documento largo y como clave de traducción
ensuciaría el catálogo y reventaría su test.

## Changelog

- `2026-09-13` — Creación.
