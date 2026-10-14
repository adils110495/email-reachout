<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the user last picked a new username on their Profile page. A username
 * can be changed once every User::USERNAME_CHANGE_DAYS days; null = never
 * changed (the one generated at signup), so the first change is allowed
 * straight away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('username_changed_at')->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username_changed_at');
        });
    }
};
