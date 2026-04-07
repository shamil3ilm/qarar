<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('co_distribution_postings');
        Schema::dropIfExists('co_distribution_segment_receivers');
        Schema::dropIfExists('co_distribution_segments');
        Schema::dropIfExists('co_distribution_cycles');

        // Distribution cycles — header
        Schema::create('co_distribution_cycles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period_from')->unsigned()->default(1);
            $table->tinyInteger('period_to')->unsigned()->default(12);
            $table->enum('status', ['open', 'executed', 'reversed'])->default('open');
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('executed_by')
                ->nullable()
                ->constrained('users', 'id', 'co_dist_cyc_exec_by_fk')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'fiscal_year', 'status'], 'co_dist_cyc_org_fy_status_idx');
        });

        // Distribution segments — sender + cost elements
        Schema::create('co_distribution_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('distribution_cycle_id')
                ->constrained('co_distribution_cycles', 'id', 'co_dist_seg_cycle_fk')
                ->cascadeOnDelete();
            $table->foreignId('sender_cost_center_id')
                ->constrained('cost_centers', 'id', 'co_dist_seg_snd_cc_fk')
                ->restrictOnDelete();
            // JSON array of cost_element IDs covered by this segment
            $table->json('cost_element_ids');
            $table->enum('tracing_factor', ['fixed_percentages', 'statistical_key_figure', 'posted_amounts'])
                ->default('fixed_percentages');
            $table->foreignId('skf_id')
                ->nullable()
                ->constrained('statistical_key_figures', 'id', 'co_dist_seg_skf_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['distribution_cycle_id'], 'co_dist_seg_cycle_idx');
        });

        // Distribution segment receivers
        Schema::create('co_distribution_segment_receivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('distribution_segment_id')
                ->constrained('co_distribution_segments', 'id', 'co_dist_rcv_seg_fk')
                ->cascadeOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_dist_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('receiver_profit_center_id')
                ->nullable()
                ->constrained('profit_centers', 'id', 'co_dist_rcv_pc_fk')
                ->nullOnDelete();
            $table->decimal('fixed_percentage', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['distribution_segment_id'], 'co_dist_rcv_seg_idx');
        });

        // Distribution postings
        Schema::create('co_distribution_postings', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('distribution_cycle_id')
                ->constrained('co_distribution_cycles', 'id', 'co_dist_post_cycle_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period')->unsigned();
            $table->foreignId('sender_cost_center_id')
                ->constrained('cost_centers', 'id', 'co_dist_post_snd_cc_fk')
                ->restrictOnDelete();
            $table->foreignId('receiver_cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_dist_post_rcv_cc_fk')
                ->nullOnDelete();
            $table->foreignId('cost_element_id')
                ->constrained('cost_elements', 'id', 'co_dist_post_ce_fk')
                ->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('SAR');
            $table->timestamps();

            $table->index(['organization_id', 'fiscal_year', 'period'], 'co_dist_post_org_fy_period_idx');
            $table->index(['distribution_cycle_id'], 'co_dist_post_cycle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_distribution_postings');
        Schema::dropIfExists('co_distribution_segment_receivers');
        Schema::dropIfExists('co_distribution_segments');
        Schema::dropIfExists('co_distribution_cycles');
    }
};
