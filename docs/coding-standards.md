# ERP Backend — Coding Standards

Practical, codebase-specific reference. These rules reflect actual patterns in use.

---

## 1. File Organization

```
app/
  Http/
    Controllers/Api/V1/{Module}/   — Thin HTTP handlers; delegate to services
    Requests/{Module}/             — Form Request validation classes
    Resources/{Module}/            — API response transformers (JSON:API envelope)
    Middleware/                    — Cross-cutting HTTP concerns (auth, org check, rate limit)
  Models/{Module}/                 — Eloquent models with scopes, constants, traits
  Services/{Module}/               — Business logic orchestration (one class per domain concept)
  Jobs/                            — Queued background jobs (PDF generation, fraud checks, etc.)
  Listeners/{Module}/              — Event listeners wired in EventServiceProvider
  Traits/                          — Reusable behaviors (ApiResponse, StructuredLogger, HasUuid)
  Exceptions/                      — Typed exceptions (ApiException, ErpException, ErrorCodes)
  Channels/                        — Broadcast channel authorisation
  Console/Commands/                — Artisan CLI commands
docs/                              — Architecture and standards documentation
routes/api/v1/{module}.php         — Route definitions, one file per module
tests/Feature/{Module}/            — Feature tests that exercise the full HTTP stack
tests/Unit/{Module}/               — Isolated unit tests for services and helpers
```

---

## 2. Naming Rules

### Actions
- Name: `VerbNounAction` — e.g. `CreateInvoiceAction`, `PostJournalEntryAction`
- Single public method: `execute(array $payload): mixed`
- No HTTP dependencies; receives plain arrays or DTOs

### Queries
- Name: `GetNounContextQuery` — e.g. `GetCustomerInvoicesQuery`, `GetArAgingQuery`
- Single public method: `execute(): mixed` (returns Collection, array, or paginator)
- All raw/complex SELECT logic lives here — never inline in a controller

### Commands (write-side input objects)
- Name: `VerbNounCommand` — e.g. `CreateInvoiceCommand`, `RunPayrollCommand`
- Declared `final readonly`
- Must implement `fromArray(array $data): static` and `toArray(): array`

### DTOs (read-side data transfer objects)
- Name: `NounContextDTO` — e.g. `CreateInvoiceDTO`, `PayslipDTO`
- Declared `final readonly`
- Must implement `fromArray(array $data): static` and `toArray(): array`

### Services
- Name: `NounService` — e.g. `InvoiceService`, `PayrollService`, `JournalService`
- Orchestration only: delegate DB writes to Eloquent models, delegate reads to Query classes
- No `Request`, `Response`, or `redirect()` inside a service

### Controllers
- Name: `NounController` — e.g. `InvoiceController`, `PayrollController`
- One action per method; keep under 20 lines
- Call one service or action method; format result with `ApiResponse` trait
- Return `$this->successResponse(...)`, `$this->paginatedResponse(...)`, or `$this->errorResponse(...)`

### Models
- Singular PascalCase: `Invoice`, `Payslip`, `JournalEntry`
- Constants for all status/type values: `Invoice::STATUS_DRAFT`, not `'draft'`
- Scopes for common filters: `->active()`, `->posted()`, `->forEmployee($id)`

### Routes
- Naming convention: `{module}.{resource}.{action}` — e.g. `sales.invoices.store`, `hr.payslips.approve`

---

## 3. Comment Rules

**Write WHY, not WHAT.** The code already says what; comments explain intent, constraints, and surprises.

### Class docblock format (mandatory for all service classes)
```php
/**
 * One-sentence summary.
 *
 * Responsibilities:
 * - ...
 *
 * Side Effects:
 * - ...
 *
 * Idempotency:
 * - ...
 *
 * CONTRACT:
 * - Any hard rules callers must obey
 */
```

### Method-level comments
- Use a single-line `/** Summary. */` docblock for non-obvious public methods
- Inline comments for edge-case logic, race conditions, and business rules not obvious from code
- Mark temporary workarounds: `// HACK: [reason] — remove when [condition]`

### `// CONTRACT:` blocks
Used inside methods to assert preconditions that are not enforced by type signatures:
```php
// CONTRACT: must be called inside DB::transaction(); see send() for usage.
$this->createJournalEntry($invoice);
```

### Noise comments to avoid
```php
// BAD: increment count
$count++;

// BAD: return the user
return $user;
```

---

## 4. Function Rules

- **Max 40 lines** per method. Extract helpers for anything longer.
- **Guard clauses** over nested ifs:
  ```php
  // GOOD
  if ($invoice->status !== Invoice::STATUS_DRAFT) {
      throw new \InvalidArgumentException('...');
  }
  // ... proceed

  // BAD
  if ($invoice->status === Invoice::STATUS_DRAFT) {
      // ... 30 lines of logic
  }
  ```
- **One responsibility** per method. If a method name contains "and", split it.
- **Named variables** over inline expressions for complex calculations.
- **bcmath** for all monetary arithmetic — never `+`, `-`, `*`, `/` on floats.

---

## 5. Error Handling

