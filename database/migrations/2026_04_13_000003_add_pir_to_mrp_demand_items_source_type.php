<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN on enums; use a raw DB-agnostic workaround.
        // For MySQL/PostgreSQL the enum is altered; for SQLite (used in tests) we
        // recreate the column as a string with an appropriate CHECK constraint.
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: drop the existing check constraint by recreating the column.
            // Laravel's schema builder emits CHECK constraints on enum columns;
            // we switch to a plain string column (no constraint) so that 'pir' is valid.
            Schema::table('mrp_demand_items', function (Blueprint $table) {
                $table->string('source_type')->default('sales_order')->change();
            });
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE mrp_demand_items MODIFY COLUMN source_type ENUM('sales_order','forecast','safety_stock','bom','pir') NOT NULL DEFAULT 'sales_order'");
        } elseif (in_array($driver, ['pgsql', 'postgresql'])) {
            DB::statement("ALTER TABLE mrp_demand_items DROP CONSTRAINT IF EXISTS mrp_demand_items_source_type_check");
            DB::statement("ALTER TABLE mrp_demand_items ADD CONSTRAINT mrp_demand_items_source_type_check CHECK (source_type IN ('sales_order','forecast','safety_stock','bom','pir'))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('mrp_demand_items', function (Blueprint $table) {
                $table->enum('source_type', ['sales_order', 'forecast', 'safety_stock', 'bom'])->default('sales_order')->change();
            });
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE mrp_demand_items MODIFY COLUMN source_type ENUM('sales_order','forecast','safety_stock','bom') NOT NULL DEFAULT 'sales_order'");
        } elseif (in_array($driver, ['pgsql', 'postgresql'])) {
            DB::statement("ALTER TABLE mrp_demand_items DROP CONSTRAINT IF EXISTS mrp_demand_items_source_type_check");
            DB::statement("ALTER TABLE mrp_demand_items ADD CONSTRAINT mrp_demand_items_source_type_check CHECK (source_type IN ('sales_order','forecast','safety_stock','bom'))");
        }
    }
};
