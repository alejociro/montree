---
description: Arranca un feature de MONTREE — lee spec.md, escribe contracts.md + plan.md + tasks.md, crea branch, deja listo para lanzar sub-agents
argument-hint: F0XX (ej. F003)
---

Arrancamos el feature **$ARGUMENTS** de MONTREE.

## Pasos a ejecutar

1. **Validar que el feature existe**: leer `docs/specs/$ARGUMENTS-*/spec.md`. Si no existe la carpeta, abortar pidiendo confirmación del slug.

2. **Leer contexto obligatorio**:
   - `docs/constitution.md`
   - `docs/multi-tenancy.md`
   - `docs/api-conventions.md`
   - `docs/testing-policy.md`
   - `docs/workflow.md`
   - La spec.md completa del feature.

3. **Cuestionar la spec** antes de proceder:
   - ¿Hay user stories ambiguas?
   - ¿Hay edge cases que falten?
   - ¿Las dependencias listadas están implementadas?
   - ¿Hay decisiones abiertas en la sección "Decisiones abiertas"?
   - Si encontrás algo: PARÁ y consultá al usuario antes de seguir.

4. **Escribir `contracts.md`**: copiar plantilla de `docs/specs/_template/contracts.md` y completar con shapes exactos de cada endpoint, validaciones, errores, eventos. Usar el formato del template.

5. **Escribir `plan.md`**: copiar plantilla y completar con decisiones técnicas — Actions, Form Requests, Controllers, Resources, Policies, modelos a tocar, pages Vue, composables. Si hace falta nuevo schema, marcar explícitamente que `montree-db-architect` debe correr primero.

6. **Generar `tasks.md`**: copiar plantilla y rellenar checklist específico del feature, derivado del plan.

7. **Crear branch**: `git checkout -b feature/$ARGUMENTS-<slug>` desde la branch base actual. Si ya existe, hacer checkout.

8. **Reporte final al usuario**:
   - Resumen de la spec en 3 líneas.
   - Decisiones técnicas tomadas (3-5 bullets).
   - Decisiones abiertas que necesitan respuesta del usuario.
   - Sugerencia de cómo lanzar los sub-agents:
     - Si requiere schema nuevo → primero `Agent(montree-db-architect)`.
     - Si no → backend y frontend en paralelo con `Agent(montree-backend-dev)` y `Agent(montree-frontend-dev)`.

## Reglas

- NO modificar código de aplicación todavía. Solo docs + branch.
- NO lanzar sub-agents automáticamente — el usuario decide cuándo.
- Si la spec tiene dependencias no resueltas, NO seguir.
