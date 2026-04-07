<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('complaint_resolutions');
        Schema::dropIfExists('complaint_communications');
        Schema::dropIfExists('complaints');

        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->string('complaint_number', 50)->unique();
            $table->enum('complaint_source', ['customer', 'internal', 'regulatory', 'supplier'])->default('customer');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->foreign('contact_id', 'complaint_contact_fk')->references('id')->on('contacts')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed', 'withdrawn'])->default('open');
            $table->unsignedBigInteger('assigned_to_id')->nullable();
            $table->foreign('assigned_to_id', 'complaint_assignee_fk')->references('id')->on('users')->nullOnDelete();
            $table->date('received_date');
            $table->date('target_resolution_date')->nullable();
            $table->date('actual_resolution_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('complaint_communications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('complaint_id');
            $table->foreign('complaint_id', 'complaint_comm_fk')->references('id')->on('complaints')->cascadeOnDelete();
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->enum('channel', ['email', 'phone', 'letter', 'portal', 'in_person'])->default('email');
            $table->text('content');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'complaint_comm_user_fk')->references('id')->on('users');
            $table->timestamp('communicated_at');
            $table->timestamps();
        });

        Schema::create('complaint_resolutions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('complaint_id');
            $table->foreign('complaint_id', 'complaint_res_fk')->references('id')->on('complaints')->cascadeOnDelete();
            $table->enum('resolution_type', ['replacement', 'refund', 'credit', 'repair', 'explanation', 'apology', 'other'])->default('other');
            $table->text('resolution_description');
            $table->boolean('customer_accepted')->default(false);
            $table->date('resolution_date');
            $table->unsignedBigInteger('resolved_by_id');
            $table->foreign('resolved_by_id', 'complaint_res_user_fk')->references('id')->on('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_resolutions');
        Schema::dropIfExists('complaint_communications');
        Schema::dropIfExists('complaints');
    }
};
