<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RE-FX Vacancy Management tables.
 *
 * vacancy_periods  — tracks each vacant interval per unit (SAP RE-FX vacancy period)
 * re_occupancy_snapshots — daily/monthly occupancy rate snapshot per building/portfolio
 */
return new class extends Migration
{
    public function up(): void
    {
        // Vacancy periods: start when a unit becomes vacant, end when it is leased again
        Schema::create('re_vacancy_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('rental_unit_id');
            $table->unsignedBigInteger('building_id');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('portfolio_id')->nullable();
            $table->date('vacant_from');
            $table->date('vacant_to')->nullable();                  // null = still vacant
            $table->string('vacancy_reason')->nullable();           // lease_expired|early_termination|new_unit|renovation|owner_use
            $table->decimal('market_rent', 15, 2)->nullable();     // expected rent while vacant (for revenue loss calc)
            $table->string('currency', 3)->default('SAR');
            $table->decimal('vacancy_loss', 15, 2)->nullable();    // computed: days_vacant × daily_market_rent
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rental_unit_id']);
            $table->index(['organization_id', 'building_id']);
            $table->index(['vacant_from', 'vacant_to']);
        });

        // Periodic occupancy snapshots per building (for dashboards and trend analysis)
        Schema::create('re_occupancy_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('snapshot_type');                        // building|property|portfolio
            $table->unsignedBigInteger('reference_id');             // building_id|property_id|portfolio_id
            $table->date('snapshot_date');
            $table->unsignedInteger('total_units');
            $table->unsignedInteger('occupied_units');
            $table->unsignedInteger('vacant_units');
            $table->decimal('occupancy_rate', 5, 2);               // percentage 0–100
            $table->decimal('total_area_sqm', 15, 4)->default(0);
            $table->decimal('occupied_area_sqm', 15, 4)->default(0);
            $table->decimal('area_occupancy_rate', 5, 2)->default(0);
            $table->decimal('potential_rent', 15, 2)->default(0);  // sum of market rent of all units
            $table->decimal('actual_rent', 15, 2)->default(0);     // sum of contract rent for occupied units
            $table->string('currency', 3)->default('SAR');
            $table->timestamps();

            $table->unique(['organization_id', 'snapshot_type', 'reference_id', 'snapshot_date'], 're_occ_snap_org_type_ref_date_unique');
            $table->index(['organization_id', 'snapshot_type', 'reference_id'], 're_occ_snap_org_type_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('re_occupancy_snapshots');
        Schema::dropIfExists('re_vacancy_periods');
    }
};
