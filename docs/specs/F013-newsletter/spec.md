# F013 — Newsletter y mailing

## Descripción

Visitantes se suscriben al newsletter del tenant. Admins envían comunicaciones masivas. Gestión de suscripciones y opt-out.

## User stories

- Como visitante, quiero suscribirme al newsletter de la agencia.
- Como suscriptor, quiero darme de baja fácilmente.
- Como admin, quiero enviar newsletters.
- Como admin, quiero ver cuántos suscriptores tengo.

## Acceptance criteria

- **Given** visitante con email válido, **when** se suscribe, **then** se agrega a la lista y recibe bienvenida.
- **Given** email ya suscrito, **when** intenta otra vez, **then** `409`.
- **Given** suscriptor click en "dar de baja", **then** status → `unsubscribed` sin requerir login.
- **Given** admin enviando newsletter, **when** define subject y contenido, **then** se encola y envía a activos.
- **Given** el envío, **then** se reporta cantidad de destinatarios.

## Edge cases

- Email inválido: `422`.
- Token unsubscribe expirado: generar nuevo y enviar.
- 0 suscriptores: advertir antes de enviar.
- Muchos suscriptores: cola con batches.
- Doble opt-in: futuro, ahora single opt-in.

## Dependencias

- F002 (Tenant).

## Endpoints involucrados

```
POST   /api/v1/newsletter/subscribe
POST   /api/v1/newsletter/unsubscribe
GET    /api/v1/admin/newsletter/subscribers
POST   /api/v1/admin/newsletter/send
```

## Componentes UI

- Pages: `NewsletterAdminPage` (admin)
- Organisms: `NewsletterForm` (público), `NewsletterComposer` (admin), `SubscriberList`
- Molecules: `SubscribeBox`, `UnsubscribeConfirmation`, `RecipientCount`
- Atoms: `BaseInput`, `BaseButton`, `BaseTextarea`, `Badge`

## Datos requeridos

Tablas: `newsletter_subscribers`

---

## Changelog

- `2026-05-17` — Creación inicial.
