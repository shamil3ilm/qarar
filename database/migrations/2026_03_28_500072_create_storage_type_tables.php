<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('storage_type_determination_rules');
        Schema::dropIfExists('storage_types');

        Schema::create('storage_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('warehouse_id')->constrained('warehouses')->name('st_warehouse_fk');
            $table->string('storage_type_code', 20);
            $table->string('storage_type_name', 100);
            $table->enum('storage_class', [
                'bulk',
                'rack',
                'floor',
                'refrigerated',
                'hazmat',
                'high_security',
                'quarantine',
            ])->default('rack');
            $table->enum('capacity_management', [
                'no_check',
                'total_weight',
                'total_qty',
                'occupied_bins',
            ])->default('no_check');
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->decimal('max_quantity', 18, 4)->nullable();
            $table->unsignedInteger('total_bins')->nullable();
            $table->decimal('current_utilization_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['warehouse_id', 'storage_type_code'], 'st_warehouse_code_unq');
            $table->index(['organization_id', 'warehouse_id'], 'st_org_warehouse_idx');
        });

        Schema::create('storage_type_determination_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('stdr_org_fk');
            $table->foreignId('storage_type_id')->constrained('storage_types')->name('stdr_st_fk');
            $table->foreignId('warehouse_id')->constrained('warehouses')->name('stdr_warehouse_fk');
            $table->enum('movement_type', ['goods_receipt', 'goods_issue', 'transfer', 'returns'])
                ->default('goods_receipt');
            $table->string('product_storage_class', 50)->nullable();
            $table->decimal('max_weight_kg', 10, 2)->nullable();
            $table->unsignedTinyInteger('priority')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['warehouse_id', 'movement_type'], 'stdr_wh_movement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_type_determination_rules');
        Schema::dropIfExists('storage_types');
    }
};
