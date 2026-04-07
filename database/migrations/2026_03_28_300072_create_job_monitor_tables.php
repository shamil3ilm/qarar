<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_monitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->foreign('organization_id', 'jm_org_id_fk')
                ->references('id')->on('organizations')->onDelete('set null');
            $table->string('job_class', 200);
            $table->string('job_name', 200);
            $table->string('queue_name', 50)->default('default');
            $table->string('status', 20)->default('queued')
                ->comment('queued/running/completed/failed/retrying');
            $table->json('payload')->nullable();
            $table->text('output')->nullable();
            $table->text('error_message')->nullable();
            $table->tinyInteger('attempts')->default(0);
            $table->tinyInteger('max_attempts')->default(3);
            $table->tinyInteger('progress_percentage')->default(0);
            $table->string('progress_message', 200)->nullable();
            $table->dateTime('queued_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->integer('run_duration_seconds')->nullable();
            $table->string('triggered_by', 50)->default('manual')
                ->comment('manual/scheduled/event/system');
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->foreign('triggered_by_user_id', 'jm_triggered_by_user_fk')
                ->references('id')->on('users')->onDelete('set null');
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['status', 'queued_at'], 'jm_status_queued_idx');
            $table->index(['job_class', 'status'], 'jm_class_status_idx');
            $table->index(['organization_id', 'status'], 'jm_org_status_idx');
            $table->index(['queue_name', 'status'], 'jm_queue_status_idx');
        });

        Schema::create('job_monitor_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_monitor_id');
            $table->foreign('job_monitor_id', 'jml_monitor_id_fk')
                ->references('id')->on('job_monitors')->onDelete('cascade');
            $table->string('level', 10)->comment('info/warning/error/debug');
            $table->text('message');
            $table->json('context')->nullable();
            $table->dateTime('created_at');

            $table->index(['job_monitor_id', 'level'], 'jml_monitor_level_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_monitor_logs');
        Schema::dropIfExists('job_monitors');
    }
};
