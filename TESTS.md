# Test Inventory & Coverage Checklist

**714 tests · 2,846 assertions · SQLite in-memory · RefreshDatabase per class**

```bash
php artisan test                        # full suite
php artisan test --filter=<pattern>     # single module
php artisan test tests/Feature/Journeys # journey tests only
php artisan test tests/Unit/            # unit tests only
```

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Test file exists and passing |
| ⬜ | Not yet written — coverage gap |
| 🔸 | Partial coverage — happy path only |

---

## Unit Tests (`tests/Unit/`)

### Service Logic

| File | Status | Coverage |
|------|--------|---------|
| `Accounting/JournalEntryFactoryTest.php` | ✅ | Debit/credit balance, multi-line entries, currency, reversal |
| `Tax/TaxCalculatorServiceTest.php` | ✅ | VAT, GST, TDS, zero-rate, exempt, reverse-charge |
| `Core/FinancialIdempotencyServiceTest.php` | ✅ | Idempotency key generation, duplicate detection, TTL |
| `Core/FinancialOperationLoggerTest.php` | ✅ | Operation logging, context capture, audit record |
| `Core/FailedJobMonitorServiceTest.php` | ✅ | Failed job capture, alert thresholds |
| `Orchestrators/Sales/PostInvoiceOrchestratorTest.php` | ✅ | Invoice posting orchestration, GL entry creation |

### State Machines

| File | Status | Coverage |
|------|--------|---------|
| `StateMachine/WorkOrderStateTest.php` | ✅ | DRAFT→RELEASED→IN_PROGRESS→COMPLETED, invalid transitions |
| `StateMachine/PayrollPeriodStateTest.php` | ✅ | DRAFT→COMPUTED→APPROVED→PAID, disallowed transitions |
| `StateMachine/PaymentRunStateTest.php` | ✅ | DRAFT→PROCESSING→COMPLETED / CANCELLED |

### Model Traits

| File | Status | Coverage |
|------|--------|---------|
| `Concerns/BelongsToOrganizationTest.php` | ✅ | Global scope, auto-set org_id, withoutTenantCheck, tenant isolation |

### Missing Unit Test Coverage

| Area | Status | Notes |
|------|--------|-------|
| `InstallmentPlanService` | ⬜ | Equal schedule rounding, custom sum validation |
| `HouseBankService` | ⬜ | Advice number auto-generation, default-bank clearing |
| `BankReconciliationMatchingService` | ⬜ | Rule-based auto-match logic |
| `PaymentToleranceService` | ⬜ | Evaluate within/outside tolerance, GL write-off |
| `ForexService` | ⬜ | Revaluation gain/loss calculation |
| `PayrollCalculationService` | ⬜ | GOSI/EOSB formulas, prorated salary |
| `MrpService` | ⬜ | BOM explosion, demand netting |

---

## Feature Tests — Accounting (`tests/Feature/Accounting/`)

| File | Status | What is Covered |
|------|--------|----------------|
| `ChartOfAccountsTest.php` | ✅ | COA CRUD, account types, hierarchy, activation |
| `FiscalYearTest.php` | ✅ | FY create/open/close, period validation, period locking |
| `JournalEntryTest.php` | ✅ | Post, reverse, balance enforcement, multi-currency |
| `BankReconciliationTest.php` | ✅ | EBS import, auto-match, manual-match, complete, difference |
| `LoanTest.php` | ✅ | Disburse, schedule generation, repayment, early settlement |
| `MultiCurrencyTest.php` | ✅ | Exchange rates, FX posting, period-end revaluation |
| `ReportTest.php` | ✅ | P&L, Balance Sheet, Trial Balance, Cash Flow |
| Missing: `InstallmentPlanTest.php` | ⬜ | Equal/custom plans, payment recording, overdue marking |
| Missing: `HouseBankTest.php` | ⬜ | Bank CRUD, account management, advice lifecycle |
| Missing: `WithholdingTaxTest.php` | ⬜ | WHT code CRUD, calculate, apply, certificate |
| Missing: `PaymentToleranceTest.php` | ⬜ | Tolerance group CRUD, evaluate, clear to GL |
| Missing: `ParallelLedgerTest.php` | ⬜ | Ledger create, entry fan-out, mapping rules |
| Missing: `LeaseAccountingTest.php` | ⬜ | IFRS 16 schedule, ROU amortisation, termination |
| Missing: `AssetComponentTest.php` | ⬜ | Component add, partial retire, asset cost update |

