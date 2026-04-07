<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_time_days')->default(0)->after('reorder_quantity')->comment('Procurement/manufacturing lead time in days');
            $table->unsignedSmallInteger('default_supplier_lead_days')->default(0)->after('lead_time_days')->comment('Default supplier delivery lead time in days');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['lead_time_days', 'default_supplier_lead_days']);
        });
    }
};
