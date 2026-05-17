# Workflow — Cómo trabajamos una feature

> Flujo end-to-end desde que abrimos un feature hasta que mergeamos.
> Orquestado por slash commands en `.claude/commands/` y ejecutado por
> sub-agents en `.claude/agents/`.

---

## 1. Roles

| Rol | Quién | Responsabilidad |
|---|---|---|
| **Claude principal** | Yo (sesión interactiva) | Lee spec, escribe `contracts.md` y `plan.md`, lanza sub-agents, consolida |
| **`montree-db-architect`** | Sub-agent | Schema completo (una sola vez al inicio) + cambios de schema futuros |
| **`montree-backend-dev`** | Sub-agent | Migrations (no-schema), Actions, Requests, Controllers, Resources, Policies, Tests |
| **`montree-frontend-dev`** | Sub-agent | Pages, organisms, molecules, atoms, types, composables, tests de UI |
| **`montree-reviewer`** | Sub-agent | Valida que código terminado cumple spec + constitución + tests pasan |
| **`montree-spec-updater`** | Sub-agent | Cuando se descubre que la spec estaba mal, la actualiza con changelog |

---

## 2. Fases del proyecto

### Fase 0 — Bases (UNA sola vez, ya en curso)

1. Constitución y docs (este repo) ✅
2. Sub-agents y slash commands ✅
3. `montree-db-architect` monta schema completo de las 15 features + paquetes spatie + seeders base
4. Verificación: `php artisan migrate:fresh --seed && php artisan test --compact`

### Fase 1 — Fundaciones (F001 + F002)

- **No paralelizables.** El frontend depende del shape definitivo de auth.
- Orden: F002 (tenant resolution) → F001 (auth).

### Fase 2 — Admin first (F003 + F011 + F015)

- Paralelizables entre features.
- F003 (CRUD tours) → F011 (dashboard) → F015 (super admin).

### Fase 3 — Funnel público (F004 + F005 + F006 + F007 + F008)

- F004 + F005 paralelizables.
- F006 + F007 secuenciales (pago depende de booking).
- F008 (notificaciones) transversal.

### Fase 4 — Engagement (F009 + F010 + F012 + F013 + F014)

- Casi todos paralelizables.

---

## 3. Flujo por feature

```
                  ┌──────────────────────────────────────────────────┐
                  │  /feature-start F0XX                             │
                  └─────────────────────┬────────────────────────────┘
                                        │
                  ┌─────────────────────▼────────────────────────────┐
                  │ Claude principal:                                │
                  │  1. Lee docs/specs/F0XX/spec.md                  │
                  │  2. Pregunta dudas al usuario si las hay         │
                  │  3. Escribe contracts.md (endpoints + shapes)    │
                  │  4. Escribe plan.md (decisiones técnicas)        │
                  │  5. Genera tasks.md (checklist atómico)          │
                  │  6. Crea branch feature/F0XX-<slug>              │
                  └─────────────────────┬────────────────────────────┘
                                        │
                  ┌─────────────────────▼────────────────────────────┐
                  │ Claude principal lanza en paralelo:              │
                  │   Agent(montree-backend-dev)   ──┐               │
                  │   Agent(montree-frontend-dev)  ──┤  contracts.md │
                  │                                  │  los conecta  │
                  └─────────────────────┬────────────┴───────────────┘
                                        │
                  ┌─────────────────────▼────────────────────────────┐
                  │ Cada sub-agent al terminar:                      │
                  │  • Self-review (3 preguntas)                     │
                  │  • Marca su sección en tasks.md                  │
                  │  • Reporta deltas con la spec                    │
                  │  • Si delta → invoca montree-spec-updater        │
                  └─────────────────────┬────────────────────────────┘
                                        │
                  ┌─────────────────────▼────────────────────────────┐
                  │  /feature-review F0XX                            │
                  │  → Agent(montree-reviewer):                      │
                  │    • Lee spec + contracts + código terminado     │
                  │    • Corre tests + lint + types                  │
                  │    • Verifica cumplimiento de constitución       │
                  │    • Reporta go / no-go + lista de issues        │
                  └─────────────────────┬────────────────────────────┘
                                        │
                            ┌───────────┴────────────┐
                            ▼                        ▼
                       no-go: bucle a            go: PR a develop
                       backend/frontend          → review humano
                       con issues                → merge
```

---

## 4. Comandos disponibles

### `/feature-start F0XX`
Arranca un feature: lee `spec.md`, escribe `contracts.md` + `plan.md` + `tasks.md`, crea branch, deja todo listo para lanzar sub-agents.

### `/feature-review F0XX`
Lanza `montree-reviewer` sobre el código actual del feature. Reporta go/no-go con lista de issues.

### `/feature-status F0XX`
Muestra estado actual: qué tasks están done/pending, qué archivos cambiaron, si tests pasan, si pint pasa.

---

## 5. Self-review de cada sub-agent (3 preguntas)

Cada sub-agent, antes de devolver control, responde por escrito:

1. **¿Está completo lo que pidió la tarea?** Lista lo entregado vs lo pedido.
2. **¿Hay errores conocidos o sospechosos?** Tests que no corrió, edge cases que no cubrió, deuda dejada.
3. **¿Qué se puede mejorar?** Refactor pequeño, abstracción candidata (con regla del 3), naming.

Si el agente termina **sin** responder estas 3 preguntas, no se considera completo y Claude principal lo invoca de nuevo.

---

## 6. Contratos entre sub-agents

Los sub-agents **no se hablan directamente**. Se sincronizan vía archivos:

- `contracts.md` define endpoints + shapes (lo escribe Claude principal antes de lanzar)
- `tasks.md` define checklist y dueño de cada item
- `plan.md` define decisiones técnicas (qué Action, qué service)

Si un sub-agent necesita modificar un contrato, **detiene** su trabajo, propone cambio y espera. No modifica unilateralmente.

---

## 7. Handoffs

### Backend → Frontend
- Backend termina → ejecuta `php artisan wayfinder:generate` → frontend ya tiene las rutas tipadas.
- Backend documenta el shape del response final (debe matchear `contracts.md`; si difiere, actualizar contract antes).

### Frontend → Backend
- Si el frontend descubre que necesita un campo extra o un endpoint nuevo: detiene, propone, modifica `contracts.md`, backend lo implementa.

### Cualquiera → Reviewer
- Reviewer asume que tests pasan y pint pasa. Si no, devuelve no-go directo sin revisar más.

---

## 8. Branching

- Una feature = una branch `feature/F0XX-<slug>` desde `develop` (o `main` si no hay develop).
- Sub-PRs por capa solo si el feature es muy grande (>500 LOC).
- Rebase frecuente contra develop para evitar conflictos de migraciones.
- Squash al mergear.

---

## 9. Migraciones en paralelo

Riesgo principal con agentes paralelos: dos agentes generan migraciones que tocan la misma tabla.

**Mitigación:**
- `montree-db-architect` monta el schema COMPLETO en Fase 0. No se tocan tablas en features iniciales.
- Si un feature necesita una columna nueva: el sub-agent que la necesita la genera con timestamp único, abre PR pequeño aislado de la lógica.
- Migraciones se mergean primero, lógica después.

---

## 10. Cuándo NO usar el flujo

- Bug fix puntual: ir directo sin spec/contracts.
- Refactor sin cambio funcional: ir directo, mencionar en commit message.
- Hotfix de seguridad: ir directo, postmortem después.
