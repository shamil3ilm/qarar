# Architecture Rules

> Living document. Each rule is marked **[ENFORCED]** (backed by a trait, middleware, or test) or
> **[ASPIRATIONAL]** (agreed target pattern not yet mechanically enforced). Update the marker when
> enforcement is added.

---

## Rule 1 — All writes go through Action classes `[ASPIRATIONAL]`

### Rule
Business-state-changing operations (create, update, post, approve, cancel) must be expressed as
dedicated `App\Actions\{Module}\{Verb}{Entity}Action` classes, not inlined in controllers or
general-purpose service methods.

### Why
Controllers are HTTP adapters. Services accumulate scope and become dumping grounds. An Action has
one job, one input DTO, one return type — it is trivially testable and trivially auditable.

### Target pattern
```php
// ✅ Controller delegates to an Action
public function store(StoreInvoiceRequest $request): JsonResponse
{
    $invoice = app(CreateInvoiceAction::class)->execute($request->validated());
    return $this->success(InvoiceResource::make($invoice));
}

// ❌ Business logic inside the controller
public function store(StoreInvoiceRequest $request): JsonResponse
{
    $invoice = Invoice::create([...]);
    $journal = JournalEntry::create([...]);
    event(new InvoiceCreated($invoice));
    return $this->success(...);
}
```

### Enforcement
Code review checklist. Introduce PHPStan custom rule when Action pattern reaches ≥50% coverage.

---

## Rule 2 — Cross-module flows use orchestrators `[ASPIRATIONAL]`

### Rule
Any operation that writes to more than one bounded module (e.g. Sales + Accounting + Inventory)
must be wrapped in a single orchestrator class that owns the `DB::transaction` and the event
dispatch sequence. No module service may directly call another module's service.

### Why
Hidden coupling between services leads to silent partial-commit states and makes tracing side
effects nearly impossible. Orchestrators make the dependency graph explicit.

### Bounded modules
`Accounting`, `Sales`, `Purchase`, `Inventory`, `HR`, `Manufacturing`, `Projects`, `Compliance`.

### Target pattern
```php
// ✅ Orchestrator owns the transaction
class PostInvoiceOrchestrator
{
    public function execute(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $this->journalService->postInvoiceJournal($invoice);
            $this->inventoryService->deductStock($invoice);
            $this->creditService->updateExposure($invoice->customer_id);
        });

        // Events outside the transaction
        event(new InvoicePosted($invoice));
    }
}

// ❌ Service calling another module's service directly
class InvoiceService
{
    public function post(Invoice $invoice): void
    {
        $this->inventoryService->deductStock($invoice);   // cross-module call — forbidden
        $this->journalService->postJournal($invoice);
    }
}
```

### Enforcement
Code review. Long-term: architectural fitness function via PHPStan layer rules.

---

## Rule 3 — No external calls inside transactions `[ENFORCED by convention, ASPIRATIONAL for tooling]`

### Rule
HTTP calls, queue dispatches, and event emissions must never appear inside a `DB::transaction`
block. All side effects go **after** the transaction commits.

### Why
External calls inside transactions hold the DB lock for the duration of the call. A slow HTTP
response (e.g. ZATCA API, payment gateway) blocks all subsequent writes on that row. A failed
external call that causes a rollback leaves the external system in an inconsistent state.

### Pattern
```php
DB::transaction(function () use ($invoice): void {
    // ✅ Pure DB writes only
    $this->createJournalEntry($invoice);
    $this->deductInventory($invoice);
    $invoice->update(['status' => Invoice::STATUS_SENT]);
});

// ✅ External calls AFTER commit
$this->zatcaService->submit($invoice);    // HTTP call
event(new InvoicePosted($invoice));       // queue dispatch
```

### Enforcement
`InvoiceService::send()` already follows this pattern. All new code is reviewed against this rule.
PHPStan rule planned to detect `Http::*`, `event()`, and `dispatch()` calls inside transaction
closures.

---

## Rule 4 — Events must be idempotent `[ENFORCED for existing listeners]`

### Rule
Every queued event listener must be safe to execute more than once for the same event payload.
Listeners must check whether their side effect has already been applied before applying it again.

### Why
Laravel queues retry failed jobs. A listener that posts a journal entry will double-post if retried
after a partial failure. Idempotency at the listener level is the last line of defence.

### Pattern
```php
class PostCopaOnInvoicePostedListener
{
    // CONTRACT:
    // - Must be idempotent (check before insert)
    // - Must not throw blocking exceptions (use try/catch + log)
    // - Must not modify original transaction data

    public function handle(InvoicePosted $event): void
    {
        if (CopaLineItem::where('source_invoice_id', $event->invoice->id)->exists()) {
            return; // already applied
        }

        // ... post COPA
    }
}
```

