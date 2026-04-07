<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('notification_number', 20)->unique();
            $table->enum('notification_type', ['M1', 'M2', 'M3', 'S1', 'S4'])->default('M2');
            $table->string('short_text', 200);
            $table->text('long_text')->nullable();

            // Equipment / functional location linkage
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->foreign('equipment_id')
                ->references('id')
                ->on('equipment')
                ->nullOnDelete();
            $table->string('functional_location_code', 50)->nullable();

            // Priority and categorisation
            $table->enum('priority', ['1_very_high', '2_high', '3_medium', '4_low'])->default('3_medium');
            $table->date('malfunction_start_date')->nullable();
            $table->date('malfunction_end_date')->nullable();
            $table->decimal('malfunction_duration_hours', 8, 2)->nullable();
            $table->boolean('breakdown')->default(false);
            $table->boolean('production_stop')->default(false);

            // Coding
            $table->string('damage_code', 20)->nullable();
            $table->string('cause_code', 20)->nullable();
            $table->string('activity_code', 20)->nullable();
            $table->text('cause_text')->nullable();
            $table->text('task_text')->nullable();

            // Workflow status (SAP IW-style)
            $table->enum('status', ['OSNO', 'NOPR', 'INIT', 'ORAS', 'NOCO', 'COMP'])->default('OSNO');

            // Maintenance order linkage (when converted)
            $table->unsignedBigInteger('maintenance_order_id')->nullable();

            // People
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();

            // Completion
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_text')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'notification_type'], 'maintenance_notifications_org_notif_type_idx');
            $table->index(['organization_id', 'equipment_id']);
            $table->index(['organization_id', 'priority']);
        });

        // Notification items (SAP IW28 — sub-tasks within a notification)
        Schema::create('maintenance_notification_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('maintenance_notifications')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('item_number');
            $table->string('short_text', 200);
            $table->text('long_text')->nullable();
            $table->string('damage_code', 20)->nullable();
            $table->string('cause_code', 20)->nullable();
            $table->enum('status', ['outstanding', 'in_process', 'completed', 'cleared'])->default('outstanding');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'item_number'], 'maintenance_notification_items_notif_item_num_uniq');
        });

        // Notification tasks (tasks assigned to resolve the notification)
        Schema::create('maintenance_notification_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')
                ->constrained('maintenance_notifications')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('task_number');
            $table->string('description', 200);
            $table->text('details')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->enum('status', ['outstanding', 'in_process', 'completed', 'cleared'])->default('outstanding');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'task_number'], 'maintenance_notification_tasks_notif_task_num_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_notification_tasks');
        Schema::dropIfExists('maintenance_notification_items');
        Schema::dropIfExists('maintenance_notifications');
    }
};
