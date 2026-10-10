<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app runs on India Standard Time (APP_TIMEZONE=Asia/Kolkata).
 *
 * No stored time changes: every date column is a MySQL TIMESTAMP (an absolute instant), and
 * the database session now runs in the app timezone (config/database.php), so existing rows
 * simply read back in IST. What this changes are the leftover "UTC" settings - each user's
 * display timezone (and its default for new users), and each sequence's sending-window timezone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $timezone = config('app.timezone');

        Schema::table('users', function (Blueprint $table) use ($timezone) {
            $table->string('timezone', 64)->default($timezone)->change();
        });

        DB::table('users')->where('timezone', 'UTC')->update(['timezone' => $timezone]);
        DB::table('sequences')->where('timezone', 'UTC')->update(['timezone' => $timezone]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->change();
        });

        // Rows keep their timezone: which ones were UTC before is not recorded.
    }
};
