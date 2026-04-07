<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tenant_rate_limit_logs');
        Schema::dropIfExists('tenant_rate_limit_configs');

        Schema::create('tenant_rate_limit_configs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->unsignedSmallInteger('requests_per_minute')->default(60);
            $table->unsignedSmallInteger('requests_per_hour')->default(1000);
            $table->unsignedInteger('requests_per_day')->default(10000);
            $table->unsignedSmallInteger('burst_limit')->default(100);
            $table->unsignedSmallInteger('api_key_limit')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->json('custom_limits')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('tenant_rate_limit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->string('endpoint');
            $table->string('method', 10);
            $table->string('ip_address');
            $table->unsignedInteger('hits_in_window')->default(1);
            $table->enum('window_type', ['minute', 'hour', 'day']);
            $table->boolean('blocked')->default(false);
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_rate_limit_logs');
        Schema::dropIfExists('tenant_rate_limit_configs');
    }
};
