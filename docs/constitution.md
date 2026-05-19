# MONTREE — Constitución técnica

> Documento inmutable que define cómo se escribe código en este proyecto.
> Cualquier desviación requiere PR explícito que modifique esta constitución primero.

---

## 1. Stack confirmado

- **PHP** 8.4 (strict types siempre)
- **Laravel** 13
- **Inertia** 3 + **Vue** 3 (Composition API, `<script setup lang="ts">`)
- **Tailwind** v4
- **Sanctum SPA** (cookies, no tokens) vía Fortify
- **Wayfinder** para rutas tipadas en frontend
- **PHPUnit** 12 (no Pest)
- **Pint** para formato
- **Sail** para entorno local
- **Laravel Boost MCP** para introspección de schema/logs/docs

**Paquetes a instalar (requieren PR aprobado):**
- `spatie/laravel-multitenancy` — estrategia single DB + tenant_id
- `spatie/laravel-permission` — RBAC granular
- `laravel/cashier` — Stripe (cuando se aborde F007)

---

## 2. Principios de diseño

1. **Sin sobreingeniería.** Si tres líneas similares funcionan, no abstraigas. Solo se abstrae cuando hay 3+ usos reales (regla del 3).
2. **Sin código muerto.** No se commitea código "por si acaso", flags inactivos, ni `@deprecated` que nadie llama.
3. **Sin comentarios decorativos.** Solo se comenta el WHY no obvio (invariante oculta, workaround puntual). El QUÉ lo dicen los identificadores.
4. **Early returns.** Anidamiento máximo 2 niveles. Si necesitas más, extrae método.
5. **Una sola responsabilidad por clase/función.** Si el nombre tiene "And", se divide.
6. **Inmutabilidad por defecto.** `readonly` en DTOs, `final` en clases que no se extienden, parámetros sin reasignar.
7. **Fail fast.** Validar en el borde (Form Request), confiar adentro. No re-validar lo que ya se validó.

---

## 3. Backend — Arquitectura

### 3.1 Capas

```
HTTP Request
    │
    ▼
[Form Request]   ← valida y autoriza input
    │
    ▼
[Controller]     ← delgado: resuelve dependencias, invoca action, retorna response
    │
    ▼
[Action]         ← un caso de uso, un verbo de negocio
    │
    ├─► [Model / Eloquent]
    ├─► [Service]    (solo si lógica compartida entre 2+ actions)
    └─► [Job / Notification / Event]
    │
    ▼
[API Resource]   ← shape del response JSON
```

### 3.2 Reglas por capa

**Controllers** (`app/Http/Controllers/...`)
- Máximo 10 líneas por método.
- NO validan input → eso es del Form Request.
- NO contienen lógica → solo orquestación.
- Inyectan Actions por constructor (property promotion).
- Métodos RESTful: `index`, `show`, `store`, `update`, `destroy`. Si necesitas más → controller de acción única (`__invoke`).

**Form Requests** (`app/Http/Requests/...`)
- Una por endpoint que reciba input.
- `authorize()` siempre implementado (no `return true` ciego — usar Policy/Gate).
- `rules()` declarativo, sin lógica condicional compleja (extraerla a método privado).
- Mensajes personalizados solo si el default es ambiguo.
- DTOs derivados con `validated()` tipado.

**Actions** (`app/Actions/...`)
- Una clase = un caso de uso = un verbo: `CreateBookingAction`, `ConfirmPaymentAction`, `ApproveReviewAction`.
- Método público único: `handle(...)` o `__invoke(...)`.
- Recibe primitivos o DTO, retorna modelo/DTO/void.
- NO conoce HTTP (no recibe Request, no retorna Response).
- Lanza excepciones de dominio tipadas (`InsufficientCapacityException`), nunca `\Exception` genérica.

**Services** (`app/Services/...`)
- Solo si hay lógica compartida por 2+ actions/jobs.
- Stateless. Sin propiedades mutables.
- Ej: `StripePaymentGateway`, `TenantResolver`, `BookingPriceCalculator`.

**Models** (`app/Models/...`)
- Sin lógica de negocio. Solo: relaciones, scopes, accessors/mutators, casts, eventos.
- Tenant-scoped models implementan `UsesLandlordConnection` o trait del paquete + global scope automático.
- `$fillable` siempre declarado. `$guarded = []` prohibido.
- Enums para columnas de estado (no strings sueltos).

