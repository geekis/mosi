<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Birthday — month and day only, derived from the kennitala the sync already stores.
 *
 * The year is deliberately never derived, stored, or exposed — a birthday shows
 * "3. September", never an age (see .ai/rules/staff-api.md). Keeping month and day in
 * their own columns means the celebrations widget is a cheap indexed query and the
 * encrypted kennitala is never decrypted on a page render.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('birth_month')->nullable()->after('kennitala_hash');
            $table->unsignedTinyInteger('birth_day')->nullable()->after('birth_month');
        });

        // Backfill existing staff from their stored (encrypted) kennitala.
        User::query()->whereNotNull('kennitala')->cursor()->each(function (User $user): void {
            $birthday = User::birthdayFromKennitala((string) $user->kennitala);

            if ($birthday !== null) {
                $user->forceFill($birthday)->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birth_month', 'birth_day']);
        });
    }
};