---

## Feature Tests — Sales (`tests/Feature/Sales/`)

| File | Status | What is Covered |
|------|--------|----------------|
| `ContactTest.php` | ✅ | Customer/vendor CRUD, payment block/unblock |
| `InvoiceTest.php` | ✅ | Draft→posted→paid, VAT lines, credit check, ZATCA fields |
| `CreditNoteTest.php` | ✅ | Issue, GL posting, outstanding balance adjustment |
| `PaymentReceivedTest.php` | ✅ | Full/partial payment, advance clearing, overpayment rejection |
| `PaymentJournalTest.php` | ✅ | GL entries on payment posting |
| Missing: `QuotationTest.php` | 🔸 | Basic CRUD covered in journey; approval flow not unit-tested |
| Missing: `SalesOrderTest.php` | 🔸 | Basic CRUD covered in journey; delivery/ATP not isolated |
| Missing: `PromotionTest.php` | ⬜ | Promotion rules, pricing override, eligibility |
| Missing: `RebateSettlementTest.php` | ⬜ | Rebate accrual, settlement, GL posting |

---

## Feature Tests — Purchase (`tests/Feature/Purchase/`)

| File | Status | What is Covered |
|------|--------|----------------|
| `BillTest.php` | ✅ | Bill CRUD, 3-way match, approval |
| `BillJournalTest.php` | ✅ | GL entries on bill posting |
| `PaymentMadeTest.php` | ✅ | Full/partial vendor payment, GL posting |
| `PaymentMadeVoidTest.php` | ✅ | Payment reversal, balance restoration |
| Missing: `PurchaseOrderTest.php` | 🔸 | Basic lifecycle covered in journey; contract/ERS not isolated |
| Missing: `GoodsReceiptTest.php` | ⬜ | Stock posting, 3-way match tolerance |
| Missing: `PurchaseRequisitionTest.php` | ⬜ | Requisition → PO conversion workflow |

---

## Feature Tests — Inventory (`tests/Feature/Inventory/`)

| File | Status | What is Covered |
|------|--------|----------------|
| `ProductTest.php` | ✅ | Product master, variants, costing method |
| `CategoryTest.php` | ✅ | Category hierarchy, attribute inheritance |
| `StockTest.php` | ✅ | Goods issue/receipt, reorder points, stock valuation |
| Missing: `WarehouseTest.php` | ⬜ | Bin management, transfer orders, wave |
| Missing: `BatchManagementTest.php` | ⬜ | Batch master, FEFO picking, expiry |
| Missing: `PhysicalInventoryTest.php` | ⬜ | Count document, difference posting |

---

## Feature Tests — HR (`tests/Feature/HR/`)

| File | Status | What is Covered |
|------|--------|----------------|
| `EmployeeTest.php` | ✅ | Employee master, org assignment, bank details |
| `PayrollTest.php` | ✅ | Payroll run compute, approve, payslip, GOSI/EOSB |
| Missing: `AttendanceTest.php` | ⬜ | Check-in/out, shift assignment, overtime |
| Missing: `LeaveTest.php` | ⬜ | Leave request, approval, balance deduction, encashment |
| Missing: `RecruitmentTest.php` | ⬜ | Job posting → application → offer workflow |
| Missing: `PerformanceTest.php` | ⬜ | Goal setting, appraisal cycle, rating |

---

## Feature Tests — Critical Flows (`tests/Feature/CriticalFlows/`)

| File | Status | What is Covered |
|------|--------|----------------|
| `InvoiceLifecycleTest.php` | ✅ | Invoice draft → posted → paid → closed |
| `SalesOrderLifecycleTest.php` | ✅ | Quote → order → delivery → invoice |
| `PurchaseFlowTest.php` | ✅ | Requisition → PO → GR → bill → payment |
| `PayrollRunTest.php` | ✅ | Full payroll cycle: run → approve → payslips |
| `LeaveRequestTest.php` | ✅ | Submit → approve → balance deducted |
| `BankReconciliationTest.php` | ✅ | Statement import → match → complete |
| `PaymentRunTest.php` | ✅ | AP payment run → payment items → file |
| `CreditManagementTest.php` | ✅ | Credit limit, exposure calculation, hold release |
| `ZatcaComplianceTest.php` | ✅ | ZATCA webhook receive, signature verify, status update |

