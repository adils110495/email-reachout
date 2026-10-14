<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and module access on the existing users table.
 *
 *   role       super_admin | user
 *   is_active  a deactivated user cannot sign in, and an open session is ended
 *   modules    JSON list of module keys (User::MODULES); null = every module
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('password')->index();
            $table->boolean('is_active')->default(true)->after('role');
            $table->json('modules')->nullable()->after('is_active');
        });

        // The account the users migration seeds becomes the super admin; on an
        // install where it was renamed, the oldest account takes the role.
        $ownerId = DB::table('users')->where('username', 'superadmin')->value('id')
            ?? DB::table('users')->min('id');

        if ($ownerId) {
            DB::table('users')->where('id', $ownerId)->update(['role' => 'super_admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'is_active', 'modules']);
        });
    }
};
