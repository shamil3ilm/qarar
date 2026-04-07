<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Freight tender requests (SAP TM: Freight Order Tendering)
        Schema::create('tm_freight_tender_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('tender_number', 50);
            $table->string('title', 200);
            $table->string('origin_country', 5)->nullable();
            $table->string('origin_zone', 100)->nullable();
            $table->string('destination_country', 5)->nullable();
            $table->string('destination_zone', 100)->nullable();
            $table->string('transport_mode', 30)->default('road');
            $table->decimal('total_weight', 14, 3)->default(0);
            $table->decimal('total_volume', 14, 4)->default(0);
            $table->unsignedInteger('shipment_count')->default(1);
            $table->boolean('has_dangerous_goods')->default(false);
            $table->boolean('requires_refrigeration')->default(false);
            $table->date('required_by_date')->nullable();
            $table->dateTime('bid_deadline')->nullable();
            $table->string('status', 20)->default('draft');
            // status: draft|open|evaluating|awarded|cancelled
            $table->foreignId('awarded_carrier_id')->nullable()->constrained('tm_carriers')->nullOnDelete();
            $table->foreignId('awarded_bid_id')->nullable(); // set after award
            $table->dateTime('awarded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'tender_number'], 'tm_freight_tender_requests_org_num_uniq');
            $table->index(['organization_id', 'status', 'bid_deadline'], 'tm_freight_tender_requests_org_status_deadline_idx');
        });

        // Cargo items within a tender request
        Schema::create('tm_freight_tender_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_request_id')->constrained('tm_freight_tender_requests')->cascadeOnDelete();
            $table->string('description', 200);
            $table->decimal('weight', 14, 3)->default(0);
            $table->decimal('volume', 14, 4)->default(0);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit_of_measure', 20)->default('pcs');
            $table->string('cargo_type', 50)->nullable(); // general|dg|refrigerated|hazmat|bulk
            $table->boolean('is_dangerous_goods')->default(false);
            $table->string('un_number', 10)->nullable();
            $table->timestamps();
        });

        // Carrier bids on tender requests
        Schema::create('tm_freight_tender_bids', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tender_request_id')->constrained('tm_freight_tender_requests')->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('tm_carriers')->cascadeOnDelete();
            $table->decimal('total_price', 14, 4);
            $table->string('currency_code', 5)->default('USD');
            $table->unsignedSmallInteger('transit_days');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('submitted');
            // status: submitted|under_review|awarded|rejected|withdrawn
            $table->dateTime('submitted_at');
            $table->dateTime('evaluated_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('breakdown')->nullable(); // itemized cost breakdown
            $table->timestamps();

            $table->unique(['tender_request_id', 'carrier_id'], 'tm_freight_tender_bids_request_carrier_uniq');
            $table->index(['tender_request_id', 'status'], 'tm_freight_tender_bids_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_freight_tender_bids');
        Schema::dropIfExists('tm_freight_tender_items');
        Schema::dropIfExists('tm_freight_tender_requests');
    }
};