### Enforcement
All listeners in `app/Listeners/` have idempotency guards. New listeners must include the CONTRACT
doc-block and an idempotency check. Reviewed in PR.

---

## Rule 5 — Multi-tenancy enforced at model level `[ENFORCED]`

### Rule
Every tenant-scoped model must use the `BelongsToOrganization` trait. The trait throws
`MissingTenantException` (HTTP 500) if a model is persisted without `organization_id`. The only
permitted bypass is `BelongsToOrganization::withoutTenantCheck(fn () => ..., reason: '...')`,
which logs a warning to the audit log in production.

### Why
A single missing `organization_id` leaks one tenant's data to another. The guard enforces the
invariant at the persistence layer where it cannot be bypassed by accident.

### Enforcement
`BelongsToOrganization::bootBelongsToOrganization()` — `creating` hook throws if `organization_id`
is still null after the auto-set attempt and the bypass flag is not set.

### Allowed bypass
```php
// Seeders, platform migrations, system records only
BelongsToOrganization::withoutTenantCheck(function (): void {
    SystemConfig::create([...]);
}, reason: 'Platform seeder — org set explicitly on each record');
```

---

## Rule 6 — State transitions must be guarded `[ENFORCED]`

### Rule
Any model with a `status` lifecycle must use the `HasStateMachine` trait. Direct
`$model->update(['status' => ...])` calls in service code are forbidden — use
`$model->transitionTo(Model::STATUS_X)` instead. `forceState()` is permitted only in migrations
and admin tooling, and must be logged.

### Why
Without a transition map, any code can move a model to any state. Illegal transitions (e.g.
`draft → posted`, skipping `approved`) corrupt business data silently.

### Enforced models
`Invoice`, `SalesOrder`, `Quotation`, `PurchaseOrder`, `Bill`, `PaymentReceived`, `PaymentMade`,
`LeaveRequest`, `InterCompanyTransfer`, `StockAdjustment`, `StockTransfer`, `Payslip`,
`PayrollPeriod`, `PaymentRun`, `WorkOrder`.

### Enforcement
`HasStateMachine::transitionTo()` throws `App\Exceptions\ERP\InvalidStateTransitionException`
(HTTP 422) for illegal moves. The trait is the single source of truth for transition maps.

---

## Rule 7 — Idempotency required on financial writes `[ENFORCED for critical operations]`

### Rule
Operations that write journal entries, move money, or post payroll must be wrapped with
`FinancialIdempotencyService::execute()`. A duplicate call within the TTL window returns the
cached result without re-executing the operation.

### Why
Queue retries, network timeouts, and double-clicks can all cause the same financial operation to
be submitted twice. Idempotency at the service layer is the only reliable defence against
double-posting.

### Critical operations (currently enforced)
| Operation | Key pattern | TTL |
|-----------|-------------|-----|
| `InvoiceService::send()` | `invoice:{id}:send` | 24 h |
| `PayrollService::generatePayslips()` | `payroll_period:{id}:generate` | 24 h |
| `PaymentRunService::post()` | `payment_run:{id}:post` | 24 h |

### Mechanism
`FinancialIdempotencyService` uses a DB-unique-constraint INSERT as the serialisation point.
First writer wins; concurrent duplicates receive `IdempotencyConflictException` (HTTP 409);
completed results are cached and replayed.

### Usage
```php
// Service class uses the ChecksIdempotency trait
public function send(Invoice $invoice, ?string $idempotencyKey = null): Invoice
{
    return $this->withFinancialIdempotency(
        key: $idempotencyKey ?? "invoice:{$invoice->id}:send",
        operation: 'invoice.send',
        orgId: $invoice->organization_id,
        callback: function () use ($invoice) {
            // ... actual logic
        }
    );
}
```

---

## Violation Response

| Severity | Example | Action |
|----------|---------|--------|
| **Critical** | Cross-tenant data leak | Stop deployment, hotfix immediately |
| **High** | Missing idempotency on financial write | Block PR, fix before merge |
| **Medium** | Direct `update(['status' => ...])` bypass | Fix in same sprint |
| **Low** | Cross-module service call without orchestrator | Refactor ticket, address within 2 sprints |

---

## Change Log

| Date | Rule | Change |
|------|------|--------|
| 2026-04-01 | 5, 6, 7 | Promoted from aspirational to enforced after implementation |
| 2026-04-01 | 1, 2 | Added as aspirational targets |
