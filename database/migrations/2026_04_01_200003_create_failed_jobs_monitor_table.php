<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs_monitor', function (Blueprint $table): void {
            $table->id();
            $table->string('job_class', 500);
            $table->string('queue', 255)->default('default');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at');
            $table->timestamps();

            $table->index('failed_at', 'idx_failed_at');
        });

        // Prefix-length index on job_class — MySQL only (SQLite/PostgreSQL use a plain index).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX idx_job_class ON failed_jobs_monitor (job_class(191))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs_monitor');
    }
};
