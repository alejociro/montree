# F009 — Mi cuenta (perfil, actividades, favoritos)

## Descripción

Área personal del usuario: perfil, historial de reservas, tours favoritos y configuración.

## User stories

- Como customer, quiero editar mi perfil y datos de contacto.
- Como customer, quiero ver mis reservas pasadas y futuras.
- Como customer, quiero ver mis tours favoritos.
- Como customer, quiero cambiar mi contraseña.
- Como customer, quiero subir foto de perfil.

## Acceptance criteria

- **Given** customer en Mi Cuenta, **then** ve tabs: Perfil, Mis Reservas, Favoritos.
- **Given** customer edita nombre y teléfono, **when** guarda, **then** se actualiza inmediatamente.
- **Given** customer sube avatar válido (jpg/png, <2MB), **then** se procesa y muestra.
- **Given** sección de reservas, **then** muestra agrupadas: próximas, pasadas, canceladas.
- **Given** sección de favoritos, **then** muestra tours con precio y próxima fecha.

## Edge cases

- Avatar corrupto o formato inválido: error claro.
- Cambio de password con actual incorrecta: `403`.
- Perfil sin datos opcionales: placeholders con CTA para completar.
- Favorito archivado: mostrar con badge "Ya no disponible".

## Dependencias

- F001 (Auth), F006 (Bookings).

## Endpoints involucrados

```
PUT    /api/v1/profile
POST   /api/v1/profile/avatar
PUT    /api/v1/profile/password
GET    /api/v1/bookings
GET    /api/v1/favorites
```

## Componentes UI

- Pages: `AccountPage` (sub-rutas: profile, bookings, favorites)
- Organisms: `ProfileForm`, `BookingHistory`, `FavoriteGrid`
- Molecules: `BookingCard`, `AvatarUploader`, `PasswordChangeForm`, `TabNavigation`
- Atoms: `BaseInput`, `BaseButton`, `Avatar`, `Badge`, `EmptyState`

## Datos requeridos

Tablas: `users`, `bookings`, `favorites`, `tours`

---

## Changelog

- `2026-05-17` — Creación inicial.
