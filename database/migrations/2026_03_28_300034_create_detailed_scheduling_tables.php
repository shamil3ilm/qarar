<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('scheduling_pegging_relationships');
        Schema::dropIfExists('scheduling_operations');
        Schema::dropIfExists('scheduling_boards');

        Schema::create('scheduling_boards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('horizon_days')->default(14);
            $table->json('work_center_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scheduling_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduling_board_id')
                ->nullable()
                ->constrained('scheduling_boards')
                ->nullOnDelete();
            $table->foreignId('work_order_id')
                ->nullable()
                ->constrained('work_orders')
                ->nullOnDelete();
            $table->foreignId('process_order_id')
                ->nullable()
                ->constrained('process_orders')
                ->nullOnDelete();
            $table->foreignId('work_center_id')
                ->constrained('work_centers')
                ->cascadeOnDelete();
            $table->unsignedInteger('operation_number');
            $table->string('description');
            $table->dateTime('planned_start');
            $table->dateTime('planned_finish');
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_finish')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('setup_minutes')->default(0);
            $table->unsignedInteger('teardown_minutes')->default(0);
            $table->unsignedInteger('priority')->default(50);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_fixed')->default(false);
            $table->unsignedInteger('sequence_number')->nullable();
            $table->timestamps();

            $table->index(
                ['work_center_id', 'planned_start'],
                'sched_op_wc_start_idx'
            );
            $table->index(
                ['scheduling_board_id'],
                'sched_op_board_idx'
            );
            $table->index(
                ['work_order_id'],
                'sched_op_wo_idx'
            );
            $table->index(
                ['priority', 'planned_start'],
                'sched_op_prio_start_idx'
            );
        });

        Schema::create('scheduling_pegging_relationships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('predecessor_operation_id');
            $table->unsignedBigInteger('successor_operation_id');
            $table->foreign('predecessor_operation_id', 'sched_peg_predecessor_fk')
                ->references('id')->on('scheduling_operations')->cascadeOnDelete();
            $table->foreign('successor_operation_id', 'sched_peg_successor_fk')
                ->references('id')->on('scheduling_operations')->cascadeOnDelete();
            $table->string('relationship_type', 20)->default('fs');
            $table->integer('lag_minutes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_pegging_relationships');
        Schema::dropIfExists('scheduling_operations');
        Schema::dropIfExists('scheduling_boards');
    }
};
