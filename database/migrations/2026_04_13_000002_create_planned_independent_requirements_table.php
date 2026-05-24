<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_independent_requirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // PIR version — allows multiple planning versions (SAP concept: active version vs. simulation)
            $table->unsignedTinyInteger('version')->default(1);
            $table->boolean('is_active')->default(true);

            // Requirement quantity and date
            $table->decimal('quantity', 12, 4);
            $table->date('requirement_date');

            // Optional planning horizon / validity
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // How much has already been consumed by confirmed sales orders (backflush)
            $table->decimal('consumed_quantity', 12, 4)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'is_active'], 'pir_org_prod_active_idx');
            $table->index(['organization_id', 'requirement_date'], 'pir_org_req_date_idx');
            $table->index(['organization_id', 'version', 'is_active'], 'pir_org_ver_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_independent_requirements');
    }
};
