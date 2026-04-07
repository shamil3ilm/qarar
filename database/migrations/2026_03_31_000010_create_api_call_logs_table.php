<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_call_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('service', 100);           // 'CompliPayClient', 'ZatcaClient', etc.
            $table->string('method', 10);             // GET, POST, etc.
            $table->string('url', 2048);
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->string('status', 20)->default('success'); // success, error, timeout
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'service', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_call_logs');
    }
};
