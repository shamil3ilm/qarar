<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('priority'); // low, medium, high, critical
            $table->unsignedInteger('first_response_hours');
            $table->unsignedInteger('resolution_hours');
            $table->boolean('business_hours_only')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['organization_id', 'priority', 'is_active']);
        });

        Schema::create('service_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('ticket_number')->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open'); // open, in_progress, pending_customer, resolved, closed, cancelled
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('type')->default('general'); // bug, feature_request, billing, technical, general
            $table->string('source')->default('manual'); // email, phone, portal, chat, manual
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('sla_policy_id')->nullable();
            $table->dateTime('first_response_due_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();
            $table->dateTime('first_response_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->tinyInteger('customer_rating')->nullable();
            $table->text('customer_feedback')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('sla_policy_id')->references('id')->on('sla_policies')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'priority']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'contact_id']);
            $table->index('sla_breached');
            $table->index('resolution_due_at');
        });

        Schema::create('service_ticket_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('service_tickets')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['ticket_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ticket_comments');
        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('sla_policies');
    }
};