- **Throw exceptions** internally; never return `false`, `null`, or `['error' => ...]` as error signals from services.
- **Format errors at the controller layer** using `ApiResponse::errorResponse()`.
- **Exception hierarchy**:
  - `ApiException::fromError(ErrorCodes::X, $context, $message)` — structured errors with error codes
  - `\InvalidArgumentException` — validation failures and state guard violations
  - `\App\Exceptions\ERP\ValidationException` — domain validation (e.g. fully-credited invoice)
  - `\App\Exceptions\ConcurrencyException` — optimistic locking conflicts
- **Never swallow exceptions** silently. Log with `$this->logWarning(...)` when a failure is intentionally non-fatal (e.g. event tracking, fraud checks).
- **Context in logs**: always include entity IDs and relevant state in log calls.

---

## 6. Database Rules

- **All financial operations in `DB::transaction()`**:
  ```php
  // GOOD
  return DB::transaction(function () use ($invoice) {
      $journal = $this->journalService->create(...);
      $this->stockService->recordSale(...);
      $invoice->update(['journal_entry_id' => $journal->id]);
      return $invoice->fresh();
  });
  ```
- **No raw SQL outside Query classes** — use Eloquent query builder everywhere else.
- **Pessimistic locking for concurrent state transitions**: `->lockForUpdate()` on status-gated operations (e.g. send, void, payslip generation) to prevent race conditions.
- **Eager-load all relations** before iterating — no lazy loading in loops:
  ```php
  // GOOD
  $invoice->load('lines.product', 'customer');
  foreach ($invoice->lines as $line) { ... }

  // BAD
  foreach ($invoice->lines as $line) {
      $line->product->name; // N+1
  }
  ```
- **Chunk large datasets**: `->chunkById(50, ...)` for bulk operations like payslip generation.
- **DB aggregates over PHP collections** for count/sum on large sets:
  ```php
  // GOOD: one query
  DB::table('payslips')->where(...)->selectRaw('SUM(net_salary)')->first();

  // BAD: loads all rows
  $period->payslips->sum('net_salary');
  ```

---

## 7. Testing Rules

- **Feature tests cover the full HTTP stack**: request → middleware → controller → service → DB → response.
- **Unit tests for services** that have complex logic independent of HTTP (e.g. tax calculation, bcmath payroll arithmetic).
- **Use factories** for all fixtures — never hand-written arrays except for simple one-offs.
- **One assertion focus per test** — test a single behavior, not a whole workflow.
- **Name tests descriptively**:
  ```php
  // GOOD
  public function test_sending_invoice_creates_journal_entry(): void
  public function test_void_returns_inventory_to_warehouse(): void

  // BAD
  public function test_invoice(): void
  ```
- **Test state transitions explicitly**: verify both the happy path and each guard clause throw.
- **80% coverage minimum**; 100% on financial calculation methods.
- **SQLite in-memory** for tests (configured in `phpunit.xml`); never hit production DB.

---

## 8. TODO Format

```php
// TODO [HIGH][2026-04-01]: Replace manual credit check with CreditManagementService.checkCreditLimit() (owner: @shamil)
// TODO [LOW][2026-06-01]: Extract invoice line validation into InvoiceLineValidator class
```

Priority levels: `HIGH`, `MEDIUM`, `LOW`

---

## 9. Constants

**Always use model constants** — never hardcode string literals for status, type, or code values.

```php
// GOOD
$invoice->update(['status' => Invoice::STATUS_SENT]);
if ($payslip->status === Payslip::STATUS_APPROVED) { ... }

// BAD
$invoice->update(['status' => 'sent']);
if ($payslip->status === 'approved') { ... }
```

Constants are defined on the model class:
```php
// In Invoice model
public const STATUS_DRAFT   = 'draft';
public const STATUS_SENT    = 'sent';
public const STATUS_PAID    = 'paid';
public const STATUS_VOIDED  = 'voided';
public const TYPE_STANDARD  = 'standard';
public const TYPE_CREDIT_NOTE = 'credit_note';
```

---

## 10. Prohibited Patterns

| Pattern | Why | Alternative |
|---------|-----|-------------|
| `processData()`, `doStuff()`, `handleRequest()` | Meaningless names hide intent | `generatePayslip()`, `postJournalEntry()`, `submitToZatca()` |
| Nested ifs deeper than 3 levels | Unreadable; hides bugs | Guard clauses, extract method |
| Business logic in controllers | Untestable without HTTP bootstrap | Move to service or action class |
| Queries outside Query classes (or inline in services/controllers for complex SELECTs) | Scattered data access, hard to optimize | Dedicated Query class with `execute()` |
| `float` arithmetic on money | Rounding errors accumulate | `bcadd()`, `bcmul()`, `bcdiv()` with 4 decimal places |
| `$collection->sum()`/`->count()` on large sets | Loads all rows into PHP | `DB::table()->selectRaw('SUM(...)')` |
| Lazy-loading relations inside loops | N+1 queries | `->with(...)` or `->load(...)` before the loop |
| Returning raw arrays from services as error signals | Silent failures; breaks error handling contract | Throw typed exceptions |
| Hardcoded string literals for status/type values | Typos, no refactoring support | Model constants (`Invoice::STATUS_DRAFT`) |
| `dd()`, `dump()`, `var_dump()` in committed code | Debug noise | Remove before commit; use structured logging |
