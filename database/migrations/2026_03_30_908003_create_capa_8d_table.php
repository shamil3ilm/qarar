<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qm_capa_8d', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('capa_number', 30)->unique();
            $table->string('title', 200);

            // D0 — Emergency response
            $table->text('d0_emergency_response')->nullable();
            $table->date('d0_date')->nullable();

            // D1 — Team
            $table->json('d1_team_members')->nullable();
            $table->foreignId('d1_champion_id')->nullable()->constrained('users')->nullOnDelete();

            // D2 — Problem description
            $table->text('d2_problem_description')->nullable();
            $table->text('d2_is_is_not')->nullable();

            // D3 — Containment actions
            $table->text('d3_containment_actions')->nullable();
            $table->date('d3_implemented_date')->nullable();
            $table->boolean('d3_verified')->default(false);

            // D4 — Root cause
            $table->text('d4_root_cause')->nullable();
            $table->text('d4_escape_point')->nullable();

            // D5 — Corrective actions chosen
            $table->text('d5_corrective_actions')->nullable();

            // D6 — Corrective action implementation
            $table->text('d6_implementation_plan')->nullable();
            $table->date('d6_target_date')->nullable();
            $table->date('d6_completed_date')->nullable();
            $table->boolean('d6_verified')->default(false);

            // D7 — Prevent recurrence
            $table->text('d7_systemic_preventions')->nullable();
            $table->text('d7_lessons_learned')->nullable();

            // D8 — Recognise team
            $table->text('d8_recognition')->nullable();
            $table->date('d8_closure_date')->nullable();

            $table->enum('status', [
                'd0_open',
                'd1_team',
                'd2_problem',
                'd3_containment',
                'd4_root_cause',
                'd5_actions',
                'd6_implemented',
                'd7_prevention',
                'd8_closed',
            ])->default('d0_open');

            // Source linkages
            $table->unsignedBigInteger('source_complaint_id')->nullable();
            $table->string('source_type', 50)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qm_capa_8d');
    }
};
