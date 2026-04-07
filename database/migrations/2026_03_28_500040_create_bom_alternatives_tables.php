<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bom_alternatives');

        Schema::create('bom_alternatives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->name('ba_product_fk');
            $table->unsignedSmallInteger('alternative_number');
            $table->string('alternative_name', 100)->nullable();
            $table->foreignId('bom_template_id')->nullable()->constrained('bom_templates')->nullOnDelete()->name('ba_bom_template_fk');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('usage_type', ['production', 'engineering', 'costing', 'plant_maintenance'])->default('production');
            $table->decimal('lot_size_from', 18, 4)->nullable();
            $table->decimal('lot_size_to', 18, 4)->nullable();
            $table->enum('status', ['active', 'inactive', 'obsolete'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'product_id', 'alternative_number'], 'ba_org_prod_alt_unq');
            $table->index(['organization_id', 'product_id', 'valid_from'], 'ba_org_prod_valid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_alternatives');
    }
};
