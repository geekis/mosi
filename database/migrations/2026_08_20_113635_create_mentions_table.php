<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records @mentions on any mentionable (posts, comments) so "mentions of me" can be
 * queried later. Notifications are sent only to users who can see the content — a
 * secret group must never leak through a mention (.ai/rules/auth.md, testing.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->morphs('mentionable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mentionable_id', 'mentionable_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentions');
    }
};
