<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bulk_operation_jobs');

        Schema::create('bulk_operation_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('operation_type');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->json('payload');
            $table->json('result_summary')->nullable();
            $table->json('error_log')->nullable();
            $table->unsignedBigInteger('initiated_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('initiated_by', 'bulk_op_usr_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_operation_jobs');
    }
};
