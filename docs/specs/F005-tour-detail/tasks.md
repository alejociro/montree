# F005 — Tasks

## Backend
- [ ] Services: TourDetailResolver, RatingDistribution
- [ ] Action: ToggleFavoriteAction
- [ ] Form Request: ToggleFavoriteRequest
- [ ] Controllers (Api/V1): PublicTourController, PublicReviewController, FavoriteController + PublicTourPageController (Inertia)
- [ ] Resources: PublicTourResource, PublicReviewResource
- [ ] Routes: `/api/v1/tours/{slug}`, `/api/v1/tours/{slug}/reviews`, `/api/v1/favorites` + Inertia `/tours/{slug}`
- [ ] Tour::scopeActive(), TourDate::scopeOpenFuture()
- [ ] Tests feature (3) + unit (1)
- [ ] Pint + wayfinder + suite verde

## Frontend
- [ ] Page TourDetail.vue
- [ ] Organisms: ImageGallery, TourInfo, ItineraryTimeline, DateSelector, ReviewSection, MapPlaceholder
- [ ] Molecules: DateCard, IncludesList, RequirementsList, RatingBreakdown, ReviewCard, FavoriteButton
- [ ] types/tour-detail.ts
- [ ] types-check + lint + build

## Review
- [ ] Tests/lint/types verde
- [ ] Cobertura AC
- [ ] N+1 verified (eager loads)
- [ ] Sin URLs hardcoded
