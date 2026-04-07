<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. project_budget_versions ────────────────────────────────────────
        Schema::dropIfExists('project_budget_availability_log');
        Schema::dropIfExists('project_budget_supplements');
        Schema::dropIfExists('project_budget_line_items');
        Schema::dropIfExists('project_budget_versions');

        Schema::create('project_budget_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id', 'pbv_project_fk')->references('id')->on('projects')->cascadeOnDelete();

            $table->string('version_code', 20);
            $table->string('version_name', 100);
            $table->unsignedSmallInteger('fiscal_year');
            $table->enum('status', ['draft', 'active', 'frozen', 'archived'])->default('draft');
            $table->boolean('is_current')->default(false);
            $table->decimal('total_budget', 18, 4)->default(0);

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'pbv_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'project_id', 'version_code', 'fiscal_year'],
                'pbv_org_proj_ver_fy_unq'
            );
            $table->index(['project_id', 'is_current'], 'pbv_project_current_idx');
        });

        // ── 2. project_budget_line_items ──────────────────────────────────────
        Schema::create('project_budget_line_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pbli_org_fk')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_budget_version_id');
            $table->foreign('project_budget_version_id', 'pbli_version_fk')
                ->references('id')->on('project_budget_versions')->cascadeOnDelete();

            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->foreign('wbs_element_id', 'pbli_wbs_fk')->references('id')->on('wbs_elements')->nullOnDelete();

            $table->unsignedBigInteger('cost_element_id')->nullable();
            $table->foreign('cost_element_id', 'pbli_cost_element_fk')->references('id')->on('cost_elements')->nullOnDelete();

            $table->decimal('budgeted_amount', 18, 4)->default(0);
            $table->decimal('committed_amount', 18, 4)->default(0);
            $table->decimal('actual_amount', 18, 4)->default(0);
            $table->decimal('available_amount', 18, 4)->default(0);
            $table->enum('avac_action', ['warning', 'error', 'none'])->default('warning');
            $table->decimal('tolerance_percent', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['project_budget_version_id', 'wbs_element_id', 'cost_element_id'],
                'pbli_ver_wbs_ce_unq'
            );
            $table->index(['project_budget_version_id'], 'pbli_version_idx');
            $table->index(['wbs_element_id'], 'pbli_wbs_idx');
        });

        // ── 3. project_budget_supplements ────────────────────────────────────
        Schema::create('project_budget_supplements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pbs_org_fk')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_budget_version_id');
            $table->foreign('project_budget_version_id', 'pbs_version_fk')
                ->references('id')->on('project_budget_versions')->cascadeOnDelete();

            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->foreign('wbs_element_id', 'pbs_wbs_fk')->references('id')->on('wbs_elements')->nullOnDelete();

            $table->enum('supplement_type', ['supplement', 'return', 'transfer_in', 'transfer_out'])->default('supplement');
            $table->decimal('amount', 18, 4);
            $table->text('reason')->nullable();
            $table->string('reference_number', 50)->nullable();

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->foreign('approved_by', 'pbs_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();

            $table->index(['project_budget_version_id'], 'pbs_version_idx');
        });

        // ── 4. project_budget_availability_log ───────────────────────────────
        Schema::create('project_budget_availability_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id', 'pbal_org_fk')->references('id')->on('organizations')->cascadeOnDelete();

            $table->unsignedBigInteger('project_budget_line_item_id');
            $table->foreign('project_budget_line_item_id', 'pbal_pbli_fk')
                ->references('id')->on('project_budget_line_items')->cascadeOnDelete();

            $table->unsignedBigInteger('wbs_element_id')->nullable();
            $table->foreign('wbs_element_id', 'pbal_wbs_fk')->references('id')->on('wbs_elements')->nullOnDelete();

            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');

            $table->decimal('requested_amount', 18, 4);
            $table->decimal('available_amount', 18, 4);
            $table->enum('result', ['approved', 'warning', 'rejected']);
            $table->string('message', 255)->nullable();
            $table->dateTime('checked_at');

            $table->unsignedBigInteger('checked_by')->nullable();
            $table->foreign('checked_by', 'pbal_checked_by_fk')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['wbs_element_id', 'checked_at'], 'pbal_wbs_checked_idx');
            $table->index(['document_type', 'document_id'], 'pbal_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_availability_log');
        Schema::dropIfExists('project_budget_supplements');
        Schema::dropIfExists('project_budget_line_items');
        Schema::dropIfExists('project_budget_versions');
    }
};
