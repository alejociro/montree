# F001 — Registro y autenticación de usuarios

## Descripción

Sistema de autenticación completo que permite a los usuarios registrarse, iniciar sesión y gestionar su acceso en el contexto de un tenant específico. Usa autenticación basada en cookies (Sanctum SPA) para máxima seguridad sin exponer tokens.

## User stories

- Como visitante, quiero registrarme con mi email para poder reservar tours.
- Como usuario registrado, quiero iniciar sesión para acceder a mis reservas y favoritos.
- Como usuario, quiero recuperar mi contraseña si la olvido.
- Como usuario, quiero verificar mi email para confirmar mi identidad.
- Como usuario, quiero cerrar sesión de forma segura.

## Acceptance criteria

### Registro
- **Given** un visitante en `eco-adventures.montree.app`, **when** completa el formulario con datos válidos, **then** se crea su cuenta con rol `customer` asociada a ese tenant.
- **Given** un email ya registrado, **when** intenta registrarse, **then** recibe `422` sin revelar si el email existe (mensaje genérico).
- **Given** un registro exitoso, **then** se envía email de verificación y se crea sesión automáticamente.

### Login
- **Given** credenciales válidas, **when** envía login, **then** recibe cookie de sesión y datos del usuario con su rol en el tenant actual.
- **Given** cuenta suspendida, **when** intenta login, **then** recibe `403` con mensaje apropiado.
- **Given** 5 intentos fallidos en 1 minuto, **then** se bloquea temporalmente (`429`).

### Recuperación
- **Given** email válido en forgot-password, **then** siempre responde `200` (no revela si existe).
- **Given** token válido en reset-password, **when** envía nueva contraseña, **then** se actualiza y puede hacer login.

## Edge cases

- Usuario existe globalmente pero no tiene relación con este tenant: se crea la relación `tenant_user` automáticamente en login.
- Token de reset expirado (60 min): error claro pidiendo solicitar otro.
- Múltiples pestañas/dispositivos: sesión independiente por dispositivo.
- Email con mayúsculas: normalizar a lowercase antes de buscar.

## Dependencias

- F002 (Tenant resolution) — el subdominio determina a qué tenant se asocia el usuario.

## Endpoints involucrados

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
GET    /api/v1/auth/user
POST   /api/v1/auth/email/verify/{id}/{hash}
```

## Componentes UI

- Pages: `LoginPage`, `RegisterPage`, `ForgotPasswordPage`, `ResetPasswordPage`, `VerifyEmailPage`
- Organisms: `AuthForm`, `SocialAuthButtons` (futuro)
- Molecules: `PasswordStrengthIndicator`, `FormFieldGroup`
- Atoms: `BaseInput`, `BaseButton`, `FormError`, `LoadingSpinner`

## Datos requeridos

Tablas: `users`, `tenant_user`, `password_reset_tokens` (default Laravel)

---

## Out of scope

- OAuth social (Google, GitHub) — futuro.
- 2FA / passkeys (Fortify lo soporta, se activará en feature aparte).
- Magic link login.

## Decisiones abiertas

- [ ] ¿Verificación de email obligatoria para reservar, o opcional?
- [ ] ¿Welcome email separado del verification email?

---

## Changelog

- `2026-05-17` — Creación inicial migrada del enunciado de proyecto.