---

## Feature Tests — End-to-End Flows (`tests/Feature/EndToEnd/`)

| File | Status | What is Covered |
|------|--------|----------------|
| `InvoiceToPaymentToLedgerTest.php` | ✅ | Full AR cycle: invoice → payment → GL balance |
| `PurchaseOrderToBillToPaymentTest.php` | ✅ | Full AP cycle: PO → GR → bill → payment → GL |
| `PayrollToPayslipToJournalTest.php` | ✅ | Full payroll: run → payslips → GL entries |

---

## Journey Tests (`tests/Feature/Journeys/`)

Full multi-step SAP-parity scenarios. Each test verifies state transitions, GL effects, and error paths.

### Financial Accounting (`FinancialAccountingJourneyTest.php`) — 36 tests, 222 assertions

| Test | Status | SAP Equivalent |
|------|--------|---------------|
| `test_fi_aa_component_accounting_lifecycle` | ✅ | AS02 / ABAVN |
| `test_fi_aa_component_can_be_deleted_when_active_with_no_journal` | ✅ | AS02 |
| `test_ebam_full_account_opening_workflow` | ✅ | SAP EBAM |
| `test_ebam_account_closing_workflow` | ✅ | SAP EBAM |
| `test_ebam_reject_request_prevents_execution` | ✅ | SAP EBAM |
| `test_xbrl_filing_full_lifecycle` | ✅ | FINSC_LEDGER / iXBRL |
| `test_xbrl_filing_validation_fails_on_missing_required_concepts` | ✅ | iXBRL validation |
| `test_xbrl_duplicate_taxonomy_namespace_rejected` | ✅ | iXBRL |
| `test_fi_fx_auto_run_revaluation_creates_entries_for_foreign_accounts` | ✅ | FAGL_FC_VAL |
| `test_fi_fx_auto_run_fails_when_no_foreign_accounts` | ✅ | FAGL_FC_VAL |
| `test_fi_ap_payment_block_prevents_contact_appearing_in_payment_run` | ✅ | FBL1N / FBL5N |
| `test_fi_ap_unblock_restores_contact_for_payment` | ✅ | FBL1N |
| `test_fi_ap_block_requires_reason` | ✅ | FBL1N |
| `test_ifrs16_finance_lease_full_lifecycle` | ✅ | IFRS 16 |
| `test_ifrs16_lease_termination_derecognises_rou_and_liability` | ✅ | IFRS 16 |
| `test_ifrs16_operating_lease_posts_straight_line_expense` | ✅ | IFRS 16 |
| `test_abumn_asset_transfer_full_lifecycle` | ✅ | ABUMN |
| `test_abumn_asset_transfer_cancel` | ✅ | ABUMN |
| `test_wht_code_crud_lifecycle` | ✅ | SAP WHT |
| `test_wht_calculate_preview` | ✅ | SAP WHT |
| `test_wht_apply_to_payment_posts_gl_entry` | ✅ | SAP WHT |
| `test_wht_certificate_issuance` | ✅ | SAP WHT |
| `test_wht_summary_report` | ✅ | SAP WHT |
| `test_payment_tolerance_group_crud` | ✅ | OBA3 / OBB8 |
| `test_payment_tolerance_evaluate_within_tolerance` | ✅ | OBA3 |
| `test_payment_tolerance_clear_difference_posts_gl` | ✅ | OBB8 |
| `test_payment_tolerance_clear_rejects_exceeded_difference` | ✅ | OBB8 |
| `test_payment_tolerance_variance_summary` | ✅ | OBB8 |
| `test_installment_plan_equal_schedule_lifecycle` | ✅ | F-36 / F-59 |
| `test_installment_plan_custom_schedule` | ✅ | F-36 / F-59 |
| `test_installment_plan_cancel_waives_unpaid` | ✅ | F-36 |
| `test_installment_plan_custom_schedule_rejects_wrong_total` | ✅ | F-36 |
| `test_house_bank_lifecycle` | ✅ | FI12 |
| `test_payment_advice_lifecycle` | ✅ | FBZP |
| `test_payment_advice_auto_number_generation` | ✅ | FBZP |
| `test_house_bank_delete_blocked_by_active_advice` | ✅ | FI12 |

