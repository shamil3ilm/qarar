<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('work_center_type', ['machine', 'labor', 'assembly', 'inspection', 'other'])->default('machine');
            $table->decimal('capacity_per_day', 8, 2)->default(8)->comment('hours');
            $table->decimal('efficiency_percent', 5, 2)->default(100);
            $table->enum('calendar_type', ['5day', '6day', '7day'])->default('5day');
            $table->decimal('cost_per_hour', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('work_center_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->date('exception_date');
            $table->decimal('available_hours', 5, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['work_center_id', 'exception_date']);
        });

        Schema::create('capacity_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->unsignedBigInteger('operation_id')->nullable();
            $table->decimal('required_hours', 8, 2);
            $table->dateTime('scheduled_start')->nullable();
            $table->dateTime('scheduled_end')->nullable();
            $table->enum('status', ['planned', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->timestamps();

            $table->index(['work_center_id', 'status']);
            $table->index('organization_id');
        });

        Schema::create('capacity_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->cascadeOnDelete();
            $table->date('load_date');
            $table->decimal('planned_hours', 8, 2)->default(0);
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->decimal('available_hours', 8, 2)->default(8);
            $table->timestamps();

            $table->unique(['work_center_id', 'load_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_loads');
        Schema::dropIfExists('capacity_requirements');
        Schema::dropIfExists('work_center_exceptions');
        Schema::dropIfExists('work_centers');
    }
};
