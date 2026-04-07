<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gosi_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('country_code', 10)->default('SA');
            $table->string('name', 100)->nullable();
            $table->decimal('employee_contribution_pct', 6, 2)->default(0);
            $table->decimal('employer_contribution_pct', 6, 2)->default(0);
            $table->decimal('hazard_pct', 6, 2)->default(0);
            $table->decimal('salary_ceiling', 15, 2)->nullable();
            $table->decimal('salary_floor', 15, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_active'], 'gosi_conf_org_active_idx');
            $table->index(['organization_id', 'country_code'], 'gosi_conf_org_country_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gosi_configurations');
    }
};
