# Specs — Índice de features

Cada carpeta `F0XX-<slug>/` contiene 4 archivos:

- `spec.md` — Qué hace el feature (estable)
- `contracts.md` — Endpoints, requests, responses (contrato backend↔frontend)
- `plan.md` — Decisiones técnicas (cómo se implementa)
- `tasks.md` — Checklist atómico de implementación

Plantilla copiable en [`_template/`](./_template/).

**Nombre de la carpeta y de la rama.** Los features nuevos usan un slug descriptivo, no el prefijo
`F0XX`: `docs/specs/<funcionalidad-en-kebab-case>/` y `<tipo>/<funcionalidad-en-kebab-case>` para la
rama (`feature/`, `bugfix/`, `fix/`, `hotfix/`). El número no dice qué se implementó; el slug sí. Los
`F001`–`F018` se conservan como están.

---

## Orden de implementación

### Fase 0 — Bases (sin spec, ya en curso)
Constitución, docs, sub-agents, slash commands, schema completo.

### Fase 1 — Fundaciones (secuenciales)
1. [F002 — Tenant resolution](./F002-tenant-resolution/spec.md)
2. [F001 — Auth](./F001-auth/spec.md)

### Fase 2 — Admin first (paralelizables entre features)
3. [F003 — CRUD tours](./F003-tour-crud/spec.md)
4. [F011 — Dashboard admin](./F011-admin-dashboard/spec.md)
5. [F015 — Super admin](./F015-super-admin/spec.md)

### Fase 3 — Funnel público
6. [F004 — Catálogo](./F004-catalog/spec.md)
7. [F005 — Detalle del tour](./F005-tour-detail/spec.md)
8. [F006 — Booking](./F006-booking/spec.md)
9. [F007 — Pagos](./F007-payments/spec.md)
10. [F008 — Notificaciones](./F008-notifications/spec.md)

### Fase 4 — Engagement
11. [F009 — Mi cuenta](./F009-account/spec.md)
12. [F010 — Reseñas](./F010-reviews/spec.md)
13. [F012 — Promociones](./F012-promotions/spec.md)
14. [F013 — Newsletter](./F013-newsletter/spec.md)
15. [F014 — Gestión de guías](./F014-team-management/spec.md)

### Fase 5 — Crecimiento / adquisición
16. [F016 — Onboarding self-serve de agencia](./F016-tenant-onboarding/spec.md) — depende de F002 (estado `pending`) y F001 (sesión aislada §10)

### Transversales
- [multilanguage-es-en — Soporte multilenguaje (español / inglés)](./multilanguage-es-en/spec.md) — toca toda la UI, las validaciones y los correos; no bloquea ni depende de ningún feature de dominio

---

## Reglas

- **No iniciar un feature** sin tener `contracts.md`, `plan.md` y `tasks.md` escritos.
- **No marcar un feature como done** sin que `montree-reviewer` lo apruebe.
- Cambios a `spec.md` requieren entrada en `## Changelog` y `montree-spec-updater` lo valida.
- Si descubrís que la spec estaba mal, **actualizá la spec antes** de seguir codeando — no codeás contra una spec equivocada.
