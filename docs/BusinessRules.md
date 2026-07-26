# FixNow Business Rules

## Users

- Roles: `customer`, `provider`, `admin`
- User status: `active`, `inactive`, `suspended`

## Provider Onboarding

- Provider registration creates only the user account.
- Provider profile is created later during onboarding.
- Providers can log in before approval.
- Providers are assignable only after profile verification is `approved`.

## Categories And Services

- Admin manages categories and services.
- Public APIs return only active categories and active services.
- A service name must be unique within its category.

## Customer Addresses

- Customers manage only their own addresses.
- A customer always has at most one default address.

## Booking Lifecycle

### Statuses

`CREATED` → `PENDING_PAYMENT` → `PENDING_ASSIGNMENT` → `PROVIDER_ASSIGNED` → `ON_THE_WAY` → `ARRIVED` → `IN_PROGRESS` → `COMPLETED` → `CLOSED`

`CANCELLED` may occur before payment only.

### Flow

1. Customer creates booking → `CREATED`.
2. Customer selects payment method:
   - **COD**: booking moves to `PENDING_ASSIGNMENT`, system auto-assigns provider.
   - **Online**: booking moves to `PENDING_PAYMENT`, awaiting gateway callback.
3. Gateway callback confirms online payment → booking moves to `PENDING_ASSIGNMENT`, system auto-assigns provider.
4. Provider accepts assignment → booking moves to `PROVIDER_ASSIGNED`.
5. Provider marks `ON_THE_WAY` → `ARRIVED` → `IN_PROGRESS` → `COMPLETED`.
6. Booking auto-closes when status = `COMPLETED` AND payment = `PAID`.
   - Online: already paid, auto-closes immediately after completion.
   - COD: provider confirms cash collection, then auto-closes.
7. Customer submits review when booking is `CLOSED`.
8. System recalculates provider rating.

### Rules

- After payment, customer cannot cancel or modify booking.
- Cancellation is only allowed for `CREATED` and `PENDING_PAYMENT` statuses.
- Customer never selects a provider.
- Admin does not participate in the normal booking lifecycle.

## Payment

- Online and COD are supported.
- Online payment: gateway callback confirms payment → triggers auto-assignment.
- COD payment: stays `PENDING` until provider confirms cash collection after service completion.
- COD can be confirmed only after booking status is `COMPLETED`.

### Payment Statuses

`PENDING`, `PAID`, `FAILED`, `REFUNDED`

## Provider Assignment

### Assignment Statuses

`ASSIGNED`, `ACCEPTED`, `REJECTED`, `EXPIRED`, `COMPLETED`

### Eligibility Rules

- User role `provider`
- User status `active`
- Provider verification `approved`
- Provider service match (all requested services)
- Provider service-area match (customer postal code)
- Explicit availability record
- No active conflicting assignment at the same booking date and time
- Provider not previously assigned (rejected/expired) for the same booking

### Rejection / Timeout

- If a provider rejects, the system automatically searches for another eligible provider.
- If a provider does not respond before timeout, assignment becomes `EXPIRED` and system re-assigns.
- Previously rejected/expired providers are never re-assigned for the same booking.

### Provider Lifecycle Ownership

After accepting, only the assigned provider may:
- Mark `ON_THE_WAY`
- Mark `ARRIVED`
- Mark `IN_PROGRESS`
- Mark `COMPLETED`
- Confirm COD cash collection

## Reviews

- Reviews belong to bookings.
- Reviews require:
  - booking ownership
  - booking status `CLOSED`
  - payment status `PAID`
  - accepted or completed provider assignment history
  - no existing review for that booking
- Review edits are allowed for 24 hours only.
- Provider rating values are recalculated from the database after each review write.
