<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // accrual_deferrals — recurring accrual/deferral entries
        Schema::create('accrual_deferrals', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('reference', 50);
            $table->enum('type', ['accrual', 'deferral']);
            $table->foreignId('debit_account_id')->constrained('chart_of_accounts');
            $table->foreignId('credit_account_id')->constrained('chart_of_accounts');
            $table->decimal('total_amount', 15, 4);
            $table->decimal('per_period_amount', 15, 4);
            $table->string('currency_code', 3)->default('SAR');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('periods_total');
            $table->integer('periods_posted')->default(0);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'ad_org_status_idx');
            $table->index(['organization_id', 'start_date', 'end_date'], 'ad_org_dates_idx');
        });

        // carry_forward_runs — year-end balance carry forward
        Schema::create('carry_forward_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('from_fiscal_year_id')->constrained('fiscal_years');
            $table->foreignId('to_fiscal_year_id')->constrained('fiscal_years');
            $table->enum('run_type', ['balance_sheet', 'profit_loss', 'both'])->default('both');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->integer('accounts_processed')->default(0);
            $table->decimal('total_amount_carried', 15, 4)->default(0);
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'from_fiscal_year_id'], 'cfr_org_fy_idx');
        });

        // payment_runs — F110-equivalent automatic payment run
        Schema::create('payment_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('run_reference', 50);
            $table->enum('payment_direction', ['outgoing', 'incoming'])->default('outgoing');
            $table->date('payment_date');
            $table->date('due_date_from')->nullable();
            $table->date('due_date_to')->nullable();
            $table->json('vendor_filter')->nullable();
            $table->json('payment_methods')->nullable();
            $table->decimal('minimum_payment', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->enum('status', ['draft', 'proposed', 'approved', 'posted', 'cancelled'])->default('draft');
            $table->integer('total_items')->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'run_reference'], 'pr_org_ref_unique');
            $table->index(['organization_id', 'status'], 'payment_runs_org_status_idx');
        });

        // payment_run_items — individual payments proposed by the run
        Schema::create('payment_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_run_id')->constrained('payment_runs')->cascadeOnDelete();
            $table->enum('document_type', ['bill', 'purchase_order', 'invoice']);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->decimal('open_amount', 15, 4);
            $table->decimal('payment_amount', 15, 4);
            $table->decimal('discount_taken', 15, 4)->default(0);
            $table->date('due_date')->nullable();
            $table->enum('status', ['proposed', 'included', 'excluded', 'paid'])->default('proposed');
            $table->string('exclusion_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['payment_run_id', 'status'], 'pri_run_status_idx');
            $table->index(['document_type', 'document_id'], 'pri_doc_idx');
        });

        // dispute_cases — AR dispute management
        Schema::create('dispute_cases', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('case_number', 30);
            $table->enum('document_type', ['invoice', 'payment_received', 'credit_note']);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('contact_id');
            $table->decimal('disputed_amount', 15, 4);
            $table->decimal('resolved_amount', 15, 4)->default(0);
            $table->enum('dispute_reason', ['pricing', 'quality', 'quantity', 'delivery', 'duplicate', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_review', 'escalated', 'resolved', 'closed'])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'case_number'], 'dc_org_case_unique');
            $table->index(['organization_id', 'status'], 'dc_org_status_idx');
            $table->index(['contact_id', 'status'], 'dc_contact_status_idx');
        });

        // collections_worklist — FSCM collections management with promise-to-pay
        Schema::create('collections_worklist', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id');
            $table->decimal('total_overdue', 15, 4)->default(0);
            $table->integer('overdue_days_max')->default(0);
            $table->enum('collections_status', ['new', 'contacted', 'promise_to_pay', 'payment_plan', 'legal', 'written_off'])->default('new');
            $table->date('promise_to_pay_date')->nullable();
            $table->decimal('promise_amount', 15, 4)->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contact_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'collections_status'], 'cw_org_status_idx');
            $table->index(['organization_id', 'contact_id'], 'cw_org_contact_idx');
        });

        // parked_documents — FI parked documents (saved but not posted)
        Schema::create('parked_documents', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('reference', 50)->nullable();
            $table->date('document_date');
            $table->date('posting_date');
            $table->json('document_data');
            $table->decimal('total_debit', 15, 4)->default(0);
            $table->decimal('total_credit', 15, 4)->default(0);
            $table->string('currency_code', 3)->default('SAR');
            $table->text('parking_reason')->nullable();
            $table->foreignId('parked_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['parked', 'pending_approval', 'posted', 'rejected'])->default('parked');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status'], 'pd_org_status_idx');
            $table->index(['organization_id', 'document_date'], 'pd_org_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parked_documents');
        Schema::dropIfExists('collections_worklist');
        Schema::dropIfExists('dispute_cases');
        Schema::dropIfExists('payment_run_items');
        Schema::dropIfExists('payment_runs');
        Schema::dropIfExists('carry_forward_runs');
        Schema::dropIfExists('accrual_deferrals');
    }
};
