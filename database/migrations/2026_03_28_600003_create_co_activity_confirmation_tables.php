<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('co_activity_confirmations');

        Schema::create('co_activity_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('confirmation_number', 50)->unique();
            $table->foreignId('work_order_id')
                ->nullable()
                ->constrained('work_orders', 'id', 'co_act_conf_wo_fk')
                ->nullOnDelete();
            $table->foreignId('work_center_id')
                ->nullable()
                ->constrained('work_centers', 'id', 'co_act_conf_wc_fk')
                ->nullOnDelete();
            $table->foreignId('cost_center_id')
                ->nullable()
                ->constrained('cost_centers', 'id', 'co_act_conf_cc_fk')
                ->nullOnDelete();
            $table->foreignId('activity_type_id')
                ->nullable()
                ->constrained('activity_types', 'id', 'co_act_conf_at_fk')
                ->nullOnDelete();
            $table->decimal('confirmed_quantity', 18, 4);
            $table->decimal('planned_quantity', 18, 4)->nullable();
            $table->string('uom', 20)->default('HR');
            $table->decimal('actual_rate', 18, 4)->nullable();
            $table->decimal('planned_rate', 18, 4)->nullable();
            $table->decimal('actual_cost', 18, 4)->nullable();
            $table->unsignedSmallInteger('fiscal_year');
            $table->tinyInteger('period')->unsigned();
            $table->date('confirmation_date');
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users', 'id', 'co_act_conf_by_fk')
                ->nullOnDelete();
            $table->enum('status', ['confirmed', 'reversed'])->default('confirmed');
            // Self-referential FK for reversal
            $table->unsignedBigInteger('reversal_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('reversal_id', 'co_act_conf_reversal_fk')
                ->references('id')
                ->on('co_activity_confirmations')
                ->nullOnDelete();

            $table->index(['organization_id', 'fiscal_year', 'period'], 'co_act_conf_org_fy_period_idx');
            $table->index(['work_order_id'], 'co_act_conf_wo_idx');
            $table->index(['cost_center_id', 'activity_type_id'], 'co_act_conf_cc_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('co_activity_confirmations');
    }
};
