<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cost_center_budget_supplements');
        Schema::dropIfExists('cost_center_budget_lines');
        Schema::dropIfExists('cost_center_budgets');

        // Cost center budgets — annual budget header per cost center
        Schema::create('cost_center_budgets', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cost_center_id')
                ->constrained('cost_centers', 'id', 'cc_budget_cc_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('budget_version', 20)->default('0');
            $table->decimal('total_budget', 18, 4)->default(0);
            $table->char('currency', 3)->default('SAR');
            $table->enum('status', ['draft', 'approved', 'active'])->default('draft');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users', 'id', 'cc_budget_approved_by_fk')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'cost_center_id', 'fiscal_year', 'budget_version'],
                'cc_budget_unique'
            );
            $table->index(['organization_id', 'fiscal_year', 'status'], 'cc_budget_org_fy_status_idx');
        });

        // Cost center budget lines — per period, per cost element
        Schema::create('cost_center_budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cost_center_budget_id')
                ->constrained('cost_center_budgets', 'id', 'cc_bdgt_line_budget_fk')
                ->cascadeOnDelete();
            $table->tinyInteger('period')->unsigned(); // 1-12
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements', 'id', 'cc_bdgt_line_ce_fk')
                ->nullOnDelete();
            $table->decimal('budgeted_amount', 18, 4)->default(0);
            $table->decimal('committed_amount', 18, 4)->default(0);
            $table->decimal('actual_amount', 18, 4)->default(0);
            // Stored computed column: available = budgeted - committed - actual
            $table->decimal('available_amount', 18, 4)
                ->storedAs('budgeted_amount - committed_amount - actual_amount');
            $table->timestamps();

            $table->index(['cost_center_budget_id', 'period'], 'cc_bdgt_line_budget_period_idx');
        });

        // Budget supplement requests
        Schema::create('cost_center_budget_supplements', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cost_center_budget_id')
                ->constrained('cost_center_budgets', 'id', 'cc_bdgt_supp_budget_fk')
                ->cascadeOnDelete();
            $table->string('supplement_number', 50)->unique();
            $table->decimal('requested_amount', 18, 4);
            $table->decimal('approved_amount', 18, 4)->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')
                ->constrained('users', 'id', 'cc_bdgt_supp_req_by_fk')
                ->restrictOnDelete();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users', 'id', 'cc_bdgt_supp_rev_by_fk')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'cc_bdgt_supp_org_status_idx');
            $table->index(['cost_center_budget_id'], 'cc_bdgt_supp_budget_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center_budget_supplements');
        Schema::dropIfExists('cost_center_budget_lines');
        Schema::dropIfExists('cost_center_budgets');
    }
};
