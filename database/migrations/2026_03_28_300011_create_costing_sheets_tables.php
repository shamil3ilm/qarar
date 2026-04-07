<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop in reverse FK order
        Schema::dropIfExists('overhead_key_rates');
        Schema::dropIfExists('overhead_keys');
        Schema::dropIfExists('costing_sheet_run_results');
        Schema::dropIfExists('costing_sheet_runs');
        Schema::dropIfExists('costing_sheet_rows');
        Schema::dropIfExists('costing_sheets');

        Schema::create('costing_sheets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('cost_component_structure_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'cs_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unique(['organization_id', 'code'], 'cs_org_code_uq');
            $table->index(['organization_id', 'is_active'], 'cs_org_active_idx');
        });

        Schema::create('costing_sheet_rows', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('costing_sheet_id');
            $table->string('row_type', 20);
            $table->string('description');
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('base_cost_element_id')->nullable();
            $table->unsignedBigInteger('overhead_key_id')->nullable();
            $table->unsignedBigInteger('credit_cost_center_id')->nullable();
            $table->unsignedBigInteger('credit_cost_element_id')->nullable();
            $table->integer('from_row')->nullable();
            $table->integer('to_row')->nullable();
            $table->timestamps();

            $table->foreign('costing_sheet_id', 'csr_cs_fk')
                ->references('id')->on('costing_sheets')->onDelete('cascade');
            // overhead_key_id FK added after overhead_keys table is created
            $table->foreign('base_cost_element_id', 'csr_base_ce_fk')
                ->references('id')->on('cost_elements')->onDelete('set null');
            $table->foreign('credit_cost_center_id', 'csr_credit_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('credit_cost_element_id', 'csr_credit_ce_fk')
                ->references('id')->on('cost_elements')->onDelete('set null');
        });

        Schema::create('overhead_keys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('code', 30);
            $table->string('name');
            $table->string('overhead_type', 20)->default('percentage');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id', 'ok_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');

            $table->unique(['organization_id', 'code'], 'ok_org_code_uq');
        });

        // Now add the overhead_key_id FK on costing_sheet_rows
        Schema::table('costing_sheet_rows', function (Blueprint $table): void {
            $table->foreign('overhead_key_id', 'csr_ok_fk')
                ->references('id')->on('overhead_keys')->onDelete('set null');
        });

        Schema::create('overhead_key_rates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('overhead_key_id');
            $table->date('validity_from');
            $table->date('validity_to')->nullable();
            $table->decimal('overhead_rate', 10, 6);
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('activity_type_id')->nullable();
            $table->timestamps();

            $table->foreign('overhead_key_id', 'okr_ok_fk')
                ->references('id')->on('overhead_keys')->onDelete('cascade');
            $table->foreign('cost_center_id', 'okr_cc_fk')
                ->references('id')->on('cost_centers')->onDelete('set null');
            $table->foreign('activity_type_id', 'okr_at_fk')
                ->references('id')->on('activity_types')->onDelete('set null');

            $table->index(['overhead_key_id', 'validity_from'], 'okr_key_validity_idx');
        });

        Schema::create('costing_sheet_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('costing_sheet_id');
            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');
            $table->dateTime('run_date');
            $table->decimal('total_overhead', 18, 4)->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id', 'csrun_org_fk')
                ->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('costing_sheet_id', 'csrun_cs_fk')
                ->references('id')->on('costing_sheets')->onDelete('cascade');
            $table->foreign('created_by', 'csrun_user_fk')
                ->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('costing_sheet_run_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('costing_sheet_run_id');
            $table->unsignedBigInteger('costing_sheet_row_id');
            $table->decimal('base_amount', 18, 4)->default(0);
            $table->decimal('overhead_rate', 10, 6)->default(0);
            $table->decimal('overhead_amount', 18, 4)->default(0);
            $table->boolean('credit_posted')->default(false);
            $table->timestamps();

            $table->foreign('costing_sheet_run_id', 'csrr_run_fk')
                ->references('id')->on('costing_sheet_runs')->onDelete('cascade');
            $table->foreign('costing_sheet_row_id', 'csrr_row_fk')
                ->references('id')->on('costing_sheet_rows')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costing_sheet_run_results');
        Schema::dropIfExists('costing_sheet_runs');
        Schema::dropIfExists('overhead_key_rates');

        // Drop FK before dropping overhead_keys
        Schema::table('costing_sheet_rows', function (Blueprint $table): void {
            $table->dropForeign('csr_ok_fk');
        });

        Schema::dropIfExists('overhead_keys');
        Schema::dropIfExists('costing_sheet_rows');
        Schema::dropIfExists('costing_sheets');
    }
};
