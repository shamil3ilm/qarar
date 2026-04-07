<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('territories')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->enum('territory_type', [
                'global', 'region', 'country', 'state', 'city', 'postal_zone', 'custom',
            ])->default('custom');
            $table->string('country_code', 3)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->json('postal_codes')->nullable()->comment('Array of postal code patterns');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
        });

        Schema::create('territory_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('role', ['owner', 'backup', 'viewer'])->default('owner');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('territory_id');
            $table->index('employee_id');
        });

        Schema::create('territory_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('territory_id')->constrained('territories')->cascadeOnDelete();
            $table->enum('entity_type', ['lead', 'opportunity', 'contact'])->default('lead');
            $table->enum('match_field', ['country', 'state', 'postal_code', 'city', 'custom'])->default('country');
            $table->string('match_value');
            $table->tinyInteger('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territory_routing_rules');
        Schema::dropIfExists('territory_assignments');
        Schema::dropIfExists('territories');
    }
};
