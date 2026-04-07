<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // QM Dynamic Modification Rules (SAP QP27)
        Schema::create('qm_dynamic_modification_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('rule_code', 20);
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Tightened inspection trigger
            $table->unsignedSmallInteger('tighten_consecutive_fails')->default(2);
            // Reduced inspection trigger
            $table->unsignedSmallInteger('reduce_after_consecutive_pass')->default(5);
            // Skip inspection trigger
            $table->unsignedSmallInteger('skip_after_reduced_pass')->default(10);
            // Reinstate reduced→normal after consecutive passes while tightened
            $table->unsignedSmallInteger('reinstate_after_tightened_fail')->default(5);

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'rule_code']);
        });

        // Tracks current inspection stage per material / vendor / inspection type
        Schema::create('qm_inspection_stage_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('rule_id')
                ->constrained('qm_dynamic_modification_rules')
                ->restrictOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->enum('current_stage', ['tightened', 'normal', 'reduced', 'skip'])->default('normal');
            $table->unsignedSmallInteger('consecutive_pass')->default(0);
            $table->unsignedSmallInteger('consecutive_fail')->default(0);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'product_id', 'supplier_id'], 'qm_inspection_stage_log_org_product_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qm_inspection_stage_log');
        Schema::dropIfExists('qm_dynamic_modification_rules');
    }
};
