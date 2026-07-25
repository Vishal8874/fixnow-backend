# FixNow API Overview

Base path: `/api`

## Public

- `GET /categories`
- `GET /categories/{category}/services`
- `GET /services`

## Auth

- `POST /auth/register/customer`
- `POST /auth/register/provider`
- `POST /auth/login`
- `GET /auth/google/redirect`
- `GET /auth/google/callback`
- `GET /auth/me`
- `POST /auth/logout`

## Customer

- `GET /customer/addresses`
- `POST /customer/addresses`
- `GET /customer/addresses/{address}`
- `PATCH /customer/addresses/{address}`
- `DELETE /customer/addresses/{address}`
- `PATCH /customer/addresses/{address}/default`
- `GET /customer/bookings`
- `POST /customer/bookings`
- `GET /customer/bookings/{booking}`
- `PATCH /customer/bookings/{booking}/cancel`
- `POST /customer/bookings/{booking}/payment`
- `GET /customer/bookings/{booking}/payment`
- `POST /customer/bookings/{booking}/review`
- `GET /customer/reviews`
- `GET /customer/reviews/{review}`
- `PATCH /customer/reviews/{review}`

## Provider

- `GET /provider/profile`
- `POST /provider/profile`
- `PATCH /provider/profile`
- `GET /provider/availability`
- `POST /provider/availability`
- `PATCH /provider/availability`
- `GET /provider/assignments`
- `GET /provider/assignments/{assignment}`
- `PATCH /provider/assignments/{assignment}/accept`
- `PATCH /provider/assignments/{assignment}/reject`
- `PATCH /provider/assignments/{assignment}/complete`

## Admin

- `GET /admin/categories`
- `POST /admin/categories`
- `GET /admin/categories/{category}`
- `PATCH /admin/categories/{category}`
- `DELETE /admin/categories/{category}`
- `GET /admin/services`
- `POST /admin/services`
- `GET /admin/services/{service}`
- `PATCH /admin/services/{service}`
- `DELETE /admin/services/{service}`
- `GET /admin/providers/pending`
- `GET /admin/providers`
- `GET /admin/providers/{provider}`
- `PATCH /admin/providers/{provider}/approve`
- `PATCH /admin/providers/{provider}/reject`
- `PATCH /admin/payments/{payment}/success`
- `PATCH /admin/payments/{payment}/failed`
- `PATCH /admin/payments/{payment}/cod-paid`
- `POST /admin/bookings/{booking}/assign`
- `GET /admin/reviews`
- `GET /admin/reviews/{review}`

## API Notes

- Collections use the standard `pagination` object.
- Validation failures always return `422` with `message = "Validation failed."`
- Ownership and authorization failures return `403` or `404` depending on exposure rules.
