# Architectural Governance — Design Spec

**Date:** 2026-04-01
**Status:** Implemented
**Scope:** Multi-tenant ERP backend (`c:\laragon\www\erp-backend`)

---

## Problem

At 1,023 models, 362 controllers, and 3,522 routes the codebase had three latent failure modes:

1. **Silent cross-tenant data leak** — `BelongsToOrganization` auto-set `organization_id` only when a user was authenticated; unauthenticated contexts (queue workers, seeders) silently created records with `NULL`.
2. **Double-posting of financial writes** — Invoice sending, payroll generation, and payment run posting had no operation-level idempotency; queue retries or double-clicks could post journals twice.
3. **Illegal state transitions** — Three high-value models (`PayrollPeriod`, `PaymentRun`, `WorkOrder`) had ad-hoc `canBe*()` helpers but no enforced transition map; service code bypassed them with raw `update(['status' => ...])`.

Additionally, the codebase had two conflicting `InvalidStateTransitionException` classes — the `HasStateMachine` trait used the plain one (extends `InvalidArgumentException`), which did not render as a structured JSON API response.

---

## Decisions

### D1 — Tenant enforcement approach
**Chosen: Static bypass flag on the trait** (`withoutTenantCheck(callable, reason)`)

Rejected:
- Environment-based bypass — breaks the ability to test the guard in the test suite
- `runningInConsole()` check — queue workers run in console context in production, creating a silent bypass where it matters most

The bypass logs a warning to the Laravel log in production/staging so all bypasses are auditable.

### D2 — Idempotency mechanism
**Chosen: DB-unique-constraint INSERT (`financial_idempotency_keys` table)**

Rejected:
- Redis-based — Redis is optional in this stack; lost keys on flush/restart; not auditable
- Model status guard only — leaves a real race window between the status check and the state update

Separate from the existing `App\Services\Security\IdempotencyService` (HTTP-layer, stores `JsonResponse` objects per user+request key).

### D3 — WorkOrder status simplification
**Chosen: SAP PP terminology — `draft → released → in_progress → completed → closed`**

- `pending` and `scheduled` merged into `released` (data migration)
- `closed` added as new terminal state after `completed`
- `cancelled` retained from both `draft` and `released`

### D4 — Exception reconciliation
**Chosen: Delete `App\Exceptions\InvalidStateTransitionException`, update `HasStateMachine` to use `App\Exceptions\ERP\InvalidStateTransitionException`**

The ERP variant extends `ErpException`, renders HTTP 422 JSON, and has a static `::make()` factory with structured context. The plain variant extended `InvalidArgumentException` and produced unformatted 500 responses.

---

## Architecture

### Phase 1 — Governance document
`docs/architecture-rules.md` — 7 rules, each marked `[ENFORCED]` or `[ASPIRATIONAL]`.

### Phase 2 — Tenant hard enforcement

```
BelongsToOrganization (trait)
├── creating hook
│   ├── auto-set org_id from auth (existing)
│   └── throw MissingTenantException if still empty + bypass=false (new)
└── withoutTenantCheck(callable, reason) static method (new)

MissingTenantException (new)
└── extends ErpException — HTTP 500, code MISSING_TENANT_CONTEXT
```

### Phase 3 — Financial idempotency

```
financial_idempotency_keys (new table)
├── unique(key, organization_id, operation)
├── status: processing | completed | failed
├── response_payload: json (cached result)
└── expires_at: timestamp (TTL, 24h default)

FinancialIdempotencyService (new)
├── execute(key, operation, orgId, callback, ttl)
│   ├── INSERT → if succeeds, run callback, cache result
│   ├── Duplicate INSERT → handleDuplicate()
│   │   ├── completed + valid TTL → return cached result
│   │   ├── processing + not stale → throw IdempotencyConflictException (409)
│   │   ├── processing + stale (>10min) → delete + retry
│   │   └── failed | expired → delete + retry
│   └── callback throws → delete row, re-throw
└── cleanup() → delete expired rows (daily scheduler)

ChecksIdempotency (trait, for service classes)
└── withFinancialIdempotency(key, operation, orgId, callback, ttl)
    └── delegates to FinancialIdempotencyService::execute()

Applied to:
├── InvoiceService::send() → key: "invoice:{id}:send"
├── PayrollService::generatePayslips() → key: "payroll_period:{id}:generate"
└── PaymentRunService::post() → key: "payment_run:{id}:post"
```