### Sales (`SalesJourneyTest.php`) — 5 tests

| Test | Status | Coverage |
|------|--------|---------|
| `test_full_sales_lifecycle_quotation_to_credit_note` | ✅ | Quote → order → invoice → payment → credit note |
| `test_voided_invoice_cannot_be_sent_again` | ✅ | Invoice void guard |
| `test_payment_over_invoice_total_is_rejected` | ✅ | Overpayment guard |
| `test_cross_tenant_invoice_access_is_denied` | ✅ | Tenant isolation |
| `test_invoice_journal_entry_organization_id_is_set` | ✅ | GL tenant isolation |

### Purchase (`PurchaseJourneyTest.php`)

| Test | Status | Coverage |
|------|--------|---------|
| Full procure-to-pay lifecycle | ✅ | Requisition → RFQ → PO → GR → bill → payment |
| Three-way match enforcement | ✅ | Tolerance rules |
| Purchase order approval workflow | ✅ | Multi-step approval |

### Manufacturing (`ManufacturingJourneyTest.php`) — 5 tests

| Test | Status | Coverage |
|------|--------|---------|
| `test_full_work_order_lifecycle` | ✅ | BOM → WO → RELEASED → IN_PROGRESS → COMPLETED |
| `test_work_order_completion_posts_gl_when_accounts_configured` | ✅ | CO-PC GL posting |
| `test_draft_bom_cannot_create_work_order` | ✅ | BOM state guard |
| `test_completed_work_order_cannot_be_started_again` | ✅ | State machine guard |
| `test_work_order_organization_id_matches_authenticated_user` | ✅ | Tenant isolation |

### Manufacturing Advanced (`ManufacturingAdvancedJourneyTest.php`)

| Test | Status | Coverage |
|------|--------|---------|
| MRP run with BOM explosion | ✅ | Demand → planned orders |
| Production costing overhead | ✅ | Costing sheet overhead run |
| Quality inspection on goods receipt | ✅ | Inspection lot → usage decision → stock posting |
| Subcontracting order | ✅ | Subcon PO → component issue → GR |

### Maintenance (`MaintenanceJourneyTest.php`)

| Test | Status | Coverage |
|------|--------|---------|
| Equipment master + maintenance plan | ✅ | Plan → order → release → complete |
| Work permit (PTW) lifecycle | ✅ | PTW request → approve → active → closed |
| Counter-based maintenance trigger | ✅ | Counter reading → threshold → auto-order |

### Multi-Tenant Isolation (`MultiTenantIsolationTest.php`)

| Test | Status | Coverage |
|------|--------|---------|
| Cross-org data access denied | ✅ | All major models |
| RBAC permission enforcement | ✅ | Missing permission → 403 |
| Super-admin bypass | ✅ | Super-admin sees all orgs |
| Module subscription gate | ✅ | Disabled module → 403 |

### Real Estate (`RealEstateJourneyTest.php`)

| Test | Status | Coverage |
|------|--------|---------|
| Full lease lifecycle | ✅ | Draft → active → expired |
| IFRS 16 schedule posting | ✅ | ROU + liability journal |
| Service charge settlement | ✅ | Allocation → settlement run |

### Warehouse & Quality (`WarehouseQualityJourneyTest.php`) — 7 tests

| Test | Status | Coverage |
|------|--------|---------|
| `test_goods_issue_create_to_post` | ✅ | GI document → stock deduction → GL |
| `test_goods_issue_gl_entry_carries_correct_organization_id` | ✅ | Tenant isolation |
| `test_goods_issue_can_be_reversed_after_posting` | ✅ | Reversal |
| `test_quality_plan_creation` | ✅ | QM plan master |
| `test_inspection_lot_lifecycle_acceptance` | ✅ | Lot → usage decision → accept |
| `test_inspection_lot_rejected_when_all_units_fail` | ✅ | Full rejection |
| `test_quality_notification_carries_correct_organization_id` | ✅ | Tenant isolation |

---

## Missing Journey Tests (Recommended)

