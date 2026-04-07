<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Portfolio — top-level grouping of real estate holdings
        Schema::create('re_portfolios', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('type', 30)->default('commercial');
            // type: commercial|residential|industrial|mixed|retail|hospitality
            $table->string('currency_code', 5)->default('SAR');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 're_portfolios_org_code_uniq');
            $table->index(['organization_id', 'is_active'], 're_portfolios_org_active_idx');
        });

        // Property — physical land/site (SAP RE-FX: RE Object type Business Entity)
        Schema::create('re_properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained('re_portfolios')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('type', 30)->default('commercial'); // commercial|residential|industrial|mixed
            $table->string('street_address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->decimal('total_area_sqm', 14, 4)->default(0);
            $table->decimal('land_area_sqm', 14, 4)->nullable();
            $table->decimal('current_valuation', 18, 4)->nullable();
            $table->string('valuation_currency', 5)->default('SAR');
            $table->date('valuation_date')->nullable();
            $table->string('ownership_type', 30)->default('owned'); // owned|leased_in|managed
            $table->string('status', 20)->default('active'); // active|inactive|under_development|disposed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code'], 're_properties_org_code_uniq');
            $table->index(['organization_id', 'portfolio_id', 'status'], 're_properties_org_portfolio_status_idx');
        });

        // Building — structure on a property
        Schema::create('re_buildings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('re_properties')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->unsignedSmallInteger('floors_above_ground')->default(1);
            $table->unsignedSmallInteger('floors_below_ground')->default(0);
            $table->decimal('gross_area_sqm', 14, 4)->default(0);
            $table->decimal('net_lettable_area_sqm', 14, 4)->default(0); // NLA
            $table->year('year_built')->nullable();
            $table->string('construction_type', 50)->nullable(); // concrete|steel|wood|etc
            $table->string('status', 20)->default('active'); // active|under_renovation|demolished
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'property_id', 'code'], 're_buildings_org_property_code_uniq');
            $table->index(['organization_id', 'property_id'], 're_buildings_org_property_idx');
        });

        // Floor — floor within a building
        Schema::create('re_floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('re_buildings')->cascadeOnDelete();
            $table->smallInteger('floor_number'); // negative = basement
            $table->string('floor_label', 50)->nullable(); // "Ground Floor", "Mezzanine", "B1"
            $table->decimal('total_area_sqm', 14, 4)->default(0);
            $table->decimal('lettable_area_sqm', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['building_id', 'floor_number'], 're_floors_building_num_uniq');
        });

        // Rental unit — smallest lettable unit (office, retail, apartment, parking, storage)
        Schema::create('re_rental_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('re_buildings')->cascadeOnDelete();
            $table->foreignId('floor_id')->nullable()->constrained('re_floors')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 200)->nullable();
            $table->string('unit_type', 30)->default('office');
            // unit_type: office|retail|residential|parking|storage|warehouse|land
            $table->decimal('area_sqm', 14, 4)->default(0);
            $table->string('status', 20)->default('vacant');
            // status: vacant|occupied|reserved|under_maintenance|decommissioned
            $table->string('usage_type', 50)->nullable(); // sub-classification
            $table->unsignedTinyInteger('rooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->boolean('has_parking')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'building_id', 'code'], 're_rental_units_org_building_code_uniq');
            $table->index(['organization_id', 'status'], 're_rental_units_org_status_idx');
            $table->index(['organization_id', 'building_id', 'status'], 're_rental_units_org_building_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_rental_units');
        Schema::dropIfExists('re_floors');
        Schema::dropIfExists('re_buildings');
        Schema::dropIfExists('re_properties');
        Schema::dropIfExists('re_portfolios');
    }
};
