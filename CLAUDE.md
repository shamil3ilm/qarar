# Multi-Tenant ERP System for GCC & India

A comprehensive multi-tenant Enterprise Resource Planning (ERP) backend built with Laravel, designed for businesses operating in GCC countries (Saudi Arabia, UAE, Bahrain, Oman, Qatar, Kuwait) and India.

## Tech Stack

- **Framework:** Laravel 12
- **Language:** PHP 8.2+
- **Authentication:** JWT (via `phpopensourcesaver/jwt-auth`)
- **Database:** MySQL/PostgreSQL (production), SQLite (testing)
- **API:** RESTful, versioned (`/api/v1`)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan db:seed
```

## Running Tests

```bash
php artisan test
```

## Project Structure

The codebase is organized into domain modules:

| Module | Path prefix | Description |
|---|---|---|
| **Sales** | `Sales/` | Contacts, invoices, payments, quotations, credit notes, refunds, promotions |
| **Purchase** | `Purchase/` | Purchase orders, bills, payment made |
| **Inventory** | `Inventory/` | Products, categories, warehouses, stock, transfers, adjustments |
| **HR** | `HR/` | Employees, attendance, leave, payroll |
| **Accounting** | `Accounting/` | Chart of accounts, journal entries, fiscal years, bank reconciliation, loans |
| **CRM** | `CRM/` | Leads, opportunities, activities, pipeline |
| **Manufacturing** | `Manufacturing/` | BOMs, work orders, production logs |
| **Core** | `Core/` | Organizations, settings, notifications, dashboards, webhooks, approvals |

Each module follows the same layered structure:
- `app/Models/{Module}/` -- Eloquent models
- `app/Services/{Module}/` -- Business logic (service layer)
- `app/Http/Controllers/Api/V1/{Module}/` -- API controllers
- `app/Http/Resources/{Module}/` -- API resource transformers
- `app/Http/Requests/{Module}/` -- Form request validation
- `routes/api/v1/{module}.php` -- Route definitions
- `tests/Feature/{Module}/` -- Feature tests

## API Conventions

- **Base URL:** `/api/v1`
- **Auth:** JWT Bearer token in `Authorization` header
- **Response envelope:**
  ```json
  {
    "success": true,
    "message": "Success",
    "data": { ... },
    "meta": { "request_id": "uuid", "timestamp": "ISO8601" }
  }
  ```
- **Error envelope:**
  ```json
  {
    "success": false,
    "error": { "code": "ERROR_CODE", "message": "Human-readable message" },
    "meta": { ... }
  }
  ```
- **Pagination:** Standard Laravel pagination with `meta` (current_page, per_page, total, last_page) and `links` (first, last, prev, next).

## Key Architectural Decisions

- **Multi-tenancy:** Achieved via `organization_id` column with Eloquent global scopes. Every tenant-scoped model automatically filters by the authenticated user's organization.
- **RBAC:** Role-based access control with `check.permission` middleware. Users have roles, roles have permissions. Super-admins bypass all permission checks.
- **Module system:** Routes are grouped by module with `check.module` middleware ensuring the organization has access to the requested module.
- **JWT middleware stack:** `auth:api` -> `validate.jwt` -> `check.organization` for protected routes.
- **Service layer pattern:** Controllers delegate business logic to service classes. Controllers handle HTTP concerns (validation, response formatting); services handle domain logic.
- **UUID:** All models use UUID (`HasUuid` trait) alongside auto-increment IDs. UUIDs are used in API responses; integer IDs are used internally.
- **Soft deletes:** Most models use `SoftDeletes` for safe data retention.
- **Audit trail:** `HasAuditTrail` trait tracks create/update/delete actions.

## Coding Conventions

- **PSR-12** coding standard
- **Strict types** (`declare(strict_types=1)`) in all PHP files
- **Service layer pattern** -- no business logic in controllers
- **Form Request classes** for input validation
- **API Resource classes** for response transformation
- **`ApiResponse` trait** on the base Controller for consistent response formatting
- **Conventional route naming:** `{module}.{resource}.{action}` (e.g., `sales.invoices.store`)
