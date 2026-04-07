<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // budgets
        // ----------------------------------------------------------------
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->string('name');
            $table->enum('budget_type', ['annual', 'quarterly', 'project', 'department'])->default('annual');
            $table->enum('status', ['draft', 'submitted', 'approved', 'active', 'closed', 'cancelled'])->default('draft');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'fiscal_year_id']);
        });

        // ----------------------------------------------------------------
        // budget_lines
        // ----------------------------------------------------------------
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->decimal('q1_amount', 15, 2)->default(0);
            $table->decimal('q2_amount', 15, 2)->default(0);
            $table->decimal('q3_amount', 15, 2)->default(0);
            $table->decimal('q4_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('budget_id');
        });

        // ----------------------------------------------------------------
        // budget_revisions
        // ----------------------------------------------------------------
        Schema::create('budget_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->smallInteger('revision_number')->unsigned();
            $table->text('reason');
            $table->decimal('previous_total', 15, 2);
            $table->decimal('new_total', 15, 2);
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['budget_id', 'revision_number']);
        });

        // ----------------------------------------------------------------
        // budget_revision_lines
        // ----------------------------------------------------------------
        Schema::create('budget_revision_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_revision_id')->constrained('budget_revisions')->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->cascadeOnDelete();
            $table->string('field_changed');
            $table->decimal('old_value', 15, 2);
            $table->decimal('new_value', 15, 2);
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // budget_commitments
        // ----------------------------------------------------------------
        Schema::create('budget_commitments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->cascadeOnDelete();
            $table->enum('source_type', ['purchase_order', 'expense_report', 'journal_entry'])->default('purchase_order');
            $table->unsignedBigInteger('source_id');
            $table->decimal('committed_amount', 15, 2);
            $table->enum('status', ['open', 'partially_used', 'used', 'cancelled'])->default('open');
            $table->timestamp('committed_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('budget_line_id');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_commitments');
        Schema::dropIfExists('budget_revision_lines');
        Schema::dropIfExists('budget_revisions');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
