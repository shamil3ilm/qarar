<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_readiness_checks', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('check_key', 100);
            $table->string('check_name', 200);
            $table->text('description')->nullable();
            $table->enum('severity', ['error', 'warning', 'info'])->default('error');
            $table->boolean('is_active')->default(true);
            $table->tinyInteger('order')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['module', 'check_key'], 'mrc_module_check_unique');
        });

        Schema::create('module_readiness_results', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('module', 50);
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('run_at');
            $table->enum('overall_status', ['pass', 'fail', 'warning'])->default('pass');
            $table->json('results');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'module'], 'mrr_org_module_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_readiness_results');
        Schema::dropIfExists('module_readiness_checks');
    }
};
