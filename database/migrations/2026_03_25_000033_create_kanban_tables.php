<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_supply_areas', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'ksa_org_code_unique');
        });

        Schema::create('kanban_control_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supply_area_id')->constrained('kanban_supply_areas')->cascadeOnDelete();
            $table->enum('replenishment_strategy', ['production', 'purchase', 'stock_transfer'])->default('production');
            $table->integer('number_of_cards')->default(1);
            $table->decimal('replenishment_quantity', 15, 4);
            $table->decimal('safety_stock_quantity', 15, 4)->default(0);
            $table->integer('replenishment_lead_time_days')->default(1);
            $table->foreignId('source_vendor_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'product_id'], 'kcc_org_product_idx');
        });

        Schema::create('kanban_cards', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('control_cycle_id')->constrained('kanban_control_cycles')->cascadeOnDelete();
            $table->string('card_number', 30);
            $table->enum('status', ['full', 'empty', 'in_replenishment', 'waiting'])->default('full');
            $table->decimal('current_quantity', 15, 4)->default(0);
            $table->timestamp('emptied_at')->nullable();
            $table->timestamp('replenishment_triggered_at')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->unsignedBigInteger('triggered_document_id')->nullable();
            $table->string('triggered_document_type', 30)->nullable();
            $table->timestamps();
            $table->unique(['control_cycle_id', 'card_number'], 'kc_cycle_number_unique');
            $table->index(['control_cycle_id', 'status'], 'kc_cycle_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_cards');
        Schema::dropIfExists('kanban_control_cycles');
        Schema::dropIfExists('kanban_supply_areas');
    }
};
