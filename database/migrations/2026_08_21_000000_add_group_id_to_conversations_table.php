<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a conversation to a group. Each group has at most one dedicated group chat
 * (unique group_id); direct conversations leave it null. If the group is hard-deleted
 * the chat is kept but detached, so message history survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('group_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->unique('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
