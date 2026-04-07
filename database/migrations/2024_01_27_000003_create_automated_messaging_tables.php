<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Messaging channels configuration
        Schema::create('messaging_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('name');
            $table->string('provider', 50); // smtp, sendgrid, twilio, vonage, firebase, whatsapp_business
            $table->json('credentials'); // Encrypted API keys/config
            $table->json('settings')->nullable(); // Rate limits, sender defaults, etc.
            $table->string('sender_name')->nullable();
            $table->string('sender_address')->nullable(); // Email, phone number
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'channel_type', 'is_default'], 'msg_channels_org_type_default_unique');
            $table->index(['organization_id', 'is_active']);
        });

        // Message templates
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50); // invoice_sent, payment_received, order_confirmed
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->string('category', 50); // transactional, promotional, reminder, notification

            // Content
            $table->string('subject')->nullable(); // For email
            $table->text('body'); // Supports {{variable}} placeholders
            $table->text('html_body')->nullable(); // Rich HTML for email
            $table->json('variables')->nullable(); // Available placeholders
            $table->json('attachments_config')->nullable(); // Auto-attach invoice PDF, etc.

            // Language
            $table->string('language', 5)->default('en');
            $table->foreignId('parent_template_id')->nullable()->constrained('message_templates')->nullOnDelete();

            $table->boolean('is_system')->default(false); // System template, cannot be deleted
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code', 'channel_type', 'language'], 'msg_tpl_org_code_channel_lang_unique');
        });

        // Automated messaging rules (triggers)
        Schema::create('messaging_automations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // Trigger
            $table->string('trigger_event', 100); // invoice.created, payment.received, order.shipped, customer.birthday, invoice.overdue
            $table->string('trigger_entity', 50)->nullable(); // invoice, payment, order, customer

            // Timing
            $table->string('timing', 20)->default('immediate'); // immediate, delayed, scheduled
            $table->unsignedInteger('delay_minutes')->default(0); // For delayed triggers
            $table->string('delay_unit', 10)->nullable(); // minutes, hours, days

            // Conditions
            $table->json('conditions')->nullable(); // Filter conditions (amount > 500, status = overdue, etc.)

            // Action
            $table->string('channel_type', 30); // email, sms, whatsapp, push_notification
            $table->foreignId('template_id')->constrained('message_templates')->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('messaging_channels')->nullOnDelete();

            // Recipients
            $table->string('recipient_type', 30)->default('contact'); // contact, user, custom, role
            $table->json('recipient_config')->nullable(); // CC, BCC, additional recipients

            // Rate limiting
            $table->unsignedInteger('max_sends_per_contact')->nullable(); // Per day/week/month
            $table->string('rate_limit_period', 10)->nullable(); // day, week, month

            // Status
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'trigger_event', 'is_active'], 'msg_auto_org_trigger_active_idx');
        });

        // Outbound message log
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_id')->nullable()->constrained('messaging_automations')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('messaging_channels')->nullOnDelete();

            // Channel info
            $table->string('channel_type', 30);
            $table->string('sender', 255)->nullable();
            $table->string('recipient', 255);
            $table->string('recipient_name')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Content
            $table->string('subject')->nullable();
            $table->text('body');
            $table->text('html_body')->nullable();

            // Context
            $table->string('entity_type', 100)->nullable(); // invoice, payment, order
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('category', 50); // transactional, promotional, reminder

            // Status
            $table->string('status', 20)->default('queued'); // queued, sending, sent, delivered, failed, bounced, opened, clicked
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();

            // Cost tracking
            $table->decimal('cost', 10, 4)->nullable(); // SMS/WhatsApp message cost
            $table->string('cost_currency', 3)->nullable();

            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'channel_type', 'created_at']);
            $table->index(['organization_id', 'status']);
            $table->index(['contact_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'next_retry_at']);
        });

        // Message attachments
        Schema::create('outbound_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('outbound_messages')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->unsignedInteger('file_size');
            $table->boolean('is_inline')->default(false); // Inline image in HTML email
            $table->timestamps();

            $table->index(['message_id']);
        });

        // Customer communication preferences
        Schema::create('contact_messaging_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('marketing_enabled')->default(true);
            $table->boolean('transactional_enabled')->default(true);
            $table->boolean('reminder_enabled')->default(true);
            $table->string('preferred_channel', 30)->default('email');
            $table->string('preferred_language', 5)->default('en');
            $table->string('timezone')->nullable();
            $table->json('quiet_hours')->nullable(); // {"start": "22:00", "end": "08:00"}
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id']);
        });

        // SMS/WhatsApp template approval status (for regulated channels)
        Schema::create('channel_template_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('message_templates')->cascadeOnDelete();
            $table->string('channel_type', 30);
            $table->string('provider_template_id')->nullable(); // WhatsApp business template ID
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'channel_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_template_approvals');
        Schema::dropIfExists('contact_messaging_preferences');
        Schema::dropIfExists('outbound_message_attachments');
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('messaging_automations');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('messaging_channels');
    }
};
