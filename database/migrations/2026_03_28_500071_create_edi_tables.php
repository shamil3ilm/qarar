<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('edi_message_segments');
        Schema::dropIfExists('edi_messages');
        Schema::dropIfExists('edi_partners');

        Schema::create('edi_partners', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('partner_code', 50);
            $table->string('partner_name', 100);
            $table->enum('partner_type', ['vendor', 'customer', 'bank', 'carrier', 'other'])->default('vendor');
            $table->enum('edi_standard', ['edifact', 'x12', 'ubl', 'idoc', 'custom'])->default('edifact');
            $table->boolean('is_active')->default(true);
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->name('ep_contact_fk');
            $table->string('interchange_id', 50)->nullable();
            $table->string('interchange_qualifier', 10)->nullable();
            $table->boolean('test_mode')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'partner_code'], 'ep_org_code_unq');
        });

        Schema::create('edi_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->name('em_org_fk');
            $table->foreignId('edi_partner_id')->constrained('edi_partners')->name('em_partner_fk');
            $table->string('message_type', 50);
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->enum('status', [
                'received',
                'processing',
                'processed',
                'failed',
                'sent',
                'acknowledged',
            ])->default('received');
            $table->string('control_number', 50)->nullable();
            $table->string('functional_acknowledgment', 50)->nullable();
            $table->longText('raw_content')->nullable();
            $table->json('parsed_content')->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'direction', 'status'], 'em_org_dir_status_idx');
            $table->index(['edi_partner_id'], 'em_partner_idx');
            $table->index(['message_type', 'direction'], 'em_type_dir_idx');
        });

        Schema::create('edi_message_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->name('ems_org_fk');
            $table->foreignId('edi_message_id')->constrained('edi_messages')->name('ems_message_fk');
            $table->string('segment_id', 10);
            $table->unsignedSmallInteger('segment_sequence');
            $table->json('segment_data');
            $table->timestamps();

            $table->index(['edi_message_id'], 'ems_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_message_segments');
        Schema::dropIfExists('edi_messages');
        Schema::dropIfExists('edi_partners');
    }
};
