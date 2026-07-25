# FixNow API

FixNow is a Laravel 12 REST API for a home services platform with customer booking, payment, provider onboarding, automatic provider assignment, and booking reviews.

## Project Overview

- Stack: Laravel 12, PHP 8.3+, MySQL, Sanctum, Socialite
- Architecture: Request -> Controller -> Service -> Model -> Resource -> Standard API Response
- Roles: `customer`, `provider`, `admin`
- User status: `active`, `inactive`, `suspended`
- Provider verification: `pending`, `approved`, `rejected`

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

## Environment Variables

Configure these values in `.env` before running the API:

```env
APP_NAME=FixNow
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

APP_TIMEZONE=Asia/Calcutta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fixnow
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/api/auth/google/callback

FILESYSTEM_DISK=public
```

## Migration And Seeding

```bash
php artisan migrate
php artisan db:seed
```

For a clean local QA dataset:

```bash
php artisan migrate:fresh --seed
```

Seeded sample credentials:

- Admin: `admin@fixnow.test` / `Password123!`
- Customer: `customer@fixnow.test` / `Password123!`
- Provider: `provider1@fixnow.test` / `Password123!`

## Running The API

```bash
php artisan serve
```

## Running Tests

```bash
php artisan test
php vendor/bin/pint
```

## Configuration

- Use MySQL for local and production environments.
- Configure Google OAuth before testing callback routes.
- Keep `APP_DEBUG=false` outside local development.
- Run `php artisan storage:link` when using provider profile image uploads.

## Authentication

- Email login is supported for all platform users.
- Google login is supported for customer accounts.
- Sanctum bearer tokens protect authenticated endpoints.
- Public auth endpoints are rate limited:
- `login`: 5 requests per minute per email or IP
- `register`: 3 requests per minute per email or IP
- `google callback`: 10 requests per minute per IP

## Architecture Overview

- Controllers stay thin and delegate business logic to services.
- Form Requests handle validation.
- API Resources shape every response payload.
- Business rules live in service classes, not models.
- Middleware protects admin, customer, and provider route groups.

Detailed architecture notes live in [docs/Architecture.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/Architecture.md).

## API Response Standard

Success:

```json
{
  "success": true,
  "message": "",
  "data": {}
}
```

Collection:

```json
{
  "success": true,
  "message": "",
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 100,
    "last_page": 10
  }
}
```

Validation error:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {}
}
```

## Business Rules

- Provider registration creates only the user account.
- Provider onboarding happens through `provider_profiles`.
- Providers can log in before profile approval.
- Providers are eligible for assignment only when user status is `active`, profile verification is `approved`, availability exists, service coverage matches, and no conflict exists.
- Customers never select a provider directly.
- Payment moves bookings into `pending_assignment`.
- The system automatically attempts provider assignment after eligible payment transitions.
- If a provider rejects an assignment, the system automatically looks for the next eligible provider.
- Reviews belong to completed, paid, assigned bookings only.

Full rule reference lives in [docs/BusinessRules.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/BusinessRules.md).

## Folder Structure

```text
app/
  Enums/
  Helpers/
  Http/
    Controllers/
      Admin/
      Auth/
      Customer/
      Provider/
    Middleware/
    Requests/
    Resources/
  Models/
  Services/
database/
  factories/
  migrations/
docs/
routes/
tests/
```

## How Assignment Works

1. Customer creates a booking.
2. Payment is created or confirmed.
3. Booking moves to `pending_assignment`.
4. `ProviderAssignmentService::assignAutomatically()` searches for the first eligible provider.
5. If a provider rejects, the booking returns to `pending_assignment` and the system immediately retries with the next eligible provider.
6. If no provider is available, the booking remains pending assignment for operational follow-up.

## How Payment Works

- Online payments start as `pending`.
- Admin success confirmation marks online payments as `paid` and triggers automatic assignment.
- COD payments start as `pending`.
- COD booking creation triggers automatic assignment immediately after the booking moves to `pending_assignment`.
- COD can only be marked `paid` after booking completion.

## How Reviews Work

- Reviews are created from bookings, not directly against providers.
- A booking must belong to the authenticated customer.
- Booking status must be `completed`.
- Payment status must be `paid`.
- Booking must have an accepted or completed provider assignment history.
- A booking can be reviewed only once.
- A review can be edited within 24 hours.
- Provider ratings are recalculated from the database on each review create or update.

## Additional Documentation

- [docs/Architecture.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/Architecture.md)
- [docs/BusinessRules.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/BusinessRules.md)
- [docs/APIOverview.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/APIOverview.md)
- [docs/API.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/API.md)
- [docs/QA_Checklist.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/QA_Checklist.md)
- [docs/auth-api-examples.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/auth-api-examples.md)
- [docs/postman/FixNow.postman_collection.json](/abs/path/C:/xampp/htdocs/fixnow-api/docs/postman/FixNow.postman_collection.json)
- [docs/postman/FixNow.postman_environment.json](/abs/path/C:/xampp/htdocs/fixnow-api/docs/postman/FixNow.postman_environment.json)

## Testing Guide

1. Run `php artisan migrate:fresh --seed`.
2. Import the Postman collection and environment from `docs/postman/`.
3. Log in as admin, customer, and provider to populate token variables.
4. Execute booking, payment, assignment, and review flows in order.
5. Use [docs/QA_Checklist.md](/abs/path/C:/xampp/htdocs/fixnow-api/docs/QA_Checklist.md) for validation coverage.

## Deployment Notes

- Run database migrations before switching traffic.
- Verify Sanctum, session, and CORS settings for the frontend domain.
- Configure production Google OAuth callback URL.
- Ensure storage permissions are correct for uploaded profile images.
- Run `php artisan test` and `php vendor/bin/pint` in CI before deployment.
