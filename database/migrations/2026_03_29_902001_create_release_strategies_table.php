<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_strategies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('document_type', ['purchase_order', 'purchase_requisition']);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'document_type', 'is_active']);
        });

        Schema::create('release_strategy_levels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('release_strategy_id')->constrained('release_strategies')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('role', 100);
            $table->decimal('min_amount', 15, 4)->nullable();
            $table->decimal('max_amount', 15, 4)->nullable();
            $table->string('label', 100);
            $table->timestamps();

            $table->unique(['release_strategy_id', 'level']);
            $table->index(['organization_id', 'release_strategy_id'], 'release_strategy_levels_org_strategy_idx');
        });

        Schema::create('release_strategy_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('release_strategy_id')->constrained('release_strategies')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('release_strategy_levels')->cascadeOnDelete();
            $table->enum('document_type', ['purchase_order', 'purchase_requisition']);
            $table->unsignedBigInteger('document_id');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comments')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'document_type', 'document_id'], 'release_strategy_approvals_org_doc_type_doc_id_idx');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_strategy_approvals');
        Schema::dropIfExists('release_strategy_levels');
        Schema::dropIfExists('release_strategies');
    }
};
