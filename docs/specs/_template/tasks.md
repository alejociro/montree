# F0XX — Tasks

> Checklist atómico. Cada item se asigna a un rol y se marca al terminar.
> Generado a partir de `plan.md`. Modificaciones se reflejan en ambos.

---

## Backend (`montree-backend-dev`)

- [ ] Crear `App\Actions\...\<Action>` con tests unitarios
- [ ] Crear `App\Http\Requests\...\<Request>` con rules + authorize
- [ ] Crear `App\Http\Controllers\Api\V1\...\<Controller>`
- [ ] Crear `App\Http\Resources\<Resource>`
- [ ] Crear `App\Policies\<Policy>` y registrar
- [ ] Definir rutas en `routes/api.php`
- [ ] Tests feature: happy + failure + edge
- [ ] Test tenant isolation (si modelo tenant-scoped)
- [ ] `php artisan wayfinder:generate`
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact --filter=<feature>`

## Frontend (`montree-frontend-dev`)

- [ ] Crear `resources/js/pages/<Page>.vue`
- [ ] Crear componentes nuevos en `components/{atoms,molecules,organisms}/`
- [ ] Crear composable `useXxx()` (si aplica)
- [ ] Types en `types/xxx.types.ts`
- [ ] Conectar con Wayfinder (sin URLs hardcodeadas)
- [ ] Estados: loading, error, empty
- [ ] Validación frontend espejo de Form Request
- [ ] `npm run types:check`
- [ ] `npm run lint && npm run format`
- [ ] Probar en navegador (golden path + 1 edge)

## DB (`montree-db-architect`, solo si hay cambios de schema)

- [ ] Migration con timestamp único
- [ ] Actualizar factory
- [ ] Actualizar seeder si aplica
- [ ] Documentar en `docs/multi-tenancy.md` si toca aislamiento

## Review (`montree-reviewer`)

- [ ] Tests pasan
- [ ] Pint pasa
- [ ] Types check pasa
- [ ] ESLint pasa
- [ ] Spec cubierta 100%
- [ ] Constitución respetada
- [ ] Sin código muerto/comentarios decorativos
- [ ] N+1 check
- [ ] Reporte final con go/no-go

---

## Bloqueos / Decisiones pendientes

- [ ] ...

## Notas durante implementación

- `YYYY-MM-DD` (`<agent>`): <nota>
