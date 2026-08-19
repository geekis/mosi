<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per `app:sync-staff` run: an audit trail of what the sync did, plus the
 * counts and payload size the circuit breaker relies on (see .ai/rules/staff-api.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_sync_runs', function (Blueprint $table) {
            $table->id();

            // running | success | failed | aborted (aborted = circuit breaker tripped).
            $table->string('status')->default('running');

            // Payload size reported by the API (totalRows) — compared run-to-run by the
            // circuit breaker to catch a partial API failure before it deactivates staff.
            $table->unsignedInteger('total_rows')->nullable();

            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('deactivated_count')->default(0);
            $table->unsignedInteger('reactivated_count')->default(0);

            $table->json('errors')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_sync_runs');
    }
};
