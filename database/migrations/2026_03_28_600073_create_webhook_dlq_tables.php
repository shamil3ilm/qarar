<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('webhook_dlq_entries');

        Schema::create('webhook_dlq_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('webhook_id');
            $table->string('event_type');
            $table->json('payload');
            $table->unsignedTinyInteger('failure_count')->default(1);
            $table->timestamp('first_failed_at');
            $table->timestamp('last_failed_at');
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->enum('status', ['pending', 'retrying', 'dead', 'replayed'])->default('pending');
            $table->timestamp('replayed_at')->nullable();
            $table->unsignedBigInteger('replayed_by')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('webhook_id', 'wh_dlq_wh_fk')->references('id')->on('webhooks')->cascadeOnDelete();
            $table->foreign('replayed_by', 'wh_dlq_usr_fk')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_dlq_entries');
    }
};