| Journey | Priority | Notes |
|---------|----------|-------|
| HR payroll full cycle | 🔴 High | Run → approve → payslips → GOSI posting |
| ZATCA e-invoicing flow | 🔴 High | Invoice → submit → webhook cleared → compliance_status update |
| Bank reconciliation full cycle | 🔴 High | MT940 import → auto-match → post difference |
| Intercompany billing | 🟡 Medium | IC sales order → AR billing → auto AP posting on counterpart |
| CO assessment cycle | 🟡 Medium | Define → execute → GL posting → reverse |
| Dunning run | 🟡 Medium | Overdue AR → dunning level → letter generated |
| Freight tendering | 🟡 Medium | Request → bid → award → transport order |
| Fixed asset depreciation run | 🟡 Medium | Asset → depreciation run → GL posting |
| GRC audit lifecycle | 🟢 Low | Audit plan → checklist → findings → CAPA |
| Loyalty points lifecycle | 🟢 Low | Transaction → earn points → redeem rewards |

---

## Security & Cross-Cutting

| Test Area | Status | Notes |
|-----------|--------|-------|
| JWT token expiry returns 401 | ✅ | Via `ZatcaComplianceTest` + auth tests |
| Rate limiting on auth endpoints | 🔸 | Config verified; load test not automated |
| HMAC webhook signature verify | ✅ | `ZatcaComplianceTest` + webhook tests |
| SQL injection via query params | ⬜ | Not automated — use DAST tooling |
| XSS in API responses | ⬜ | Not applicable (JSON API, no HTML) |
| 2FA enforcement | ⬜ | Feature exists; E2E test missing |
| IP allowlist middleware | 🔸 | Middleware tested in unit; E2E not covered |

---

## Running Specific Test Groups

```bash
# Unit tests
php artisan test tests/Unit/

# All accounting feature tests
php artisan test tests/Feature/Accounting/
php artisan test tests/Feature/CriticalFlows/

# All journey tests (slowest — hits full DB)
php artisan test tests/Feature/Journeys/

# Single file
php artisan test tests/Feature/Journeys/FinancialAccountingJourneyTest.php

# By SAP module
php artisan test --filter=FI        # Financial Accounting
php artisan test --filter=Journey   # All journeys
php artisan test --filter=Payroll   # Payroll

# Parallel (requires phpunit-parallel or Pest)
php artisan test --parallel
```

---

## Coverage Summary

| Module | Unit | Feature | Journey | Overall |
|--------|------|---------|---------|---------|
| Financial Accounting (FI) | 🔸 | 🔸 | ✅ | 🔸 |
| Controlling (CO) | ⬜ | ⬜ | 🔸 | 🔸 |
| Materials Management (MM) | ⬜ | ✅ | ✅ | 🔸 |
| Sales & Distribution (SD) | ✅ | ✅ | ✅ | ✅ |
| Human Capital Management (HCM) | ⬜ | ✅ | 🔸 | 🔸 |
| Quality Management (QM) | ⬜ | ⬜ | ✅ | 🔸 |
| Manufacturing (PP) | ✅ | ⬜ | ✅ | 🔸 |
| Plant Maintenance (PM) | ⬜ | ⬜ | ✅ | 🔸 |
| Project System (PS) | ⬜ | ⬜ | 🔸 | 🔸 |
| CRM | ⬜ | ⬜ | ⬜ | ⬜ |
| ZATCA Compliance | ⬜ | ✅ | 🔸 | 🔸 |
| Real Estate (RE-FX) | ⬜ | ⬜ | ✅ | 🔸 |
| Multi-Tenancy | ✅ | ⬜ | ✅ | ✅ |
| RBAC / Permissions | ⬜ | 🔸 | ✅ | 🔸 |
| Webhooks / Events | ⬜ | 🔸 | ⬜ | 🔸 |
| Tax / WHT | ✅ | ⬜ | ✅ | 🔸 |
| Analytics / Fraud / AML | ⬜ | ⬜ | ⬜ | ⬜ |
| Transportation (TM) | ⬜ | ⬜ | 🔸 | 🔸 |
| Real Estate (RE-FX) | ⬜ | ⬜ | ✅ | 🔸 |
| Loyalty / Messaging | ⬜ | ⬜ | ⬜ | ⬜ |

**Legend:** ✅ good coverage · 🔸 partial · ⬜ missing
