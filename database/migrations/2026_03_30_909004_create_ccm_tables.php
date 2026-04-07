<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grc_ccm_monitors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('monitor_code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('control_type', ['preventive', 'detective', 'corrective']);
            $table->string('data_source', 100);
            $table->json('rules');
            $table->enum('frequency', ['real_time', 'hourly', 'daily', 'weekly', 'monthly'])->default('daily');
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('total_exceptions')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'monitor_code']);
        });

        Schema::create('grc_ccm_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained('grc_ccm_monitors')->restrictOnDelete();
            $table->string('record_type', 100);
            $table->unsignedBigInteger('record_id');
            $table->string('record_reference', 100)->nullable();
            $table->text('exception_details');
            $table->enum('severity', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'investigated', 'resolved', 'false_positive'])->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'monitor_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grc_ccm_exceptions');
        Schema::dropIfExists('grc_ccm_monitors');
    }
};
