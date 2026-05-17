# Política de tests

## 1. Stack

- **PHPUnit 12** (no Pest). Convertir cualquier Pest a PHPUnit si aparece.
- **Faker** para datos.
- **RefreshDatabase** en feature tests.
- **Mockery** para mocks de servicios externos.

## 2. Cobertura mínima por entregable

Antes de marcar una tarea como completa:

- **1 happy path** que ejerce el flujo principal con datos válidos.
- **1 failure path** que verifica que el error correcto se lanza/responde (validation, auth, business rule).
- **1 edge case** específico del feature (los listados en `spec.md` → "Edge Cases").
- **1 tenant isolation test** si toca modelo tenant-scoped.

Total mínimo: **3–4 tests por endpoint**.

## 3. Naming

- Clase: `<Algo>Test` en mismo path lógico que el código testado.
- Métodos: `test_<verbo>_<sujeto>_<contexto>`. Ej: `test_creates_booking_when_capacity_available`.
- Sin abreviaciones.

## 4. Estructura AAA

```php
public function test_creates_booking_when_capacity_available(): void
{
    // Arrange
    $tenant = Tenant::factory()->create();
    $tenant->makeCurrent();
    $tour = Tour::factory()->withFutureDate(capacity: 10)->create();
    $user = User::factory()->customerOf($tenant)->create();

    // Act
    $response = $this->actingAs($user)->postJson(route('bookings.store'), [
        'tour_date_id' => $tour->dates->first()->id,
        'travelers' => 3,
    ]);

    // Assert
    $response->assertCreated();
    $this->assertDatabaseHas('bookings', [
        'tour_date_id' => $tour->dates->first()->id,
        'travelers_count' => 3,
        'status' => BookingStatus::PendingPayment->value,
    ]);
}
```

## 5. Reglas

- **Una sola assertion principal por test** (puede haber assertions secundarias del mismo concepto).
- **No mockear lo que estás testeando.** Si testas `CreateBookingAction`, no mockees `CreateBookingAction`.
- **Sí mockear servicios externos**: Stripe, mail driver, HTTP client a terceros.
- **No mockear la BD.** Usar SQLite in-memory o la conexión real con `RefreshDatabase`.
- **Factories sobre fixtures.** Si un test necesita data específica, usar `.state()` o `.set()`.
- **No reutilizar setup entre tests** salvo lo trivial. Cada test es self-contained.

## 6. Tipos de test

| Tipo | Carpeta | Cuándo |
|---|---|---|
| Feature | `tests/Feature/...` | Default. Toca HTTP + DB |
| Unit | `tests/Unit/...` | Clases puras: calculadoras, parsers, validadores |
| Browser | (no por ahora) | Solo si surge necesidad real |

## 7. Comandos

```bash
# Test puntual (preferido durante dev)
php artisan test --compact --filter=test_creates_booking_when_capacity_available

# Test de un archivo
php artisan test --compact tests/Feature/BookingTest.php

# Toda la suite (al cerrar feature)
php artisan test --compact
```

## 8. Frontend tests

- Pendiente decisión: Vitest + Vue Test Utils para componentes.
- Por ahora: tests de integración via Feature tests que ejercen rutas Inertia y validan props.

## 9. Casos especiales

### Tests de tenant isolation

Obligatorio por cada modelo tenant-scoped. Ver patrón en [`multi-tenancy.md`](./multi-tenancy.md) §6.

### Tests de webhook de Stripe

- Generar firma válida con la secret de testing.
- Casos: `payment_intent.succeeded`, `payment_intent.payment_failed`, evento duplicado.

### Tests de jobs

- `Bus::fake()` para verificar que se despacharon.
- Tests directos del `handle()` con factories.

## 10. Qué NO se testea

- Getters/setters triviales.
- Resources (su shape se verifica en feature tests del endpoint).
- Migrations.
- Configs.
