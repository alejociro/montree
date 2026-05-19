# F0XX — Contratos de API

> Shapes exactos de request y response. Es CONTRATO: backend y frontend
> se basan en este archivo. Modificar requiere acuerdo de ambos lados.

---

## POST /api/v1/...

**Auth:** required (customer) / optional / none
**Permission:** `<permission>` o N/A

### Request

```json
{
  "field": "string",
  "nested": { "key": "value" }
}
```

**Validación:**
| Campo | Tipo | Reglas |
|---|---|---|
| `field` | string | required, max:255 |
| `nested.key` | string | required, in:a,b,c |

### Response 201

```json
{
  "data": {
    "id": "uuid",
    "field": "string",
    "created_at": "2026-05-17T10:00:00Z"
  }
}
```

### Errores

| Status | Caso | error_code | Mensaje |
|---|---|---|---|
| 422 | Validación falló | — | "..." |
| 403 | Regla de negocio | `<UPPER_SNAKE>` | "..." |
| 409 | Conflicto | `<UPPER_SNAKE>` | "..." |

---

## GET /api/v1/...

(repetir formato)

---

## Eventos / Side-effects

- Al crear `<recurso>` se dispara `<EventName>` que: <qué hace>.
- Al confirmar `<recurso>` se encola `<JobName>` que: <qué hace>.

---

## Cambios al contrato

- `YYYY-MM-DD` — Cambio: <descripción>. Razón: <por qué>.
