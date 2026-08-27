<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whereabouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The day this registration is for. Stored as a bare date — never a formatted
            // string (.ai/rules/database.md). Indexed for the per-day board query.
            $table->date('date')->index();
            // Exactly one of these is set: a work location when present, an absence reason
            // when away. Neither set is impossible — the row is deleted instead. The rule
            // is enforced in the SetWhereabouts action, not the schema.
            $table->foreignId('work_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('absence')->nullable();
            $table->timestamps();

            // One registration per person per day; upserted by SetWhereabouts.
            $table->unique(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whereabouts');
    }
};
