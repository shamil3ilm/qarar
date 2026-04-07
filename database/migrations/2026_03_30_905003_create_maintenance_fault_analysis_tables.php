<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_fault_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('description', 255);
            $table->enum('fault_type', ['mechanical', 'electrical', 'hydraulic', 'software', 'operator', 'wear', 'other'])->default('other');
            $table->text('cause')->nullable();
            $table->text('recommended_action')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_root_cause_analyses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('maintenance_order_id');
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('fault_code_id')->nullable();
            $table->enum('rca_method', ['5_why', 'fishbone', 'fault_tree', 'fmea', 'other'])->default('5_why');
            $table->json('whys')->nullable()->comment('For 5-Why: array of why strings');
            $table->text('root_cause')->nullable();
            $table->text('contributing_factors')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->text('preventive_actions')->nullable();
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('target_date')->nullable();
            $table->date('closed_date')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'equipment_id'], 'maintenance_root_cause_analyses_org_equipment_idx');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_root_cause_analyses');
        Schema::dropIfExists('maintenance_fault_codes');
    }
};
