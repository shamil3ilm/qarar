<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('version_code', 20);
            $table->string('description', 200)->nullable();
            $table->enum('costing_type', ['standard', 'actual', 'planned'])->default('standard');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'version_code'], 'cost_versions_org_code_unique');
            $table->index(['organization_id', 'is_active'], 'cost_versions_org_active_idx');
            $table->index(['organization_id', 'costing_type'], 'cost_versions_org_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_versions');
    }
};
