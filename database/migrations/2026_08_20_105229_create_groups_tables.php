<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups and their memberships. Names and descriptions are translatable JSON from the
 * start (never retrofit — .ai/rules/localisation.md). Department groups are keyed to an
 * organisation id so the staff sync can find-or-create and auto-enrol into them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('type')->index();
            $table->string('visibility')->default('open')->index();
            // organizationId (or the NVS root 39162 for the org-wide group). Unique so a
            // department group is created once and enrolled into thereafter.
            $table->string('external_organization_id')->nullable()->unique();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');
    }
};
