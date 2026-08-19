<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Map the users table onto the NVS staff API (see .ai/rules/staff-api.md).
 *
 * Accounts are created only by the staff sync; there is no self-registration. `name`
 * becomes `display_name` (the whole Icelandic name, patronymic included) and never gets
 * split; `given_name` is the first token, derived by the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'display_name');
        });

        Schema::table('users', function (Blueprint $table) {
            // Identity from the staff API.
            $table->string('external_id')->nullable()->after('id');
            $table->string('given_name')->nullable()->after('display_name');

            // Kennitala: encrypted at rest, with a SHA-256 hash for lookups. Never
            // fillable, always hidden (enforced on the model).
            $table->text('kennitala')->nullable()->after('given_name');
            $table->char('kennitala_hash', 64)->nullable()->after('kennitala');

            // Contact — both may be missing for some staff.
            $table->string('phone')->nullable()->after('email');
            $table->string('employee_number')->nullable()->after('phone');
            $table->string('person_type')->nullable()->after('employee_number');

            // Primary assignment (primaryFlag === 'Y').
            $table->string('job_title')->nullable();
            $table->string('job_id')->nullable();
            $table->string('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('parent_organization_id')->nullable();
            $table->string('assignment_status')->nullable();
            $table->date('assignment_starts_at')->nullable();
            $table->date('assignment_ends_at')->nullable();
            $table->unsignedSmallInteger('assignment_percent')->nullable();
            $table->boolean('is_manager')->default(false);
            $table->string('supervisor_external_id')->nullable();
            // Resolved to users.id in the sync's second pass. No DB-level FK: it is
            // self-referential and SQLite cannot add one post-creation; enforced in the model.
            $table->unsignedBigInteger('supervisor_id')->nullable();

            // Location — data quality is suspect; do not build filters on it (staff-api.md).
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();

            // Auth & preferences.
            $table->string('auth_method')->default('password');
            $table->string('locale', 5)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('password_changed_at')->nullable();
        });

        // Entra users have no password; some staff have no email.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('email')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('external_id');
            $table->unique('kennitala_hash');
            $table->index('organization_id');
            $table->index('parent_organization_id');
            $table->index('supervisor_external_id');
            $table->index('supervisor_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropUnique(['kennitala_hash']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['parent_organization_id']);
            $table->dropIndex(['supervisor_external_id']);
            $table->dropIndex(['supervisor_id']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'external_id', 'given_name', 'kennitala', 'kennitala_hash', 'phone',
                'employee_number', 'person_type', 'job_title', 'job_id', 'organization_id',
                'organization_name', 'parent_organization_id', 'assignment_status',
                'assignment_starts_at', 'assignment_ends_at', 'assignment_percent',
                'is_manager', 'supervisor_external_id', 'supervisor_id', 'location_name',
                'location_address', 'auth_method', 'locale', 'is_active', 'password_changed_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('display_name', 'name');
        });
    }
};
