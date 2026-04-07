<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('floc_characteristics');
        Schema::dropIfExists('floc_equipment');

        Schema::table('functional_locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('functional_locations', 'planner_group')) {
                $table->string('planner_group')->nullable()->after('description');
            }
        });

        Schema::create('floc_equipment', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('floc_id');
            $table->string('equipment_number');
            $table->string('description');
            $table->string('category')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('installed_at')->nullable();
            $table->date('removed_at')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('floc_id', 'floc_eq_floc_fk')->references('id')->on('functional_locations')->cascadeOnDelete();
            $table->foreign('product_id', 'floc_eq_prod_fk')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('floc_characteristics', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('floc_id');
            $table->string('characteristic_name');
            $table->string('characteristic_value');
            $table->string('uom', 20)->nullable();
            $table->timestamps();

            $table->foreign('floc_id', 'floc_char_floc_fk')->references('id')->on('functional_locations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floc_characteristics');
        Schema::dropIfExists('floc_equipment');
    }
};
