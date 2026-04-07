<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Freight rate table header (SAP TM: Scale tables / Condition technique)
        Schema::create('tm_freight_rate_tables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->foreignId('carrier_id')->nullable()->constrained('tm_carriers')->nullOnDelete();
            $table->foreignId('carrier_service_id')->nullable()->constrained('tm_carrier_services')->nullOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('currency_code', 5)->default('USD');
            // basis: how the rate is applied
            $table->string('basis', 20)->default('weight'); // weight|volume|piece|pallet|shipment
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 'tm_freight_rate_tables_org_code_uniq');
            $table->index(['organization_id', 'carrier_id', 'is_active'], 'tm_freight_rate_tables_org_carrier_active_idx');
            $table->index(['organization_id', 'valid_from', 'valid_to'], 'tm_freight_rate_tables_org_validity_idx');
        });

        // Rate lines — origin/destination zone + weight/volume breaks
        Schema::create('tm_freight_rate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_table_id')->constrained('tm_freight_rate_tables')->cascadeOnDelete();
            $table->string('origin_zone', 100)->nullable();   // null = any
            $table->string('destination_zone', 100)->nullable();
            $table->decimal('weight_from', 10, 3)->default(0);
            $table->decimal('weight_to', 10, 3)->nullable(); // null = unlimited
            $table->decimal('volume_from', 10, 4)->default(0);
            $table->decimal('volume_to', 10, 4)->nullable();
            $table->decimal('base_rate', 14, 4)->default(0);    // fixed charge
            $table->decimal('per_unit_rate', 14, 6)->default(0); // per kg, per cbm, etc.
            $table->decimal('min_charge', 14, 4)->default(0);
            $table->decimal('max_charge', 14, 4)->nullable();
            $table->timestamps();

            $table->index(['rate_table_id', 'origin_zone', 'destination_zone'], 'tm_freight_rate_lines_table_zones_idx');
        });

        // Surcharges: fuel, toll, DG handling, remote area, residential, etc.
        Schema::create('tm_freight_surcharges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_table_id')->nullable()->constrained('tm_freight_rate_tables')->nullOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained('tm_carriers')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('type', 30)->default('fuel');
            // type: fuel|toll|insurance|dg_handling|remote_area|oversize|residential|peak|other
            $table->string('calculation_method', 20)->default('pct');
            // calculation_method: flat|pct|per_kg|per_cbm|per_piece
            $table->decimal('value', 14, 6);
            $table->string('currency_code', 5)->default('USD');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'type', 'is_active'], 'tm_freight_surcharges_org_type_active_idx');
            $table->index(['organization_id', 'carrier_id', 'is_active'], 'tm_freight_surcharges_org_carrier_active_idx');
        });

        // Freight agreements: contracted rates per carrier (SAP TM: Freight Agreement)
        Schema::create('tm_freight_agreements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('tm_carriers')->cascadeOnDelete();
            $table->string('agreement_number', 50);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('currency_code', 5)->default('USD');
            $table->string('status', 20)->default('draft'); // draft|active|expired|terminated
            $table->foreignId('rate_table_id')->nullable()->constrained('tm_freight_rate_tables')->nullOnDelete();
            $table->decimal('annual_volume_commitment', 14, 4)->nullable(); // kg
            $table->decimal('annual_spend_commitment', 14, 4)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'agreement_number'], 'tm_freight_agreements_org_num_uniq');
            $table->index(['organization_id', 'carrier_id', 'status'], 'tm_freight_agreements_org_carrier_status_idx');
            $table->index(['organization_id', 'valid_from', 'valid_to'], 'tm_freight_agreements_org_validity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_freight_agreements');
        Schema::dropIfExists('tm_freight_surcharges');
        Schema::dropIfExists('tm_freight_rate_lines');
        Schema::dropIfExists('tm_freight_rate_tables');
    }
};
