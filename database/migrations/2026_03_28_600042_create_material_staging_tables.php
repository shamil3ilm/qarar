<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('staging_movements');
        Schema::dropIfExists('staging_request_lines');
        Schema::dropIfExists('staging_requests');

        Schema::create('staging_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('request_number')->unique();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders', 'id', 'stag_req_wo_fk');
            $table->string('production_supply_area')->nullable();
            $table->foreignId('requested_by')->constrained('users', 'id', 'stag_req_usr_fk');
            $table->date('required_date');
            $table->enum('status', ['open', 'in_progress', 'staged', 'cancelled'])->default('open');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staging_request_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staging_request_id')->constrained('staging_requests', 'id', 'stag_line_req_fk');
            $table->foreignId('product_id')->constrained('products', 'id', 'stag_line_prod_fk');
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('staged_quantity', 18, 4)->default(0);
            $table->string('uom', 20);
            $table->foreignId('source_warehouse_id')->nullable()->constrained('warehouses', 'id', 'stag_line_wh_fk');
            $table->foreignId('source_location_id')->nullable()->constrained('warehouse_locations', 'id', 'stag_line_loc_fk');
            $table->enum('status', ['open', 'partial', 'complete'])->default('open');
            $table->timestamps();
        });

        Schema::create('staging_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('staging_request_line_id')->constrained('staging_request_lines', 'id', 'stag_mov_line_fk');
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements', 'id', 'stag_mov_sm_fk');
            $table->decimal('moved_quantity', 18, 4);
            $table->foreignId('moved_by')->constrained('users', 'id', 'stag_mov_usr_fk');
            $table->timestamp('moved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_movements');
        Schema::dropIfExists('staging_request_lines');
        Schema::dropIfExists('staging_requests');
    }
};
