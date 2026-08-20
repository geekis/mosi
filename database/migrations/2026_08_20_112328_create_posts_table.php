<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posts are user-generated content, stored as written and never translated
 * (.ai/rules/localisation.md). A post belongs to a group, or to no group (a personal
 * post shown on the author's profile and to their followers). Drafts and scheduled
 * posts have published_at null or in the future.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->boolean('is_official')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_id', 'published_at']);
            $table->index('published_at');
            $table->index('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
