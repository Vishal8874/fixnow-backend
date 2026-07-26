# FixNow API Reference

Base URL: `/api`

Standard success response:

```json
{
  "success": true,
  "message": "",
  "data": {}
}
```

Standard validation response:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {}
}
```

## Authentication

### POST `/auth/register/customer`
- Authentication: none
- Headers: `Content-Type: application/json`
- Request Body: `name`, `email`, `password`, `password_confirmation`, optional `device_name`
- Success Response: `201` with `user` and `token`
- Business Rules: creates a customer user with `active` status
- Example Request:
```json
{ "name": "Priya Verma", "email": "customer@example.com", "password": "Password123!", "password_confirmation": "Password123!" }
```
- Example Response:
```json
{ "success": true, "message": "Customer registered successfully.", "data": { "user": { "id": 1, "role": "customer", "status": "active" }, "token": "..." } }
```

### POST `/auth/register/provider`
- Authentication: none
- Headers: `Content-Type: application/json`
- Request Body: `name`, `email`, `password`, `password_confirmation`, optional `device_name`
- Success Response: `201`
- Business Rules: creates only the provider user account; onboarding happens later

### POST `/auth/login`
- Authentication: none
- Headers: `Content-Type: application/json`
- Request Body: `email`, `password`, optional `device_name`
- Success Response: `200` with `user` and `token`
- Validation Errors: `422`
- Business Rules: email-only login; inactive and suspended users are blocked

### GET `/auth/google/redirect`
- Authentication: none
- Success Response: `200` with `redirect_url`
- Notes: starts customer Google login flow

### GET `/auth/google/callback`
- Authentication: none
- Success Response: `200` with `user` and `token`
- Notes: rate limited and depends on valid Google callback input

### GET `/auth/me`
- Authentication: Sanctum
- Headers: `Authorization: Bearer <token>`
- Success Response: `200`

### POST `/auth/logout`
- Authentication: Sanctum
- Headers: `Authorization: Bearer <token>`
- Success Response: `200`

## Categories

### GET `/categories`
- Authentication: none
- Success Response: paginated active categories
- Business Rules: returns only active public categories

### GET `/categories/{category}/services`
- Authentication: none
- Success Response: paginated active services for an active category
- Notes: inactive categories return `404`

### GET `/admin/categories`
- Authentication: admin Sanctum token
- Success Response: paginated categories with service count

### POST `/admin/categories`
- Authentication: admin Sanctum token
- Request Body: `name`, optional `slug`, `icon`, `description`, `status`
- Success Response: `201`

### GET `/admin/categories/{category}`
- Authentication: admin Sanctum token
- Success Response: `200`

### PATCH `/admin/categories/{category}`
- Authentication: admin Sanctum token
- Request Body: partial category fields
- Success Response: `200`

### DELETE `/admin/categories/{category}`
- Authentication: admin Sanctum token
- Success Response: `200`
- Business Rules: linked services block deletion with `409`

## Services

### GET `/services`
- Authentication: none
- Success Response: paginated public services
- Business Rules: only active services with active categories are public

### GET `/admin/services`
- Authentication: admin Sanctum token
- Success Response: paginated services

### POST `/admin/services`
- Authentication: admin Sanctum token
- Request Body: `category_id`, `name`, optional `slug`, `image`, `description`, `estimated_duration`, `base_price`, optional `status`
- Success Response: `201`
- Business Rules: service name must be unique within a category

### GET `/admin/services/{service}`
- Authentication: admin Sanctum token
- Success Response: `200`

### PATCH `/admin/services/{service}`
- Authentication: admin Sanctum token
- Request Body: partial service fields
- Success Response: `200`

### DELETE `/admin/services/{service}`
- Authentication: admin Sanctum token
- Success Response: `200`

## Customer Addresses

### GET `/customer/addresses`
- Authentication: customer Sanctum token
- Success Response: paginated addresses

### POST `/customer/addresses`
- Authentication: customer Sanctum token
- Request Body: `label`, `contact_person`, `contact_phone`, `address_line_1`, optional `address_line_2`, `landmark`, `city`, `state`, `postal_code`, `latitude`, `longitude`, `is_default`
- Success Response: `201`
- Business Rules: first address becomes default automatically

### GET `/customer/addresses/{address}`
- Authentication: customer Sanctum token
- Success Response: `200`
- Business Rules: ownership enforced

### PATCH `/customer/addresses/{address}`
- Authentication: customer Sanctum token
- Request Body: partial address fields
- Success Response: `200`

### DELETE `/customer/addresses/{address}`
- Authentication: customer Sanctum token
- Success Response: `200`

### PATCH `/customer/addresses/{address}/default`
- Authentication: customer Sanctum token
- Success Response: `200`
- Business Rules: only one default address per customer

## Bookings

### GET `/customer/bookings`
- Authentication: customer Sanctum token
- Success Response: paginated bookings

### POST `/customer/bookings`
- Authentication: customer Sanctum token
- Request Body:
```json
{
  "customer_address_id": 1,
  "booking_date": "2026-07-25",
  "booking_time": "10:30",
  "service_charge": 50,
  "tax": 25,
  "discount": 0,
  "services": [{ "service_id": 1, "quantity": 1 }]
}
```
- Success Response: `201`
- Business Rules: customer must own the address; only active services in active categories may be booked

### GET `/customer/bookings/{booking}`
- Authentication: customer Sanctum token
- Success Response: `200`
- Notes: includes booking summary, address, status history, and payment if present

### PATCH `/customer/bookings/{booking}/cancel`
- Authentication: customer Sanctum token
- Request Body: optional `remarks`
- Success Response: `200`
- Business Rules: cancellation allowed only for `created` and `pending_payment` statuses (before payment).

## Payments

### POST `/customer/bookings/{booking}/payment`
- Authentication: customer Sanctum token
- Request Body: `payment_method`, optional `gateway`, optional `notes`
- Success Response: `201`
- Business Rules: one payment per booking; COD payment creation moves booking to `pending_assignment` and triggers auto-assignment; online payment creation moves booking to `pending_payment` awaiting gateway callback.

### GET `/customer/bookings/{booking}/payment`
- Authentication: customer Sanctum token
- Success Response: `200`

### POST `/gateway/payment/callback`
- Authentication: none (simulated gateway callback)
- Request Body: `payment_id`, optional `gateway_transaction_id`, `status` (`success` or `failed`), optional `notes`
- Success Response: `200`
- Business Rules: online payment gateway simulation callback. On `success`, updates payment status to `paid`, moves booking to `pending_assignment`, and triggers auto-assignment.

### PATCH `/admin/payments/{payment}/failed`
- Authentication: admin Sanctum token
- Request Body: optional `gateway_transaction_id`, optional `notes`
- Success Response: `200`
- Business Rules: admin operational override for marking stuck online payments as failed.

## Provider Profile

### GET `/provider/profile`
- Authentication: provider Sanctum token
- Success Response: `200`

### POST `/provider/profile`
- Authentication: provider Sanctum token
- Headers: multipart form data supported
- Request Body: `about`, `experience_years`, optional `profile_image`
- Success Response: `201`
- Business Rules: only one profile per provider

### PATCH `/provider/profile`
- Authentication: provider Sanctum token
- Request Body: partial profile fields
- Success Response: `200`

## Provider Availability

### GET `/provider/availability`
- Authentication: provider Sanctum token
- Success Response: `200`

### POST `/provider/availability`
- Authentication: provider Sanctum token
- Request Body: `is_available`, optional `available_from`, optional `available_until`, optional `notes`
- Success Response: `201`

### PATCH `/provider/availability`
- Authentication: provider Sanctum token
- Request Body: partial availability fields
- Success Response: `200`

## Provider Approval

### GET `/admin/providers/pending`
- Authentication: admin Sanctum token
- Success Response: paginated pending provider profiles

### GET `/admin/providers`
- Authentication: admin Sanctum token
- Success Response: paginated providers

### GET `/admin/providers/{provider}`
- Authentication: admin Sanctum token
- Success Response: `200`

### PATCH `/admin/providers/{provider}/approve`
- Authentication: admin Sanctum token
- Success Response: `200`
- Business Rules: `409` if already approved

### PATCH `/admin/providers/{provider}/reject`
- Authentication: admin Sanctum token
- Success Response: `200`
- Business Rules: `409` if already rejected

## Provider Assignment

### POST `/admin/bookings/{booking}/assign`
- Authentication: admin Sanctum token
- Request Body: optional `notes`
- Success Response: `201` when assignment created, `200` with empty data when no eligible provider is available
- Business Rules: intended for operational use; normal booking flow auto-assigns providers

### GET `/provider/assignments`
- Authentication: provider Sanctum token
- Success Response: paginated assignments

### GET `/provider/assignments/{assignment}`
- Authentication: provider Sanctum token
- Success Response: `200`

### PATCH `/provider/assignments/{assignment}/accept`
- Authentication: provider Sanctum token
- Request Body: optional `notes`
- Success Response: `200`
- Business Rules: assignment status must be `assigned`. Moves booking to `provider_assigned`.

### PATCH `/provider/assignments/{assignment}/reject`
- Authentication: provider Sanctum token
- Request Body: optional `rejection_reason`, optional `notes`
- Success Response: `200`
- Business Rules: assignment status must be `assigned`. Moves booking to `pending_assignment` and system automatically retries assignment with next eligible provider.

### PATCH `/provider/assignments/{assignment}/on-the-way`
- Authentication: provider Sanctum token
- Request Body: optional `notes`
- Success Response: `200`
- Business Rules: booking status must be `provider_assigned`. Moves booking to `on_the_way`.

### PATCH `/provider/assignments/{assignment}/arrived`
- Authentication: provider Sanctum token
- Request Body: optional `notes`
- Success Response: `200`
- Business Rules: booking status must be `on_the_way`. Moves booking to `arrived`.

### PATCH `/provider/assignments/{assignment}/in-progress`
- Authentication: provider Sanctum token
- Request Body: optional `notes`
- Success Response: `200`
- Business Rules: booking status must be `arrived`. Moves booking to `in_progress`.

### PATCH `/provider/assignments/{assignment}/complete`
- Authentication: provider Sanctum token
- Request Body: optional `notes`
- Success Response: `200`
- Business Rules: booking status must be `in_progress`. Sets assignment status to `completed` and booking status to `completed`. If online pre-paid, auto-closes booking to `closed`.

### PATCH `/provider/assignments/{assignment}/confirm-cod-payment`
- Authentication: provider Sanctum token
- Request Body: optional `notes`
- Success Response: `200`
- Business Rules: booking status must be `completed` and payment method `cash_on_delivery`. Marks payment `paid` and auto-closes booking to `closed`.

## Reviews

### POST `/customer/bookings/{booking}/review`
- Authentication: customer Sanctum token
- Request Body: `rating`, optional `comment`
- Success Response: `201`
- Business Rules: booking must belong to customer, be in status `closed`, payment status `paid`, have accepted/completed assignment history, and not already be reviewed.

### GET `/customer/reviews`
- Authentication: customer Sanctum token
- Success Response: paginated customer reviews
- Business Rules: customer views their own reviews

### GET `/customer/reviews/{review}`
- Authentication: customer Sanctum token
- Success Response: `200`
- Business Rules: ownership enforced

### PATCH `/customer/reviews/{review}`
- Authentication: customer Sanctum token
- Request Body: optional `rating`, optional `comment`
- Success Response: `200`
- Business Rules: editable only within 24 hours

### GET `/admin/reviews`
- Authentication: admin Sanctum token
- Success Response: paginated reviews

### GET `/admin/reviews/{review}`
- Authentication: admin Sanctum token
- Success Response: `200`

## Notes

- All authenticated endpoints require `Authorization: Bearer <token>`.
- Validation failures return `422`.
- Ownership-protected resources return `404` when accessed by non-owners.
- Role-protected routes return `403` when the authenticated role is not allowed.
