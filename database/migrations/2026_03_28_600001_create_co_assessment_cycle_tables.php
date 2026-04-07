<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop in reverse FK order if already exist (idempotent guard)
        Schema::dropIfExists('co_assessment_postings');
        Schema::dropIfExists('co_assessment_cycle_receivers');
        Schema::dropIfExists('co_assessment_cycle_segments');
        Schema::dropIfExists('co_assessment_cycles');

        // Assessment cycles — header
        Schema::create('co_assessment_cycles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('cycle_type', ['assessment', 'distribution'])->default('assessment');
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period_from')->unsigned()->default(1);  // 1-12
            $table->tinyInteger('period_to')->unsigned()->default(12);
            $table->enum('status', ['open', 'executed', 'reversed'])->default('open');
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users', 'id', 'co_asmt_cyc_exec_by_fk')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fiscal_year', 'status'], 'co_asmt_cyc_org_fy_status_idx');
        });

        // Assessment cycle segments — define sender/tracing rules
        Schema::create('co_assessment_cycle_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('assessment_cycle_id')
                ->constrained('co_assessment_cycles', 'id', 'co_asmt_seg_cycle_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('segment_number')->default(1);
            $table->foreignId('sender_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_seg_snd_cc_fk')
                ->nullOnDelete();
            $table->foreignId('sender_profit_center_id')
                ->nullable()
                ->constrained('profit_centers', 'id', 'co_asmt_seg_snd_pc_fk')
                ->nullOnDelete();
            $table->foreignId('sender_cost_element_id')
                ->nullable()
                ->constrained('cost_elements', 'id', 'co_asmt_seg_snd_ce_fk')
                ->nullOnDelete();
            $table->enum('tracing_factor', ['fixed_percentages', 'statistical_key_figure', 'posted_amounts'])
                ->default('fixed_percentages');
            $table->foreignId('skf_id')
                ->nullable()
                ->constrained('statistical_key_figures', 'id', 'co_asmt_seg_skf_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['assessment_cycle_id'], 'co_asmt_seg_cycle_idx');
        });

        // Assessment cycle receivers — one row per receiver in a segment
        Schema::create('co_assessment_cycle_receivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_cycle_segment_id')
                ->constrained('co_assessment_cycle_segments', 'id', 'co_asmt_rcv_seg_fk')
                ->cascadeOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_profit_center_id')
                ->nullable()
                ->constrained('profit_centers', 'id', 'co_asmt_rcv_pc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_order_id')
                ->nullable()
                ->constrained('internal_orders', 'id', 'co_asmt_rcv_io_fk')
                ->nullOnDelete();
            $table->decimal('fixed_percentage', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['assessment_cycle_segment_id'], 'co_asmt_rcv_seg_idx');
        });

        // Assessment postings — actual cost lines generated when cycle is executed
        Schema::create('co_assessment_postings', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('assessment_cycle_id')
                ->constrained('co_assessment_cycles', 'id', 'co_asmt_post_cycle_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period')->unsigned();
            $table->foreignId('sender_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_post_snd_cc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_asmt_post_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('cost_element_id')
                ->nullable()
                ->constrained('cost_elements', 'id', 'co_asmt_post_ce_fk')
                ->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            // Self-referential FK for reversals
            $table->unsignedBigInteger('reversal_id')->nullable();
            $table->timestamps();

            $table->foreign('reversal_id', 'co_asmt_post_reversal_fk')
                ->references('id')
                ->on('co_assessment_postings')
                ->nullOnDelete();

            $table->index(['organization_id', 'fiscal_year', 'period'], 'co_asmt_post_org_fy_period_idx');
            $table->index(['assessment_cycle_id'], 'co_asmt_post_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_assessment_postings');
        Schema::dropIfExists('co_assessment_cycle_receivers');
        Schema::dropIfExists('co_assessment_cycle_segments');
        Schema::dropIfExists('co_assessment_cycles');
    }
};
