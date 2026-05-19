# F013 — Plan técnico

## Backend
- Actions: `SubscribeToNewsletterAction`, `UnsubscribeFromNewsletterAction`, `SendNewsletterCampaignAction`.
- Form Requests: `SubscribeRequest`, `UnsubscribeRequest`, `SendCampaignRequest`.
- Controllers (Api/V1): `NewsletterController` (subscribe/unsubscribe), `Admin/NewsletterController` (subscribers index, send) + `NewsletterPageController` (Inertia unsubscribe + admin).
- Resources: `NewsletterSubscriberResource`.
- Policy: `NewsletterPolicy` (admin).
- Notifications: `WelcomeNewsletterNotification` (ShouldQueue, tenant snapshot), `NewsletterCampaignNotification` (ShouldQueue, recibe subject+body+tenant snapshot + signed unsubscribe URL).
- Mail templates: `resources/views/emails/newsletter/welcome.blade.php`, `resources/views/emails/newsletter/campaign.blade.php`.
- Service `UnsubscribeTokenSigner`: usa `URL::temporarySignedRoute` o `signed-cookie` con expiry 30 días.
- Routes: subscribe (público, throttle 5/min/IP), unsubscribe (público), admin subscribers + send, Inertia unsubscribe page + admin page.

## Frontend
- Page `Admin/Newsletter/Index.vue` — tabs Subscribers + Compose. Subscribers tab: tabla con search + count. Compose tab: editor simple (textarea HTML + preview + button "Enviar" con confirm dialog).
- Page `Newsletter/Unsubscribe.vue` — pública, recibe token, botón confirmar.
- Organisms: `SubscriberTable`, `NewsletterComposer` (con `Textarea` HTML + preview iframe + RecipientCount).
- Molecules: `SubscribeBox` (para landing pública / footer del catálogo), `RecipientCount`, `UnsubscribeConfirmation`.
- AdminSidebar: "Newsletter" item con icon `Mail`.
- `Welcome.vue` o catálogo: agregar `SubscribeBox` en footer (opcional MVP).
- types/newsletter.ts.

## Tests
- Feature: `NewsletterControllerTest` (subscribe happy + duplicate 409 + invalid email 422 + welcome notification queued), `UnsubscribeTest` (valid token + expired token + already unsubscribed), `Admin/NewsletterControllerTest` (subscribers index + send happy + no recipients 422 + tenant isolation), `NewsletterPageTest` (unsubscribe page render con token).
- Unit: `UnsubscribeTokenSignerTest`.
