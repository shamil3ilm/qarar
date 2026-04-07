<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_base_case')->default(false);
            $table->json('assumptions')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_base_case'], 'cash_flow_scenarios_org_base_idx');
        });

        Schema::create('cash_flow_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('forecast_date');
            $table->tinyInteger('horizon_days')->unsigned()->default(90);
            $table->string('currency_code', 3)->default('SAR');
            $table->decimal('total_opening_balance', 15, 4)->default(0);
            $table->decimal('total_inflows', 15, 4)->default(0);
            $table->decimal('total_outflows', 15, 4)->default(0);
            $table->decimal('closing_balance', 15, 4)->default(0);
            $table->foreignId('scenario_id')->nullable()->constrained('cash_flow_scenarios')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'forecast_date'], 'cash_flow_forecasts_org_date_idx');
            $table->index(['organization_id', 'scenario_id'], 'cash_flow_forecasts_org_scen_idx');
        });

        Schema::create('cash_flow_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained('cash_flow_forecasts')->cascadeOnDelete();
            $table->date('expected_date');
            $table->enum('flow_type', ['inflow', 'outflow']);
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 200);
            $table->decimal('amount', 15, 4);
            $table->enum('confidence', ['certain', 'probable', 'possible'])->default('probable');
            $table->boolean('is_actual')->default(false);
            $table->timestamps();

            $table->index(['forecast_id', 'expected_date'], 'cash_flow_lines_forecast_date_idx');
            $table->index(['forecast_id', 'flow_type'], 'cash_flow_lines_forecast_type_idx');
            $table->index(['source_type', 'source_id'], 'cash_flow_lines_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_lines');
        Schema::dropIfExists('cash_flow_forecasts');
        Schema::dropIfExists('cash_flow_scenarios');
    }
};
