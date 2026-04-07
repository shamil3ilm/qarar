<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dps_sanction_lists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('list_name', 100);
            $table->string('list_authority', 50); // OFAC|EU|UN|HMT|local|other
            $table->string('list_type', 20); // denied_party|embargo|debarred
            $table->dateTime('last_updated_at')->nullable();
            $table->integer('entry_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync')->default(false);
            $table->string('sync_url', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dps_list_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dps_sanction_list_id')
                ->constrained('dps_sanction_lists')
                ->cascadeOnDelete();
            $table->string('entry_type', 20); // person|entity|vessel|aircraft
            $table->string('name', 200);
            $table->json('aliases')->nullable();
            $table->string('country_code', 3)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('id_number', 100)->nullable();
            $table->string('program', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['dps_sanction_list_id', 'is_active'], 'dps_entries_list_active_idx');
            $table->index('name', 'dps_entries_name_idx');
            $table->index('country_code', 'dps_entries_country_idx');
        });

        Schema::create('dps_screening_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('screened_entity_type', 20); // contact|vendor|customer
            $table->unsignedBigInteger('screened_entity_id');
            $table->dateTime('screening_date');
            $table->decimal('match_threshold', 5, 2)->default(80);
            $table->string('status', 20)->default('clean'); // clean|potential_match|confirmed_match|cleared
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cleared_at')->nullable();
            $table->text('clearance_notes')->nullable();
            $table->string('triggered_by', 50); // manual|auto_transaction|batch
            $table->timestamps();

            $table->index(['screened_entity_type', 'screened_entity_id'], 'dps_runs_entity_idx');
            $table->index(['status', 'screening_date'], 'dps_runs_status_date_idx');
        });

        Schema::create('dps_screening_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dps_screening_run_id')
                ->constrained('dps_screening_runs')
                ->cascadeOnDelete();
            $table->foreignId('dps_list_entry_id')
                ->constrained('dps_list_entries')
                ->cascadeOnDelete();
            $table->decimal('match_score', 5, 2);
            $table->string('matched_field', 30); // name|alias|id_number|address
            $table->boolean('is_false_positive')->default(false);
            $table->timestamps();

            $table->index('dps_screening_run_id', 'dps_results_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dps_screening_results');
        Schema::dropIfExists('dps_screening_runs');
        Schema::dropIfExists('dps_list_entries');
        Schema::dropIfExists('dps_sanction_lists');
    }
};
