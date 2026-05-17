---
description: Muestra estado actual de un feature — qué tasks están done/pending, archivos cambiados, si tests/lint pasan
argument-hint: F0XX (ej. F003)
---

Estado del feature **$ARGUMENTS**.

## Reporte a generar

1. **Branch actual**: `git branch --show-current`. Si no es `feature/$ARGUMENTS-*`, advertir.

2. **Tasks**: leer `docs/specs/$ARGUMENTS-*/tasks.md`. Contar checkboxes:
   - Backend: X / Y completados
   - Frontend: X / Y completados
   - DB: X / Y completados
   - Review: X / Y completados

3. **Archivos cambiados vs main**:
   - `git diff --stat main...HEAD` resumido.
   - Agrupar por tipo: PHP backend, Vue frontend, migrations, tests, docs.

4. **Commits**: `git log --oneline main..HEAD`.

5. **Estado técnico** (correr en paralelo):
   - `php artisan test --compact --filter=$ARGUMENTS 2>&1 | tail -5` → cantidad de tests + resultado
   - `vendor/bin/pint --test --format agent --dirty 2>&1 | tail -3` → pint OK/fail
   - `npm run types:check 2>&1 | tail -3` → tsc OK/fail
   - `npm run lint 2>&1 | tail -3` → eslint OK/fail

6. **Bloqueos pendientes**: extraer de `tasks.md` sección "Bloqueos / Decisiones pendientes".

7. **Próximo paso sugerido**:
   - Si DB pending → `Agent(montree-db-architect)`.
   - Si backend pending → `Agent(montree-backend-dev)`.
   - Si frontend pending → `Agent(montree-frontend-dev)`.
   - Si todo done y reviews vacíos → `/feature-review $ARGUMENTS`.
   - Si reviewer aprobó → `gh pr create`.

## Reglas

- NO ejecutar nada que modifique código o estado git.
- Reporte conciso, listo para leer en 30 segundos.
