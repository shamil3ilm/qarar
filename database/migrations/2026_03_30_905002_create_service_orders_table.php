<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_service_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('service_order_number', 30)->unique();
            $table->unsignedBigInteger('maintenance_order_id')->nullable();
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable()->comment('FK to suppliers/vendors');
            $table->enum('status', ['draft', 'issued', 'confirmed', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->enum('service_type', ['repair', 'inspection', 'installation', 'calibration', 'overhaul'])->default('repair');
            $table->text('description');
            $table->date('requested_date');
            $table->date('due_date');
            $table->date('completed_date')->nullable();
            $table->decimal('estimated_cost', 15, 4)->default(0);
            $table->decimal('actual_cost', 15, 4)->default(0);
            $table->string('sla_response_hours', 10)->nullable()->comment('SLA: response time in hours');
            $table->string('sla_resolution_hours', 10)->nullable()->comment('SLA: resolution time in hours');
            $table->timestamp('sla_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();
            $table->timestamp('vendor_responded_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->unsignedBigInteger('purchase_order_id')->nullable()->comment('Linked PO');
            $table->unsignedBigInteger('bill_id')->nullable()->comment('Vendor invoice when completed');
            $table->text('vendor_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_service_orders');
    }
};
