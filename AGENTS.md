You are a Senior Laravel 12 Backend Engineer.

Your task is to build a production-ready REST API backend for a project named "FixNow".

## Tech Stack

- Laravel 12
- PHP 8.3+
- MySQL
- Laravel Sanctum
- Laravel Socialite (Google Login)
- REST API
- Eloquent ORM
- Form Requests
- API Resources
- Service Layer Architecture
- Native PHP Enums

Do NOT use:
- Repository Pattern
- DTO Pattern
- GraphQL
- Livewire
- Inertia

Follow Laravel best practices.

==================================================
ARCHITECTURE
==================================================

Controllers should contain almost no business logic.

Flow:

Request
→ Controller
→ Service
→ Model
→ Resource
→ Standard API Response

Create the following folders if needed:

app/
    Enums/
    Helpers/
    Services/
    Http/
        Controllers/
            Auth/
            Customer/
            Provider/
            Admin/
        Requests/
        Resources/

==================================================
API RESPONSE STANDARD
==================================================

Every endpoint must return the following structure.

Success

{
    "success": true,
    "message": "",
    "data": {}
}

Collection

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

Validation Error

{
    "success": false,
    "message": "Validation failed.",
    "errors": {}
}

Never return raw Eloquent models.



Responses must be UI-friendly.

==================================================
USER ROLES
==================================================

Use enum.

customer
provider
admin

==================================================
USER STATUS
==================================================

pending
active
rejected
blocked

==================================================
AUTHENTICATION
==================================================

Laravel Sanctum

Google Login using Socialite.

Login using EMAIL ONLY.

Provider registration only creates the user account.

Provider onboarding happens later.

==================================================
PROJECT MODULES
==================================================

1 Authentication

2 Categories

3 Services

4 Provider

5 Customer Address

6 Booking

7 Payment

8 Provider Assignment

9 Reviews

10 Admin

Implement modules one by one.

==================================================
DATABASE TABLES
==================================================

users

categories

services

provider_profiles

provider_documents

provider_services

provider_service_areas

provider_availability

customer_addresses

bookings

booking_items

booking_status_histories

provider_assignments

payments

payment_transactions

reviews

==================================================
BOOKING FLOW
==================================================

Customer

↓

Choose Service

↓

Choose Address

↓

Choose Date & Time

↓

Checkout

↓

Choose Payment

↓

Booking Created

↓

Provider Auto Assigned

↓

Provider Accepts

↓

Provider On The Way

↓

Service Started

↓

Service Completed

↓

Payment Completed (COD only)

↓

Review

==================================================
PAYMENT
==================================================

Support

1 Online Payment

2 Cash On Delivery

Online Payment

Paid immediately.

Cash on Delivery

Payment status starts as Pending.

Provider collects payment after service completion.

==================================================
PROVIDER ASSIGNMENT
==================================================

Customer NEVER selects provider.

System auto assigns provider based on:

- Active
- Verified
- Available
- Provides requested service
- Covers customer service area
- No booking conflict

If provider rejects,
assign another provider.

==================================================
SERVICE STRUCTURE
==================================================

Category

↓

Service

↓

Provider Service

Admin creates Categories and Services.

Providers select which services they offer.

==================================================
CODING STANDARDS
==================================================

- Use Form Requests
- Use API Resources
- Use Enums
- Use Transactions where required
- Use Service classes
- Keep Controllers thin
- Use eager loading
- Avoid N+1 queries
- Validate everything
- Use route model binding
- Follow PSR-12

==================================================
IMPLEMENTATION ORDER
==================================================

Build the project module by module.

For each module generate:

- Artisan commands
- Migration
- Model
- Relationships
- Enum (if needed)
- Form Requests
- Resource
- Service
- Controller
- Routes
- API examples
- Validation
- Business logic

Complete one module before moving to the next.

Start with:

1. Project setup
2. Authentication
3. Categories
4. Services

Do not skip steps.

Whenever a business requirement is unclear, stop and ask instead of making assumptions.