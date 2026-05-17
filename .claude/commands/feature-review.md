---
description: Lanza el revisor sobre un feature terminado — corre tests, lint, types y audita contra spec + constitución
argument-hint: F0XX (ej. F003)
---

Revisar el feature **$ARGUMENTS**.

## Pasos a ejecutar

1. **Validar que el feature está listo para review**:
   - Existe carpeta `docs/specs/$ARGUMENTS-*/`.
   - `tasks.md` tiene la mayoría de items de backend y frontend marcados.
   - Hay commits en la branch del feature.

2. **Pre-checks rápidos** (antes de lanzar el reviewer, para no hacerle perder tiempo):
   - `vendor/bin/pint --test --format agent` (read-only)
   - `npm run types:check`
   - `npm run lint`
   - `php artisan test --compact --filter=$ARGUMENTS` o filtro adecuado

   Si CUALQUIERA falla, reportar al usuario antes de invocar al reviewer (no tiene sentido auditar código que no compila).

3. **Lanzar reviewer**:
   ```
   Agent(
     subagent_type: "montree-reviewer",
     description: "Review $ARGUMENTS",
     prompt: "Auditá el feature $ARGUMENTS. Lee docs/specs/$ARGUMENTS-*/{spec,contracts,plan,tasks}.md y todo el código modificado en la branch actual vs main. Aplicá el checklist de tu definición. Devolvé reporte go/no-go con issues priorizados."
   )
   ```

4. **Procesar el reporte del reviewer**:
   - Si **GO**: sugerir al usuario abrir PR con `gh pr create`.
   - Si **NO-GO**: listar los bloqueos al usuario y sugerir invocar `Agent(montree-backend-dev)` o `Agent(montree-frontend-dev)` con la lista de fixes específica.

## Reglas

- NO mergear nada automáticamente — el merge es decisión del usuario.
- NO modificar código — el reviewer solo audita.
- Si los pre-checks fallan, NO invocar al reviewer aún.
