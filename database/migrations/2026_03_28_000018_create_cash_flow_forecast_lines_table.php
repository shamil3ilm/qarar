<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_flow_forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_flow_forecast_id')->constrained('cash_flow_forecasts')->cascadeOnDelete();
            $table->date('expected_date');
            $table->enum('line_type', ['inflow', 'outflow'])->default('inflow');
            $table->string('category', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('expected_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cash_flow_forecast_id', 'expected_date'], 'cff_lines_forecast_date_idx');
            $table->index(['cash_flow_forecast_id', 'line_type'], 'cff_lines_forecast_type_idx');
            $table->index(['source_type', 'source_id'], 'cff_lines_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_flow_forecast_lines');
    }
};
