<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('production_versions');

        Schema::create('production_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version_code', 20);
            $table->string('description')->nullable();
            $table->foreignId('bom_id')
                ->nullable()
                ->constrained('bom_templates')
                ->nullOnDelete();
            $table->foreignId('routing_id')
                ->nullable()
                ->constrained('routing_headers')
                ->nullOnDelete();
            $table->decimal('lot_size_from', 18, 4)->default(0);
            $table->decimal('lot_size_to', 18, 4)->nullable();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('production_plant', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['organization_id', 'product_id', 'version_code'],
                'pv_org_product_code_unique'
            );
            $table->index(['product_id', 'is_active'], 'pv_product_active_idx');
            $table->index(['product_id', 'is_default'], 'pv_product_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_versions');
    }
};
