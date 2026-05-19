# F013 — Tasks

## Backend
- [ ] Actions: Subscribe, Unsubscribe, SendCampaign
- [ ] Form Requests: 3
- [ ] Controllers: NewsletterController, Admin/NewsletterController, NewsletterPageController
- [ ] Resources: NewsletterSubscriberResource
- [ ] Policy: NewsletterPolicy
- [ ] Notifications (queue + snapshots): WelcomeNewsletter, NewsletterCampaign
- [ ] Mail templates (welcome, campaign)
- [ ] Service: UnsubscribeTokenSigner
- [ ] Routes públicas + admin + Inertia
- [ ] Tests feature (4) + unit (1)
- [ ] Pint + wayfinder + suite verde

## Frontend
- [ ] Page Admin/Newsletter/Index (tabs Subscribers + Compose)
- [ ] Page Newsletter/Unsubscribe
- [ ] Organisms: SubscriberTable, NewsletterComposer
- [ ] Molecules: SubscribeBox, RecipientCount, UnsubscribeConfirmation
- [ ] AdminSidebar: "Newsletter" item
- [ ] types/newsletter.ts
- [ ] types-check + lint + build

## Review
- [ ] Tests verde
- [ ] Notification snapshots queue-safe
- [ ] Signed token verified
