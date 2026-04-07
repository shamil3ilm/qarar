<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_center_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('profit_center_id')->constrained('profit_centers')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('period');    // 1–12
            $table->decimal('plan_revenue', 15, 4)->default(0);
            $table->decimal('plan_cost', 15, 4)->default(0);
            $table->decimal('plan_profit', 15, 4)->default(0);
            $table->char('currency_code', 3)->default('SAR');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'profit_center_id', 'fiscal_year', 'period'],
                'pcp_org_pc_year_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_center_plans');
    }
};
