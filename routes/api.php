<?php

use App\Http\Controllers\Api\V1\Compliance\ZatcaWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// ZATCA webhook receiver — outside auth groups, verified by HMAC signature middleware
Route::post('v1/webhooks/zatca', [ZatcaWebhookController::class, 'handle'])
    ->middleware(['throttle:30,1', 'verify.zatca.webhook']);

// API Version 1
Route::prefix('v1')->middleware(['api.version'])->group(function () {
    // Auth routes (public)
    require __DIR__.'/api/v1/auth.php';

    // Protected routes
    Route::middleware(['auth:api', 'validate.jwt', 'check.organization', 'throttle:api', 'track.activity'])->group(function () {
        // Core module routes (always enabled)
        require __DIR__.'/api/v1/core.php';

        // Compliance / ZATCA onboarding routes (core, always available)
        require __DIR__.'/api/v1/compliance.php';

        // Tax Compliance — GCC VAT Returns, India GST, India TDS/TCS
        require __DIR__.'/api/v1/tax-compliance.php';

        // Tax Determination Rules — configurable rules engine (TAXINJ/TAXINN equivalent)
        require __DIR__.'/api/v1/tax.php';

        // Change Management — freeze periods, feature flag targeting, onboarding, readiness
        Route::prefix('change-freeze')->group(function () {
            require __DIR__.'/api/v1/change-freeze.php';
        });
        require __DIR__.'/api/v1/feature-flags.php';
        Route::prefix('onboarding')->group(function () {
            require __DIR__.'/api/v1/onboarding.php';
        });
        Route::prefix('module-readiness')->group(function () {
            require __DIR__.'/api/v1/module-readiness.php';
        });

        // Accounting module routes (with module check)
        Route::middleware(['check.module:accounting'])->group(function () {
            require __DIR__.'/api/v1/accounting.php';

            Route::prefix('bank-reconciliation')->group(function () {
                require __DIR__.'/api/v1/bank-reconciliation.php';
            });

            Route::prefix('loans')->group(function () {
                require __DIR__.'/api/v1/loans.php';
            });

            Route::prefix('multi-currency')->group(function () {
                require __DIR__.'/api/v1/multi-currency.php';
            });

            // Asset Accounting (FI-AA)
            require __DIR__.'/api/v1/asset-accounting.php';

            // CO module — Cost & Profit Centers
            Route::prefix('controlling')->group(function () {
                require __DIR__.'/api/v1/controlling.php';
            });

            // FI Consolidation
            Route::prefix('consolidation')->group(function () {
                require __DIR__.'/api/v1/consolidation.php';
            });

            // Financial Management — Dunning, Credit Limits, Petty Cash, Cash Flow
            require __DIR__.'/api/v1/financial-management.php';

            // CO Module — Cost Elements, Activity Types, Internal Orders, CO-PA
            require __DIR__.'/api/v1/co-module.php';

            // Period Lock — accounting period lock overrides
            Route::prefix('period-lock')->group(function () {
                require __DIR__.'/api/v1/period-lock.php';
            });

            // FI Structural — Accruals, Carry Forward, Payment Run, Disputes, Parked Documents
            require __DIR__.'/api/v1/fi-structural.php';

            // FI-TR — Treasury Management (investments, bank positions, liquidity plans)
            require __DIR__.'/api/v1/treasury.php';

            // MM-ML — Material Ledger / Actual Costing
            require __DIR__.'/api/v1/material-ledger.php';
        });

        // Inventory module routes (with module check)
        Route::prefix('inventory')->middleware(['check.module:inventory'])->group(function () {
            require __DIR__.'/api/v1/inventory.php';

            Route::prefix('barcode')->group(function () {
                require __DIR__.'/api/v1/barcode.php';
            });

            require __DIR__.'/api/v1/product-details.php';

            // Wave Management & Picking
            Route::prefix('warehouse-mgmt')->group(function () {
                require __DIR__.'/api/v1/wave.php';
            });

            // Physical Inventory (MM — stock counting & adjustment posting)
            require __DIR__.'/api/v1/physical-inventory.php';

            // WM Structural — Warehouse Transfer Orders
            require __DIR__.'/api/v1/wm-structural.php';

            // MM Material Valuation — MAP, Standard Cost Variance, Revaluation
            require __DIR__.'/api/v1/material-valuation.php';

            // EWM — Extended Warehouse Management (SAP EWM: bins, transfer orders, putaway, labor)
            Route::prefix('ewm')->group(function () {
                require __DIR__.'/api/v1/ewm.php';
            });
        });

        // Sales module routes (with module check)
        Route::prefix('sales')->middleware(['check.module:sales'])->group(function () {
            require __DIR__.'/api/v1/sales.php';

            Route::prefix('bulk')->group(function () {
                require __DIR__.'/api/v1/bulk-sales.php';
            });

            Route::prefix('price-overrides')->group(function () {
                require __DIR__.'/api/v1/price-overrides.php';
            });

            Route::prefix('offers')->group(function () {
                require __DIR__.'/api/v1/offers.php';
            });

            Route::prefix('payment-delivery')->group(function () {
                require __DIR__.'/api/v1/payment-delivery.php';
            });

            // Price List Management — tiered/volume pricing
            require __DIR__.'/api/v1/price-lists.php';

            // Consignment Sales
            require __DIR__.'/api/v1/consignment.php';

            // Pricing Conditions (SD — condition technique: procedures, types, records, resolve)
            Route::prefix('pricing-conditions')->group(function () {
                require __DIR__.'/api/v1/pricing-conditions.php';
            });

            // SD Advanced — ATP, Customer Material Info, Output Determination, Delivery Split
            require __DIR__.'/api/v1/sd-advanced.php';
        });

        // Intercompany Sales (SD — cross-org order routing; spans two orgs, no single org scope)
        require __DIR__.'/api/v1/intercompany.php';

        // Purchase module routes (with module check)
        Route::prefix('purchase')->middleware(['check.module:purchase'])->group(function () {
            require __DIR__.'/api/v1/purchase.php';

            // Supplier Performance Tracking
            Route::prefix('supplier-performance')->group(function () {
                require __DIR__.'/api/v1/supplier-performance.php';
            });

            // Procurement — RFQ, Vendor Contracts, Goods Receipts, Vendor Advances
            require __DIR__.'/api/v1/procurement.php';

            // Purchase Requisitions (MM PR workflow)
            require __DIR__.'/api/v1/purchase-requisitions.php';
        });

        // HR module routes (with module check)
        Route::prefix('hr')->middleware(['check.module:hr'])->group(function () {
            require __DIR__.'/api/v1/hr.php';

            Route::prefix('leave-management')->group(function () {
                require __DIR__.'/api/v1/leave-management.php';
            });

            Route::prefix('performance')->group(function () {
                require __DIR__.'/api/v1/performance.php';
            });

            require __DIR__.'/api/v1/recruitment.php';

            Route::prefix('training')->group(function () {
                require __DIR__.'/api/v1/training.php';
            });

            // HR Compliance — EOSB/Gratuity, Social Insurance (GOSI), Benefits, Shifts, Succession
            require __DIR__.'/api/v1/hr-compliance.php';

            // HCM Structural — Compensation Reviews, Position Management, Overtime
            require __DIR__.'/api/v1/hcm-structural.php';

            // HCM Gap 7: Time Evaluation / CATS — Time Sheets, Wage Types, Cost Allocation
            require __DIR__.'/api/v1/time-evaluation.php';

            // HCM Gap 8: Travel Expense Management — Per Diem Rates, Travel Requests, Claims
            require __DIR__.'/api/v1/travel-expense.php';
        });

        // CRM module routes (with module check)
        Route::prefix('crm')->middleware(['check.module:crm'])->group(function () {
            require __DIR__.'/api/v1/crm.php';

            // Territory Management
            Route::prefix('territories')->group(function () {
                require __DIR__.'/api/v1/territory.php';
            });
        });

        // Manufacturing module routes (with module check)
        Route::prefix('manufacturing')->middleware(['check.module:manufacturing'])->group(function () {
            require __DIR__.'/api/v1/manufacturing.php';
            require __DIR__.'/api/v1/quality.php';

            // MRP — Material Requirements Planning
            require __DIR__.'/api/v1/mrp.php';

            // Capacity Planning
            Route::prefix('capacity')->group(function () {
                require __DIR__.'/api/v1/capacity.php';
            });

            // Product Costing (CO-PC) — Standard/Actual costing, variances
            require __DIR__.'/api/v1/product-costing.php';

            // Subcontracting / Job Work
            require __DIR__.'/api/v1/subcontracting.php';

            // PP Routing — Work Centers & Routing Master Data
            require __DIR__.'/api/v1/pp-routing.php';
        });

        // Plant Maintenance module routes (with module check)
        Route::prefix('maintenance')->middleware(['check.module:maintenance'])->group(function () {
            require __DIR__.'/api/v1/maintenance.php';

            // PM Condition-Based Maintenance — measurements, condition rules, spare parts
            require __DIR__.'/api/v1/pm-condition.php';
        });

        // Project Systems module routes (with module check)
        Route::prefix('ps')->middleware(['check.module:projects'])->group(function () {
            require __DIR__.'/api/v1/projects.php';

            // PS EVM — WBS, Earned Value Management, Project Settlement
            require __DIR__.'/api/v1/ps-evm.php';

            // PS Budget Availability Control — versions, supplements, availability checks
            require __DIR__.'/api/v1/ps-budget.php';
        });

        // Automation module routes (with module check)
        Route::prefix('automation')->middleware(['check.module:automation'])->group(function () {
            require __DIR__.'/api/v1/automation.php';
        });

        // Messaging module routes (with module check)
        Route::prefix('messaging')->middleware(['check.module:messaging'])->group(function () {
            require __DIR__.'/api/v1/messaging.php';
        });

        // Expense module routes (with module check)
        Route::prefix('expenses')->middleware(['check.module:expenses'])->group(function () {
            require __DIR__.'/api/v1/expenses.php';
        });

        // E-Commerce module routes (with module check)
        Route::prefix('ecommerce')->middleware(['check.module:ecommerce'])->group(function () {
            require __DIR__.'/api/v1/ecommerce.php';
        });

        // Customs & Excise module routes (with module check)
        Route::prefix('customs')->middleware(['check.module:customs'])->group(function () {
            require __DIR__.'/api/v1/customs.php';
        });

        // International Trade module routes (with module check)
        Route::prefix('trade')->middleware(['check.module:trade'])->group(function () {
            require __DIR__.'/api/v1/trade.php';
        });

        // TM — Transportation Management (SAP TM: carriers, rate engine, tendering, load building)
        Route::prefix('tm')->middleware(['check.module:tm'])->group(function () {
            require __DIR__.'/api/v1/tm.php';
        });

        // RE-FX — Real Estate Flexible Framework (SAP RE-FX: portfolio, leases, posting, settlement)
        Route::prefix('real-estate')->middleware(['check.module:real_estate'])->group(function () {
            require __DIR__.'/api/v1/real-estate.php';
        });

        // Loyalty & Rewards module routes (with module check)
        Route::prefix('loyalty')->middleware(['check.module:loyalty'])->group(function () {
            require __DIR__.'/api/v1/loyalty.php';
        });

        // Document Vault routes (core, always available)
        require __DIR__.'/api/v1/documents.php';

        // Custom Fields routes (core, always available)
        require __DIR__.'/api/v1/custom-fields.php';

        // Activity Logging routes (core, always available)
        require __DIR__.'/api/v1/activity-logs.php';

        // Reports & Dashboard routes (core, always available)
        require __DIR__.'/api/v1/reports.php';

        // Calendar routes (core, always available)
        Route::prefix('calendar')->group(function () {
            require __DIR__.'/api/v1/calendar.php';
        });

        // Task Board routes (core, always available)
        Route::prefix('task-boards')->group(function () {
            require __DIR__.'/api/v1/task-boards.php';
        });

        // Module Access / RBAC routes (core, always available)
        Route::prefix('module-access')->group(function () {
            require __DIR__.'/api/v1/module-access.php';
        });

        // Fraud Detection routes (core, always available)
        Route::prefix('fraud')->group(function () {
            require __DIR__.'/api/v1/fraud.php';
        });

        // AML (Anti-Money Laundering) routes (core, always available)
        Route::prefix('aml')->group(function () {
            require __DIR__.'/api/v1/aml.php';
        });

        // Billing & Subscription routes (org can view own subscription)
        Route::prefix('billing')->group(function () {
            require __DIR__.'/api/v1/billing.php';
        });

        // Budget Management module routes (with module check)
        Route::prefix('budget')->middleware(['check.module:budget'])->group(function () {
            require __DIR__.'/api/v1/budget.php';
        });

        // EDI / IDoc routes (core, always available)
        require __DIR__.'/api/v1/edi.php';

        // Campaign & Segment routes (core, always available)
        require __DIR__.'/api/v1/campaigns.php';

        // Analytics routes (core, always available)
        Route::prefix('analytics')->group(function () {
            require __DIR__.'/api/v1/analytics.php';
        });

        // Classification System (Platform — cross-cutting characteristic framework)
        require __DIR__.'/api/v1/classification.php';

        // Workflow Escalation & Substitution (Platform — SLA enforcement)
        require __DIR__.'/api/v1/workflow-escalation.php';
    });

    // Platform Admin routes (super admin only, no organization scope)
    Route::prefix('admin')->middleware(['auth:api', 'validate.jwt', 'super.admin'])->group(function () {
        require __DIR__.'/api/v1/admin.php';
    });

    // Denied Party Screening + Customer Self-Service Portal
    // DPS requires standard JWT; portal routes use their own session token mechanism.
    require __DIR__.'/api/v1/dps-portal.php';
});
