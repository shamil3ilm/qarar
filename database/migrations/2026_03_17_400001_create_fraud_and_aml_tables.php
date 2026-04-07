<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable fraud detection rules per organization
        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rule_type');        // velocity, amount, geographic, behavioral, pattern
            $table->string('entity_type');      // invoice, payment, login, contact
            $table->json('conditions');         // rule-specific condition parameters
            $table->string('severity');         // low, medium, high, critical
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_block')->default(false); // block transaction automatically
            $table->unsignedInteger('score_impact')->default(10); // fraud score contribution
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active', 'rule_type']);
        });

        // Fraud alerts generated when rules fire
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fraud_rule_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');      // invoice, payment, login, contact
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_uuid')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable();
            $table->string('severity');
            $table->string('status')->default('open'); // open, reviewing, resolved, false_positive
            $table->unsignedInteger('fraud_score')->default(0);
            $table->json('evidence');           // context data captured at alert time
            $table->string('ip_address', 45)->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'status', 'severity']);
            $table->index(['entity_type', 'entity_id']);
        });

        // AML risk scores per contact/customer (updated periodically and on data changes)
        Schema::create('aml_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');        // 0-100 composite risk score
            $table->string('risk_level');                // low (0-30), medium (31-60), high (61-80), critical (81-100)
            $table->json('score_breakdown');             // per-dimension contributions
            $table->boolean('sanctions_hit')->default(false);
            $table->boolean('pep_hit')->default(false);
            $table->string('sanctions_details')->nullable();
            $table->timestamp('last_screened_at')->nullable();
            $table->timestamp('score_updated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['organization_id', 'contact_id']);
            $table->index(['organization_id', 'risk_level']);
        });

        // Suspicious Activity Reports (SAR) — regulatory compliance
        Schema::create('aml_suspicious_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('report_type');          // SAR, CTR (currency transaction report), STR
            $table->string('status')->default('draft'); // draft, filed, closed
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('contact_name')->nullable(); // denormalized for reports
            $table->json('related_transaction_ids')->nullable();
            $table->text('description');
            $table->string('activity_type');        // structuring, smurfing, layering, unusual_pattern, sanctions_hit
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->date('activity_date_from')->nullable();
            $table->date('activity_date_to')->nullable();
            $table->text('narrative')->nullable();   // full narrative for filing
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('filed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        // AML transaction monitoring — flags on individual transactions
        Schema::create('aml_transaction_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type');     // invoice, payment, journal_entry
            $table->unsignedBigInteger('transaction_id');
            $table->string('transaction_number')->nullable();
            $table->decimal('amount', 20, 4);
            $table->string('currency', 3);
            $table->string('flag_reason');          // large_cash, structuring, rapid_movement, threshold_breach, unusual_pattern
            $table->string('status')->default('flagged'); // flagged, cleared, escalated
            $table->unsignedInteger('aml_score')->default(0);
            $table->json('context');                // supporting data
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamp('transaction_date');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'status']);
            $table->index(['transaction_type', 'transaction_id']);
        });

        // Customer Due Diligence records
        Schema::create('aml_cdd_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('cdd_level');            // standard, enhanced, simplified
            $table->string('status');               // pending, completed, expired, failed
            $table->json('verification_data')->nullable();
            $table->date('verified_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'contact_id']);
        });

        // Cached sanctions/PEP screening results to avoid re-checking unchanged contacts
        Schema::create('aml_screening_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('list_type');            // ofac, eu, un, pep, local
            $table->boolean('is_match')->default(false);
            $table->json('match_details')->nullable();
            $table->string('data_hash');            // hash of contact fields screened — skip if unchanged
            $table->timestamp('screened_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['contact_id', 'list_type']);
            $table->index(['organization_id', 'is_match']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aml_screening_cache');
        Schema::dropIfExists('aml_cdd_records');
        Schema::dropIfExists('aml_transaction_flags');
        Schema::dropIfExists('aml_suspicious_activities');
        Schema::dropIfExists('aml_risk_scores');
        Schema::dropIfExists('fraud_alerts');
        Schema::dropIfExists('fraud_rules');
    }
};
