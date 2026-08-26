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
        Schema::table('users', function (Blueprint $table) {
            // The immutable Entra object ID (oid claim). Stable across email and name
            // changes, so it is the primary match key for SSO login; email is only the
            // first-login fallback used to bind this value (.ai/rules/auth.md).
            $table->string('entra_oid')->nullable()->unique()->after('auth_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['entra_oid']);
            $table->dropColumn('entra_oid');
        });
    }
};
