# FixNow Authentication API Examples

Base prefix: `/api/auth`

## Register Customer

`POST /api/auth/register/customer`

```json
{
  "name": "Asha Verma",
  "email": "asha@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "device_name": "ios-app"
}
```

## Register Provider

`POST /api/auth/register/provider`

```json
{
  "name": "Ravi Kumar",
  "email": "ravi@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "device_name": "android-app"
}
```

Provider registration creates only the user account with `provider` role and `active` status.

## Login

`POST /api/auth/login`

```json
{
  "email": "asha@example.com",
  "password": "Password123!",
  "device_name": "web-browser"
}
```

## Get Google Redirect URL

`GET /api/auth/google/redirect`

Use the returned `redirect_url` in the client to continue Google authentication.

## Google Callback

`GET /api/auth/google/callback?code=...`

This returns a Sanctum token and authenticated user payload.

## Authenticated User

`GET /api/auth/me`

Header:

```text
Authorization: Bearer {token}
```

## Logout

`POST /api/auth/logout`

Header:

```text
Authorization: Bearer {token}
```
