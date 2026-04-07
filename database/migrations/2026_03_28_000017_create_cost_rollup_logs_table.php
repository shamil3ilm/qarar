<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_rollup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('cost_version_id')->nullable()->constrained('cost_versions')->nullOnDelete();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->timestamp('run_at')->nullable();
            $table->integer('products_costed')->default(0);
            $table->integer('levels_processed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['organization_id', 'status'], 'cost_rollup_org_status_idx');
            $table->index('cost_version_id', 'cost_rollup_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_rollup_logs');
    }
};
