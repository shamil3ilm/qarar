<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('prt_operation_assignments');
        Schema::dropIfExists('production_resource_tools');

        Schema::create('production_resource_tools', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('prt_number', 50);
            $table->string('prt_name', 100);
            $table->enum('prt_type', ['tool', 'fixture', 'jig', 'test_equipment', 'document', 'program'])->default('tool');
            $table->enum('status', ['available', 'in_use', 'maintenance', 'retired'])->default('available');
            $table->string('location', 100)->nullable();
            $table->unsignedInteger('quantity_available')->default(1);
            $table->unsignedInteger('quantity_in_use')->default(0);
            $table->string('serial_number', 100)->nullable();
            $table->date('next_calibration_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'prt_number'], 'prt_org_number_unq');
            $table->index(['organization_id', 'status'], 'prt_org_status_idx');
        });

        Schema::create('prt_operation_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->name('poa_org_fk');
            $table->foreignId('production_resource_tool_id')->constrained('production_resource_tools')->cascadeOnDelete()->name('poa_prt_fk');
            $table->foreignId('routing_operation_id')->nullable()->constrained('routing_operations')->nullOnDelete()->name('poa_routing_op_fk');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete()->name('poa_wo_fk');
            $table->enum('usage_type', ['required', 'optional'])->default('required');
            $table->unsignedInteger('quantity_required')->default(1);
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->enum('status', ['planned', 'assigned', 'in_use', 'released'])->default('planned');
            $table->timestamps();

            $table->index(['production_resource_tool_id'], 'poa_prt_idx');
            $table->index(['work_order_id'], 'poa_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prt_operation_assignments');
        Schema::dropIfExists('production_resource_tools');
    }
};
