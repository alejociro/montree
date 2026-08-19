# multilanguage-es-en — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.

---

## PATCH /locale

Cambia el idioma activo de la sesión. Es una ruta web (Inertia), no `/api/v1`: la respuesta
esperada es una redirección que hace que la página se vuelva a renderizar en el idioma nuevo.

**Auth:** optional (funciona para invitado y para autenticado)
**Permission:** N/A

### Request

```json
{
  "locale": "en"
}
```

**Validación:**
| Campo | Tipo | Reglas |
|---|---|---|
| `locale` | string | required, string, `in:es,en` (los valores salen de `config('montree.locales')`) |

### Response 302

`back()` a la URL de origen. Efectos:

- Cookie `locale` = valor elegido, 1 año, `SameSite=Lax`, **sin encriptar** (declarada en
  `encryptCookies(except: [...])` junto a `appearance` y `sidebar_state`).
- Si hay usuario autenticado: `users.locale` queda con el valor elegido.
- La siguiente respuesta ya viaja con `locale` y `translations` del idioma nuevo.

### Errores

| Status | Caso | error_code | Mensaje |
|---|---|---|---|
| 422 | `locale` ausente, vacío o fuera de `config('montree.locales')` | — | mensaje de validación estándar, en el idioma activo |

---

## Props compartidas de Inertia (`HandleInertiaRequests::share`)

Se agregan tres props a las que ya existen (`name`, `auth`, `tenant`, `tenantConfiguration`,
`flash`, `sidebarOpen`). Están presentes en **toda** respuesta Inertia, autenticada o no.

```json
{
  "locale": "es",
  "locales": [
    { "code": "es", "name": "Español", "native": "Español" },
    { "code": "en", "name": "Inglés",  "native": "English" }
  ],
  "translations": {
    "Guardar cambios": "Save changes",
    "Quedan :count lugares": "{1} :count spot left|[2,*] :count spots left"
  }
}
```

| Prop | Tipo | Nota |
|---|---|---|
| `locale` | `string` | Código del idioma activo (`app()->getLocale()`). |
| `locales` | `LocaleOption[]` | Catálogo de idiomas soportados, para pintar el selector. `native` es el nombre en su propio idioma y no se traduce. |
| `translations` | `Record<string, string>` | Mapa completo de `lang/{locale}.json` del idioma activo. Para `es` llega prácticamente vacío: la clave **es** el texto en español (ver `plan.md` §5), así que no necesita entradas. |

`translations` viaja en cada respuesta, no como prop diferida: el primer render debe salir ya
traducido y el catálogo pesa pocos KB.

---

## Contrato del frontend — `useTranslations()` y `$t`

```ts
const { t, tChoice, locale, locales, setLocale } = useTranslations();
```

| Símbolo | Firma | Comportamiento |
|---|---|---|
| `t` | `(key: string, replacements?: Record<string, string \| number>) => string` | Busca `key` en `translations`; si no está, devuelve `key` (el texto en español). Sustituye `:name` por su valor. |
| `tChoice` | `(key: string, count: number, replacements?) => string` | Elige la forma plural con la misma sintaxis de `trans_choice` (`{0} …\|{1} …\|[2,*] …` o `singular\|plural`). Inyecta `:count` automáticamente. |
| `locale` | `ComputedRef<string>` | Idioma activo. |
| `locales` | `ComputedRef<LocaleOption[]>` | Catálogo soportado. |
| `setLocale` | `(code: string) => void` | `router.patch` a `PATCH /locale` vía Wayfinder, `preserveScroll: true`. |

`$t` es el mismo `t`, registrado como propiedad global en `app.ts` para poder usarse en
`<template>` sin importar nada. En `<script setup>` se usa `useTranslations()`.

**Tipos** (`resources/js/types/locale.ts`):

```ts
export type LocaleOption = { code: string; name: string; native: string };
export type Translator = (key: string, replacements?: Record<string, string | number>) => string;
```

`global.d.ts` extiende `sharedPageProps` con `locale`, `locales` y `translations`, y
`ComponentCustomProperties` con `$t: Translator`, para que `vue-tsc` valide su uso en templates.

---

## Eventos / Side-effects

- Al cambiar el idioma NO se dispara ningún evento de dominio ni job. Es preferencia de presentación.
- El idioma activo afecta a todo lo que resuelve `__()` / `trans_choice()` en el request en curso:
  mensajes de validación, `message` de las excepciones de dominio, y el contenido de las
  notificaciones enviadas dentro de ese request.
- Las notificaciones encoladas se renderizan con el idioma del **destinatario** (`$notifiable->locale`),
  no con el del request que las originó.

---

## Cambios al contrato

- `2026-08-19` — Creación inicial: `PATCH /locale`, props `locale` / `locales` / `translations`, contrato de `useTranslations()`.
