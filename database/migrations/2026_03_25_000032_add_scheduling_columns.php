<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_operations', function (Blueprint $table) {
            $table->dateTime('scheduled_start')->nullable()->after('status');
            $table->dateTime('scheduled_end')->nullable()->after('scheduled_start');
            $table->unsignedBigInteger('work_center_id')->nullable()->after('scheduled_end');
            $table->foreign('work_center_id')->references('id')->on('work_centers')->nullOnDelete();
        });

        Schema::table('routing_operations', function (Blueprint $table) {
            $table->decimal('inter_operation_time', 8, 2)->default(0)->after('labor_time')
                ->comment('Buffer/transit hours between operations');
        });
    }

    public function down(): void
    {
        Schema::table('routing_operations', function (Blueprint $table) {
            $table->dropColumn('inter_operation_time');
        });

        Schema::table('work_order_operations', function (Blueprint $table) {
            $table->dropForeign(['work_center_id']);
            $table->dropColumn(['scheduled_start', 'scheduled_end', 'work_center_id']);
        });
    }
};
