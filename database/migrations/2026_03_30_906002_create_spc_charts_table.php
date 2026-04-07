<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spc_charts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('characteristic_name', 100);
            $table->string('chart_type', 20)->default('xbar_r')
                ->comment('xbar_r, individual_mr, p_chart, c_chart');
            $table->unsignedTinyInteger('subgroup_size')->default(5);
            $table->decimal('ucl', 15, 6)->nullable()->comment('Upper Control Limit');
            $table->decimal('lcl', 15, 6)->nullable()->comment('Lower Control Limit');
            $table->decimal('center_line', 15, 6)->nullable()->comment('Process mean (X-bar-bar)');
            $table->decimal('usl', 15, 6)->nullable()->comment('Upper Specification Limit');
            $table->decimal('lsl', 15, 6)->nullable()->comment('Lower Specification Limit');
            $table->decimal('cpk', 8, 4)->nullable()->comment('Latest computed Cpk');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'product_id']);
        });

        Schema::create('spc_subgroups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('spc_chart_id')->constrained('spc_charts')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->json('measurements')->comment('Array of measured values');
            $table->decimal('subgroup_mean', 15, 6)->nullable();
            $table->decimal('subgroup_range', 15, 6)->nullable();
            $table->boolean('out_of_control')->default(false);
            $table->json('violated_rules')->nullable()->comment('List of violated Western Electric rules');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['spc_chart_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spc_subgroups');
        Schema::dropIfExists('spc_charts');
    }
};
