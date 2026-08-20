<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emoji reactions on any reactable (posts, comments). One reaction per user per item —
 * reacting again changes the emoji rather than stacking (spec §7: 5–6 emoji, not a like).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('reactable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->unique(['reactable_id', 'reactable_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
