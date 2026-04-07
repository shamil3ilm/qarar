<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_kpis', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('equipment_id')->nullable()->comment('NULL = org-level aggregate');
            $table->string('period_year', 4);
            $table->string('period_month', 2);
            $table->decimal('mtbf_hours', 10, 2)->default(0)->comment('Mean Time Between Failures');
            $table->decimal('mttr_hours', 10, 2)->default(0)->comment('Mean Time To Repair');
            $table->decimal('availability_pct', 5, 2)->default(0)->comment('(MTBF / (MTBF + MTTR)) * 100');
            $table->decimal('oee_pct', 5, 2)->default(0)->comment('Overall Equipment Effectiveness');
            $table->integer('breakdown_count')->default(0);
            $table->decimal('total_downtime_hours', 10, 2)->default(0);
            $table->decimal('planned_maintenance_hours', 10, 2)->default(0);
            $table->decimal('unplanned_maintenance_hours', 10, 2)->default(0);
            $table->decimal('maintenance_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'equipment_id', 'period_year', 'period_month'], 'maintenance_kpis_org_equipment_period_yr_mo_uniq');
            $table->index(['organization_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_kpis');
    }
};
