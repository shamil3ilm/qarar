<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('repetitive_mfg_backflushes');
        Schema::dropIfExists('repetitive_mfg_schedule_lines');
        Schema::dropIfExists('repetitive_mfg_schedules');
        Schema::dropIfExists('production_lines');

        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->foreignId('work_center_id')
                ->nullable()
                ->constrained('work_centers')
                ->nullOnDelete();
            $table->decimal('capacity_per_hour', 10, 4)->nullable();
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('units_of_measure')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'pl_org_code_unique');
        });

        Schema::create('repetitive_mfg_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_version_id')
                ->nullable()
                ->constrained('production_versions')
                ->nullOnDelete();
            $table->foreignId('production_line_id')
                ->constrained('production_lines')
                ->cascadeOnDelete();
            $table->date('schedule_date_from');
            $table->date('schedule_date_to');
            $table->decimal('total_planned_quantity', 18, 4);
            $table->decimal('total_confirmed_quantity', 18, 4)->default(0);
            $table->string('status', 20)->default('planned');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['product_id', 'schedule_date_from'],
                'rms_product_date_idx'
            );
            $table->index(
                ['production_line_id', 'status'],
                'rms_line_status_idx'
            );
            $table->index(
                ['status', 'schedule_date_from'],
                'rms_status_date_idx'
            );
        });

        Schema::create('repetitive_mfg_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('repetitive_mfg_schedule_id')
                ->constrained('repetitive_mfg_schedules')
                ->cascadeOnDelete();
            $table->date('schedule_date');
            $table->decimal('planned_quantity', 18, 4);
            $table->decimal('confirmed_quantity', 18, 4)->default(0);
            $table->string('status', 20)->default('planned');
            $table->timestamps();
        });

        Schema::create('repetitive_mfg_backflushes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repetitive_mfg_schedule_id')
                ->constrained('repetitive_mfg_schedules')
                ->cascadeOnDelete();
            $table->dateTime('backflush_date');
            $table->decimal('quantity_produced', 18, 4);
            $table->decimal('quantity_scrapped', 18, 4)->default(0);
            $table->json('component_movements')->nullable();
            $table->decimal('labor_time_minutes', 10, 2)->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repetitive_mfg_backflushes');
        Schema::dropIfExists('repetitive_mfg_schedule_lines');
        Schema::dropIfExists('repetitive_mfg_schedules');
        Schema::dropIfExists('production_lines');
    }
};