**Resources** (`app/Http/Resources/...`)
- TODO response JSON pasa por un Resource.
- `whenLoaded()` para relaciones — nunca cargar relaciones innecesarias.
- No filtrar por rol acá — eso lo hace la Policy en el controller.

**Policies** (`app/Policies/...`)
- Una por modelo que requiera autorización.
- Métodos: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, + acciones custom.
- Validan: pertenencia al tenant, rol, ownership del recurso.

**Jobs** (`app/Jobs/...`)
- Para trabajo asíncrono > 200ms o que pueda fallar y reintentarse.
- Idempotentes siempre (clave: `uniqueId()` cuando hace falta).
- `tries`, `backoff`, `timeout` explícitos.

**Notifications** (`app/Notifications/...`)
- Multi-canal (`mail`, `database`) cuando aplique.
- Usar via `notify()` desde el modelo Usuario, no `Notification::send()` salvo broadcast.

**Eventos / Listeners**
- Solo cuando hay side-effects desacoplados (ej: aprobación de review → recalcular rating del tour). No para todo.

### 3.3 Patrones aplicables

| Patrón | Cuándo | Cuándo NO |
|---|---|---|
| Action Pattern | Siempre que haya un caso de uso | Operaciones triviales (índice CRUD) |
| Form Request | Siempre que llegue input | GET sin filtros |
| Policy | Recurso con autorización | Endpoint público sin owner |
| Observer | Side-effect determinístico de un modelo | Lógica que depende del request |
| Repository | NUNCA por defecto | Si surge necesidad real de swap de fuente, abrir RFC |
| Service Layer genérico | NUNCA por defecto | Solo cuando 2+ actions comparten lógica |

### 3.4 DTOs

- `readonly class` con constructor con property promotion cuando una Action recibe >3 parámetros o un grupo cohesivo.
- Viven en `app/Data/` (no usar Spatie data salvo aprobación).
- Sin lógica — son contenedores tipados.

### 3.5 Enums

- Para todo estado: `BookingStatus`, `PaymentStatus`, `TourStatus`, `UserRole`.
- TitleCase en los cases (`PendingPayment`, no `pending_payment`).
- Implementan `value` string lowercase con `_`.
- Tienen `label()` para UI.

---

## 4. Frontend — Arquitectura

### 4.1 Estructura

```
resources/js/
├── pages/              # Inertia pages (1 archivo = 1 ruta)
├── layouts/            # AppLayout, AuthLayout, AdminLayout
├── components/
│   ├── atoms/          # BaseInput, BaseButton, Badge, Avatar...
│   ├── molecules/      # FormField, TourCard, NotificationCard...
│   └── organisms/      # TourGrid, BookingForm, ReviewSection...
├── composables/        # useToast, useTenant, useFavorites...
├── types/              # types globales (TS)
└── lib/                # utils puros (formatPrice, cn, etc.)
```

### 4.2 Reglas

- **TypeScript estricto.** `strict: true` en `tsconfig.json`. Sin `any`. Sin `as unknown as`.
- **Composition API + `<script setup>`** siempre. Sin Options API.
- **Props tipadas** con interface, `defineProps<Props>()`.
- **`useForm` de Inertia** para todos los forms — nunca fetch/axios para mutar.
- **`useHttp` de Inertia v3** para requests one-off que no son visita.
- **Wayfinder** para URLs: `import { storeBooking } from '@/actions/BookingController'`. Hardcodear URLs está prohibido.
- **Inertia router vs API JSON — separación obligatoria**
  - `router.post/put/patch/delete` de Inertia se reserva EXCLUSIVAMENTE para web routes que devuelven respuesta Inertia (redirect 302 con headers `X-Inertia`).
  - Los endpoints `/api/v1/*` devuelven Eloquent Resources (JSON) — Inertia los descarta silenciosamente.
  - Para llamar a `/api/v1/*` desde el frontend usar el composable `useApi()` (en `resources/js/composables/useApi.ts`) que envuelve `fetch` con CSRF + cookies + manejo de errores.
  - **Detección:** si un click no produce request en Network tab pero el handler corre, casi seguro estás usando `router.*` contra `/api/v1/*`.
