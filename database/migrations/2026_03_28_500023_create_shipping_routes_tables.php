<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shipping_route_determinations');
        Schema::dropIfExists('shipping_routes');
        Schema::dropIfExists('shipping_zones');

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('zone_code', 20);
            $table->string('zone_name', 100);
            $table->json('country_codes')->nullable();
            $table->string('postal_code_pattern', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'zone_code'], 'sz_org_code_unq');
        });

        Schema::create('shipping_routes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('sr_org_fk');
            $table->string('route_code', 30);
            $table->string('route_name', 100);
            $table->foreignId('departure_zone_id')->constrained('shipping_zones')->cascadeOnDelete()->name('sr_dep_zone_fk');
            $table->foreignId('destination_zone_id')->constrained('shipping_zones')->cascadeOnDelete()->name('sr_dest_zone_fk');
            $table->enum('transportation_mode', ['road', 'air', 'sea', 'rail', 'courier'])->default('road');
            $table->unsignedSmallInteger('transit_days')->default(1);
            $table->string('carrier', 100)->nullable();
            $table->decimal('freight_cost', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'route_code'], 'sr_org_code_unq');
            $table->index(['departure_zone_id', 'destination_zone_id'], 'sr_dep_dest_idx');
        });

        Schema::create('shipping_route_determinations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete()->name('srd_org_fk');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete()->name('srd_so_fk');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete()->name('srd_shipment_fk');
            $table->foreignId('shipping_route_id')->nullable()->constrained('shipping_routes')->nullOnDelete()->name('srd_route_fk');
            $table->foreignId('departure_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete()->name('srd_dep_zone_fk');
            $table->foreignId('destination_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete()->name('srd_dest_zone_fk');
            $table->dateTime('determined_at');
            $table->timestamps();
            $table->index(['sales_order_id'], 'srd_so_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_route_determinations');
        Schema::dropIfExists('shipping_routes');
        Schema::dropIfExists('shipping_zones');
    }
};
