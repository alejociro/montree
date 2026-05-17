# F0XX — Plan técnico

> Decisiones técnicas para implementar este feature.
> Backend, frontend, base de datos, tests.

---

## 1. Resumen

<2–3 líneas: cómo se implementa en general>

## 2. Backend

### Modelos

- `<ModelA>` — nuevo / extendido. Cambios: ...
- `<ModelB>` — relación con A: ...

### Migrations

- `XXXX_XX_XX_xxxxxx_create_<tabla>_table.php` (si schema ya existe, NO duplicar)
- Cambios a tablas existentes — abrir PR aislado primero.

### Actions

- `App\Actions\<Domain>\<VerbObjectAction>` — hace ...
- `App\Actions\<Domain>\<VerbObjectAction>` — hace ...

### Form Requests

- `App\Http\Requests\<Domain>\<StoreXxxRequest>` — valida ...
- `App\Http\Requests\<Domain>\<UpdateXxxRequest>` — valida ...

### Controllers

- `App\Http\Controllers\Api\V1\<Domain>\<XxxController>`
- Métodos: `index, show, store, update, destroy` (los necesarios)

### Resources

- `App\Http\Resources\<XxxResource>` — shape: ver `contracts.md`

### Policies

- `App\Policies\<XxxPolicy>` — gates: ...

### Services / DTOs

- (solo si aplica regla del 3)

### Jobs / Notifications / Events

- `App\Jobs\<XxxJob>` — para ...
- `App\Notifications\<XxxNotification>` — canales: mail, database

## 3. Frontend

### Pages

- `resources/js/pages/<Path>/<Page>.vue` — ruta ...

### Composables

- `useXxx()` — ...

### Organisms / Molecules / Atoms

- Reutilizar: ...
- Nuevos: ...

### Types

- `xxx.types.ts` — interfaces ...

### Wayfinder

- Tras backend listo: `php artisan wayfinder:generate`
- Imports esperados: `@/actions/<Domain>/<Controller>` / `@/routes/...`

## 4. Tests

### Feature tests (backend)

- `tests/Feature/<Domain>/<XxxTest>.php`
- Cobertura:
  - `test_<happy>` ...
  - `test_<failure>` ...
  - `test_<edge>` ...
  - `test_<tenant_isolation>` (si aplica)

### Unit tests

- (solo si hay clases puras complejas)

## 5. Decisiones tomadas

- **<Decisión>**: <opción elegida>. Razón: <por qué>.
- **<Decisión>**: <opción elegida>. Razón: <por qué>.

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| ... | media | ... |

## 7. Out of scope explícito

- ...
