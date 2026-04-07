<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Carrier master (SAP TM: BP with carrier role + LO-MDM)
        Schema::create('tm_carriers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 200);
            $table->string('type', 30)->default('road'); // road|air|sea|rail|courier|multimodal
            $table->string('status', 20)->default('active'); // active|inactive|suspended
            $table->string('scac_code', 10)->nullable(); // Standard Carrier Alpha Code (road/rail)
            $table->string('iata_code', 10)->nullable(); // IATA carrier code (air)
            $table->string('country_code', 5)->nullable();
            $table->string('currency_code', 5)->default('USD');
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->decimal('rating', 3, 2)->nullable(); // 0.00–5.00 computed from performance
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'tm_carriers_org_status_idx');
            $table->index(['organization_id', 'type', 'status'], 'tm_carriers_org_type_status_idx');
        });

        // Carrier service levels (e.g. Express, Economy, Next-Day)
        Schema::create('tm_carrier_services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('tm_carriers')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('mode', 30)->default('road'); // road|air|sea|rail|courier
            $table->unsignedSmallInteger('transit_days_min')->default(1);
            $table->unsignedSmallInteger('transit_days_max')->default(1);
            $table->boolean('is_tracking_available')->default(false);
            $table->string('tracking_url_template', 500)->nullable(); // {tracking_number} placeholder
            $table->boolean('handles_dangerous_goods')->default(false);
            $table->boolean('handles_refrigerated')->default(false);
            $table->boolean('handles_oversized')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'carrier_id', 'code'], 'tm_carrier_services_org_carrier_code_uniq');
            $table->index(['organization_id', 'carrier_id', 'is_active'], 'tm_carrier_services_org_carrier_active_idx');
        });

        // Monthly carrier performance KPIs (SAP TM: Carrier Evaluation)
        Schema::create('tm_carrier_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('tm_carriers')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month'); // 1–12
            $table->unsignedInteger('total_shipments')->default(0);
            $table->unsignedInteger('on_time_deliveries')->default(0);
            $table->unsignedInteger('late_deliveries')->default(0);
            $table->unsignedInteger('damaged_shipments')->default(0);
            $table->unsignedInteger('lost_shipments')->default(0);
            $table->decimal('avg_transit_days', 5, 2)->nullable();
            $table->decimal('cost_variance_pct', 6, 2)->nullable(); // actual vs agreed
            $table->decimal('on_time_pct', 5, 2)->nullable(); // computed
            $table->decimal('rating', 3, 2)->nullable(); // 0.00–5.00
            $table->timestamps();

            $table->unique(['organization_id', 'carrier_id', 'period_year', 'period_month'], 'tm_carrier_perf_org_carrier_period_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_carrier_performance');
        Schema::dropIfExists('tm_carrier_services');
        Schema::dropIfExists('tm_carriers');
    }
};