### Phase 4 — State machine consolidation

```
HasStateMachine (updated)
└── now uses App\Exceptions\ERP\InvalidStateTransitionException::make()

App\Exceptions\InvalidStateTransitionException (deleted)

PayrollPeriod (updated)
├── uses HasStateMachine
└── transitions: open → processing → processed → closed

PaymentRun (updated)
├── uses HasStateMachine
└── transitions: draft → proposed → approved → posted
                         ↘ cancelled (from draft/proposed/approved)

WorkOrder (updated)
├── uses HasStateMachine
├── STATUS_PENDING + STATUS_SCHEDULED → STATUS_RELEASED
├── STATUS_CLOSED added
├── transitions: draft → released → in_progress → completed → closed
│                     ↘ cancelled (from draft/released)
└── start()/complete()/close()/cancel() use transitionTo()

PayrollService::generatePayslips() → transitionTo(PROCESSING), transitionTo(PROCESSED)
PaymentRunService: propose → transitionTo(PROPOSED), approve → transitionTo(APPROVED),
                  post → transitionTo(POSTED), cancel → transitionTo(CANCELLED)
```

---

## Files Changed

| File | Change |
|------|--------|
| `docs/architecture-rules.md` | Created |
| `app/Exceptions/ERP/MissingTenantException.php` | Created |
| `app/Exceptions/ERP/IdempotencyConflictException.php` | Created |
| `app/Exceptions/InvalidStateTransitionException.php` | Deleted |
| `app/Models/Concerns/BelongsToOrganization.php` | Hard tenant guard + bypass |
| `app/Models/Concerns/HasStateMachine.php` | Use ERP exception |
| `app/Models/Concerns/ChecksIdempotency.php` | Created |
| `app/Services/Core/FinancialIdempotencyService.php` | Created |
| `database/migrations/2026_04_01_100001_create_financial_idempotency_keys_table.php` | Created |
| `database/migrations/2026_04_01_100002_rename_work_order_statuses_to_sap_pp.php` | Created |
| `app/Models/HR/PayrollPeriod.php` | + HasStateMachine |
| `app/Models/Accounting/PaymentRun.php` | + HasStateMachine |
| `app/Models/Manufacturing/WorkOrder.php` | + HasStateMachine, SAP PP statuses |
| `app/Services/Sales/InvoiceService.php` | Idempotency on send() |
| `app/Services/HR/PayrollService.php` | Idempotency + transitionTo on generatePayslips() |
| `app/Services/Accounting/PaymentRunService.php` | Idempotency + transitionTo on post/approve/cancel |
| `routes/console.php` | financial:cleanup-idempotency command + daily schedule |

---

## Tests

| File | Tests | Coverage |
|------|-------|---------|
| `tests/Unit/Models/Concerns/BelongsToOrganizationTest.php` | 4 | Tenant guard + bypass |
| `tests/Unit/Services/Core/FinancialIdempotencyServiceTest.php` | 6 | All execute() branches |
| `tests/Unit/Models/StateMachine/PayrollPeriodStateTest.php` | 8 | Full transition map |
| `tests/Unit/Models/StateMachine/PaymentRunStateTest.php` | 7 | Full transition map |
| `tests/Unit/Models/StateMachine/WorkOrderStateTest.php` | 10 | Full transition map + start/complete/cancel |

**Total: 35 tests, all green.**

---

## Risks Accepted

| Risk | Mitigation |
|------|-----------|
| Tenant guard may surface latent bugs in seeders/factories without explicit org_id | `withoutTenantCheck` bypass available; factories should set org_id explicitly |
| `FinancialIdempotencyService` returns cached JSON-decoded array for object types | Callers receive the same data shape; service layer hydrates from the DB anyway |
| WorkOrder data migration merges `pending` + `scheduled` → `released` irreversibly | `down()` migration restores `released → pending` as best-effort rollback |
