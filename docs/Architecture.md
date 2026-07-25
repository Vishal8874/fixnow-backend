# FixNow Architecture

## Core Pattern

The API follows this backend flow:

`Form Request -> Controller -> Service -> Model -> Resource -> ApiResponse`

## Main Layers

- Controllers: transport layer only, no business rules
- Services: booking, payment, assignment, onboarding, reviews, and admin workflows
- Models: relationships, casts, mass-assignment configuration
- Resources: UI-friendly API payloads
- Middleware: role protection for `admin`, `customer`, `provider`

## Automatic Assignment Flow

1. A booking is created in `created` state.
2. Payment updates move the booking to `pending_assignment`.
3. `ProviderAssignmentService::assignAutomatically()` is called by the payment flow.
4. The service finds the first eligible provider using SQL filters for:
- active provider account
- approved provider profile
- matching service offering
- matching service area
- explicit availability
- no active booking conflict
5. The assignment is created with status `assigned`.
6. If the provider rejects, the service automatically retries with the next eligible provider.

## Review Integration

- Reviews depend on completed bookings.
- Review creation validates booking ownership, status, payment state, and assignment history.
- Provider ratings are recalculated from the `reviews` table after each write.

## Security Model

- Sanctum protects authenticated routes.
- Role middleware protects route groups.
- Ownership checks happen inside services.
- Responses never return raw Eloquent models.

## Transaction Boundaries

- Booking creation
- Payment creation and payment state updates
- Assignment accept, reject, complete
- Provider onboarding creation
- Review create and update
