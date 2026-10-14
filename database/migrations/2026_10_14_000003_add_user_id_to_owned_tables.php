<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every record now belongs to a user.
 *
 * Existing rows were created when the app had one shared workspace, so they
 * all go to the super admin. Any other account that already exists starts with
 * an empty workspace (plus the default platforms).
 *
 * bulk_items is not listed: it is only ever reached through its bulk, which is
 * owned. app_settings (branding) stays global and super-admin managed.
 */
return new class extends Migration
{
    private const TABLES = [
        'leads',
        'lead_emails',
        'email_templates',
        'platforms',
        'categories',
        'addresses',
        'bulks',
        'email_verifications',
        'finder_results',
        'mail_settings',
        'gmb_leads',
        'app_notifications',
        'cash_leads',
    ];

    /** Values that were unique across the app and are now unique per user. */
    private const PER_USER_UNIQUE = [
        'categories'     => 'name',
        'mail_settings'  => 'type',
        'gmb_leads'      => 'place_id',
        'finder_results' => 'domain',
    ];

    private const DEFAULT_PLATFORMS = ['Google', 'LinkedIn', 'Upwork', 'Freelancing', 'Facebook'];

    public function up(): void
    {
        $ownerId = DB::table('users')->where('role', 'super_admin')->orderBy('id')->value('id');

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                // Deleting a user deletes their workspace with them.
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });

            if ($ownerId) {
                DB::table($tableName)->whereNull('user_id')->update(['user_id' => $ownerId]);
            }
        }

        foreach (self::PER_USER_UNIQUE as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->dropUnique([$column]);
                $table->unique(['user_id', $column]);
            });
        }

        $now = now();

        foreach (DB::table('users')->where('id', '!=', $ownerId ?? 0)->pluck('id') as $userId) {
            DB::table('platforms')->insert(array_map(fn (string $name) => [
                'user_id'    => $userId,
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ], self::DEFAULT_PLATFORMS));
        }
    }

    /**
     * Note: rows that only differ by owner (two users' "Restaurants") will
     * collide on the restored global unique keys - de-duplicate them first.
     */
    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                // Foreign key first: MySQL may be using the (user_id, ...) unique
                // index to back it, and refuses to drop an index a key relies on.
                $table->dropForeign(['user_id']);

                if (isset(self::PER_USER_UNIQUE[$tableName])) {
                    $column = self::PER_USER_UNIQUE[$tableName];
                    $table->dropUnique(['user_id', $column]);
                    $table->unique([$column]);
                }

                $table->dropColumn('user_id');
            });
        }
    }
};
