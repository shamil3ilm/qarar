<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_center_budget_lines', function (Blueprint $table): void {
            // period column may not exist yet — check at runtime for safety
            if (!Schema::hasColumn('cost_center_budget_lines', 'period')) {
                $table->unsignedTinyInteger('period')->nullable()->after('cost_center_budget_id')
                    ->comment('Fiscal period 1–12; NULL means the line covers the full year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cost_center_budget_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('cost_center_budget_lines', 'period')) {
                $table->dropColumn('period');
            }
        });
    }
};
