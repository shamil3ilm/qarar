<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_units', function (Blueprint $table): void {
            if (! Schema::hasColumn('org_units', 'org_unit_code')) {
                $table->string('org_unit_code', 20)->nullable()->after('name');
            }

            if (! Schema::hasColumn('org_units', 'org_unit_type')) {
                $table->string('org_unit_type', 50)->nullable()->after('unit_type');
            }

            if (! Schema::hasColumn('org_units', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('valid_to');
            }

            if (! Schema::hasColumn('org_units', 'head_count_plan')) {
                $table->unsignedSmallInteger('head_count_plan')->default(0)->after('is_active');
            }

            if (! Schema::hasColumn('org_units', 'manager_position_id')) {
                $table->unsignedBigInteger('manager_position_id')->nullable()->after('manager_id');
            }

            if (! Schema::hasColumn('org_units', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('head_count_plan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table): void {
            $table->dropColumnIfExists('org_unit_code');
            $table->dropColumnIfExists('org_unit_type');
            $table->dropColumnIfExists('is_active');
            $table->dropColumnIfExists('head_count_plan');
            $table->dropColumnIfExists('manager_position_id');
            $table->dropColumnIfExists('created_by');
        });
    }
};
