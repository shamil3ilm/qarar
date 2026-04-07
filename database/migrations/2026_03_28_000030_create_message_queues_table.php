<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('provider', 50)->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('queued'); // queued, processing, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->index('outbound_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_queues');
    }
};
