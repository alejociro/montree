# F014 — Gestión de guías/operadores

## Descripción

Gestión del equipo de la agencia: invitar guías y operadores, asignarlos a fechas, permitirles ver agenda y datos de viajeros asignados.

## User stories

- Como admin, quiero invitar a un guía u operador.
- Como admin, quiero asignar guías a fechas específicas.
- Como admin, quiero cambiar rol de un miembro.
- Como guía, quiero ver mi agenda de tours asignados.
- Como guía, quiero ver lista de viajeros de mis tours.

## Acceptance criteria

- **Given** admin invitando email existente, **then** se crea relación `tenant_user` con el rol.
- **Given** admin invitando email nuevo, **then** se envía invitación para registro.
- **Given** admin asignando guía a `tour_date`, **when** guarda, **then** `guide_id` actualizado y el guía ve el tour en agenda.
- **Given** guía accediendo a agenda, **then** ve solo fechas asignadas con datos de viajeros.
- **Given** límite de staff del plan alcanzado, **then** error de plan.
- **Given** admin suspendiendo miembro, **when** guarda, **then** ese usuario pierde acceso.

## Edge cases

- Invitar usuario que ya es member de otro tenant: permitido (multi-tenant).
- Invitar usuario que ya es member de ESTE tenant: `409`.
- Remover último admin: no permitido (mínimo 1 admin).
- Guía asignado a fecha que se cancela: notificar y liberar agenda.
- Guía con tours simultáneos: advertir pero no bloquear.

## Dependencias

- F001 (Users), F003 (Tours y tour_dates).

## Endpoints involucrados

```
GET    /api/v1/admin/users
POST   /api/v1/admin/users/invite
PATCH  /api/v1/admin/users/{id}/role
PATCH  /api/v1/admin/users/{id}/status
PUT    /api/v1/admin/tour-dates/{id}              # asignación de guía
GET    /api/v1/guide/schedule
```

## Componentes UI

- Pages: `TeamPage` (admin), `GuideSchedulePage` (guía)
- Organisms: `TeamList`, `InviteForm`, `GuideCalendar`, `TravelerManifest`
- Molecules: `MemberCard`, `RoleSelector`, `ScheduleCard`, `InviteModal`
- Atoms: `Avatar`, `Badge`, `BaseSelect`, `BaseButton`, `StatusDot`

## Datos requeridos

Tablas: `users`, `tenant_user`, `tour_dates`, `booking_travelers`

---

## Changelog

- `2026-05-17` — Creación inicial.
