# F012 — Plan técnico

## Backend
- Actions: `CreatePromotionAction`, `UpdatePromotionAction`, `DeactivatePromotionAction`, `ValidatePromotionAction` (la última recibe code+tour_date_id+subtotal+user, retorna DTO `PromotionValidationResult` con discount calculado).
- Services: `PromotionDiscountCalculator` (percentage vs fixed; aplica max_discount; rule PRO-009 total mínimo $1 si quedaría menos).
- Form Requests: `StorePromotionRequest`, `UpdatePromotionRequest`, `ValidatePromotionRequest`.
- Controllers: `Admin\PromotionController` (apiResource), `PromotionValidationController` (invoke público para F006).
- Resources: `PromotionResource`, `PromotionValidationResource`.
- Policy: `PromotionPolicy` (admin).
- Exception: `PromotionInvalidException` con error_code switchable.
- Routes: `/api/v1/admin/promotions` + `/api/v1/promotions/validate` (auth pero no admin).
- Inertia route `/admin/promotions`.
- `Promotion::scopeUsableNow()`, `Promotion::scopeForTenant()` (ya implícito por trait).

## Frontend
- Page `Admin/Promotion/Index.vue` — tabla + button "Nueva promoción" abre Dialog con form.
- Organisms: `PromotionTable`, `PromotionFormDialog` (create/edit).
- Molecules: `PromotionStatusBadge` (active/expired/exhausted), `PromotionUsageBar`, `TourMultiSelect` (chips), `DateRangePicker` (2 inputs simples date).
- Reusar Dialog, Switch, Select, Input.
- AdminSidebar: agregar "Promociones" con icon `Ticket` después de "Configuración".
- Wayfinder routes.

## Tests
- Feature: `Admin/PromotionControllerTest` (CRUD happy + duplicate code 409 + code locked 422 + tenant isolation).
- Feature: `PromotionValidationControllerTest` (each invalid case → specific error_code).
- Unit: `PromotionDiscountCalculatorTest` (percentage, fixed, max_discount cap, min total $1).
