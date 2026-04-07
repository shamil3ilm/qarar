<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracked_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_class', 255);
            $table->string('job_key', 100)->nullable()->comment('Business key for idempotency, e.g. payroll_period_id');
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending|running|succeeded|failed|replayed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['job_class', 'status']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index('job_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_jobs');
    }
};
