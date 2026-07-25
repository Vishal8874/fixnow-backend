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

## Booking

- Customers book services, not providers.
- Bookings capture pricing snapshot data in booking items.
- Booking history must record status transitions.

## Payment

- Online and COD are supported.
- Online payment success moves the booking to `pending_assignment`.
- COD payment creation moves the booking to `pending_assignment`.
- COD can be marked paid only after booking completion.

## Provider Assignment

- Customer never selects a provider.
- The system automatically assigns the first eligible provider.
- Eligibility requires:
- user role `provider`
- user status `active`
- provider verification `approved`
- provider service match
- provider service-area match
- explicit availability record
- no active conflicting assignment at the same booking date and time
- If a provider rejects, the system automatically searches for another eligible provider.

## Reviews

- Reviews belong to bookings.
- Reviews require:
- booking ownership
- completed booking
- paid payment
- accepted or completed provider assignment history
- no existing review for that booking
- Review edits are allowed for 24 hours only.
- Provider rating values are recalculated from the database after each review write.