- **Tailwind utility-first.** Sin clases CSS custom salvo en `app.css` para resets/tokens.
- **Sin lógica de negocio en componentes.** Solo presentación + handlers que llaman composables/actions.
- **Single root element** en cada `.vue` (regla Vue).
- **`Inertia::optional()`** y deferred props con skeleton de carga obligatorio.

### 4.3 Naming

- Pages: `PascalCase.vue` matching ruta (`TourDetail.vue` en `/tours/{slug}`).
- Componentes: `PascalCase.vue`, prefijo `Base` para atoms (`BaseInput.vue`).
- Composables: `useXxx.ts`.
- Types: `xxx.types.ts`.

---

## 5. Base de datos

- **MySQL/PostgreSQL** (definido en `.env`). Sin features dialect-specific salvo aprobación.
- **Migrations idempotentes.** Down siempre implementado.
- **Naming:** tablas plural snake_case (`tour_dates`), columnas snake_case, FKs `<tabla_singular>_id`.
- **Soft deletes** solo donde la spec lo requiera (`bookings`, `reviews`).
- **UUIDs** para identificadores públicos (`booking_number`, `tour.slug`); BIGINT autoinc para PK interna.
- **Timestamps** `created_at` y `updated_at` siempre. `deleted_at` cuando soft delete.
- **tenant_id** en TODA tabla tenant-scoped, indexada, NOT NULL, FK a `tenants`.
- **Índices** explícitos en columnas de búsqueda/orden/JOIN.
- **Constraints** en BD (NOT NULL, UNIQUE, CHECK) además de validación de app.
- **JSON columns** solo para datos opacos sin queries por contenido.

Ver [`multi-tenancy.md`](./multi-tenancy.md) para reglas de aislamiento.

---

## 6. Tests

Ver [`testing-policy.md`](./testing-policy.md). Resumen:

- PHPUnit 12 (no Pest).
- **Feature tests** por defecto; Unit solo para clases puras complejas.
- Cobertura mínima por endpoint: **1 happy + 1 failure + 1 edge**.
- Factories para todo modelo. Sin `DB::insert` manual en tests.
- Sin mocks de BD. Sí mocks de servicios externos (Stripe, mail).
- `RefreshDatabase` en todo feature test.

---

## 7. Formato y linting

- **Pint** antes de cerrar cualquier tarea: `vendor/bin/pint --dirty --format agent`.
- **ESLint** + **Prettier** para frontend: `npm run lint && npm run format`.
- **TypeScript check**: `npm run types:check` debe pasar.
- **PHPStan / Larastan** — pendiente (post-fundaciones).

---

## 8. Git

- Branches: `feature/F0XX-<slug>`, `fix/<slug>`, `chore/<slug>`.
- Commits convencionales: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`.
- PRs pequeños y enfocados (un feature = un PR; si el feature es grande, sub-PRs por capa).
- Sin force-push a `main` ni `develop`. Sin `--no-verify`.
- Migraciones con timestamps únicos para evitar conflictos entre features paralelos.

---

## 9. Errores comunes a evitar

- ❌ Validar en el controller en vez del Form Request
- ❌ Lógica de negocio en el Resource
- ❌ Cargar relación con `with()` pero no usarla en el response
- ❌ Loop con query adentro (N+1)
- ❌ Hardcodear URLs en Vue
- ❌ Variables nombradas `data`, `result`, `temp`, `obj`
- ❌ `try/catch` que solo re-lanza
- ❌ `if/else` redundantes (`if (x) return true; else return false;` → `return x`)
- ❌ Comentarios que repiten el código
- ❌ Métodos `getXxx()` que solo hacen `return $this->xxx;` (usar accessor o property pública)

---

## 10. Cómo se cambia esta constitución

1. PR con título `docs(constitution): <cambio>`.
2. Justificación en el body: por qué la regla actual no sirve.
3. Migración del código existente afectado en el mismo PR o ticket de seguimiento.
4. Merge requiere review humano.

---

## Changelog

- `2026-05-19` — Agregada regla "Inertia router vs API JSON — separación obligatoria" en §4.2 (Frontend Reglas). Razón: regla descubierta tras review Playwright 2026-05-19 — todos los flujos mutantes UI estaban rotos por usar `router.post` contra endpoints JSON. Ver `docs/review-2026-05-19/findings.md` (P0-2 sistémico).
