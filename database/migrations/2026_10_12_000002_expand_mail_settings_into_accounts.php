<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mail Settings used to hold exactly two rows: one "smtp" (sending) and one "imap"
 * (Sent-folder copy). The sequencer needs several sending accounts, each with its own
 * SMTP + IMAP, limits and health, so every row now IS an account:
 *
 *   host/port/encryption/username/password/from_*  = SMTP (unchanged columns)
 *   folder                                         = IMAP Sent folder (unchanged meaning)
 *   imap_*                                         = IMAP login, used for the Sent copy AND reply/bounce polling
 *   is_default                                     = the account the rest of the app sends with
 *
 * The existing smtp + imap rows are merged into one default account, so nothing the
 * old screens saved is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('provider', 32)->default('smtp')->after('name');
            $table->boolean('is_default')->default(false)->after('is_active');

            $table->string('imap_host')->nullable()->after('folder');
            $table->unsignedSmallInteger('imap_port')->nullable()->after('imap_host');
            $table->string('imap_encryption', 8)->nullable()->after('imap_port');
            $table->string('imap_username')->nullable()->after('imap_encryption');
            $table->text('imap_password')->nullable()->after('imap_username');
            $table->string('imap_folder')->default('INBOX')->after('imap_password');   // inbox polled for replies

            $table->text('provider_config')->nullable();
            $table->unsignedInteger('daily_limit')->nullable();
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(10);

            $table->boolean('smtp_ok')->nullable();
            $table->timestamp('smtp_tested_at')->nullable();
            $table->boolean('imap_ok')->nullable();
            $table->timestamp('imap_tested_at')->nullable();
            $table->unsignedBigInteger('imap_last_uid')->nullable();
            $table->unsignedBigInteger('imap_uid_validity')->nullable();
            $table->timestamp('imap_last_checked_at')->nullable();
            $table->text('imap_last_error')->nullable();
        });

        // Merge the legacy smtp + imap rows into a single default account.
        $smtp = DB::table('mail_settings')->where('type', 'smtp')->first();
        $imap = DB::table('mail_settings')->where('type', 'imap')->first();

        if ($smtp || $imap) {
            $targetId = $smtp->id ?? $imap->id;

            $update = [
                'name' => ($smtp->from_address ?? null) ?: ($imap->username ?? 'Default account'),
                'is_default' => true,
            ];

            if ($imap) {
                $update += [
                    'imap_host' => $imap->host,
                    'imap_port' => $imap->port,
                    'imap_encryption' => $imap->encryption === 'notls' ? 'none' : $imap->encryption,
                    'imap_username' => $imap->username,
                    'imap_password' => $imap->password,     // already encrypted with the same APP_KEY
                    'folder' => $imap->folder,              // Sent folder
                ];
            }

            if (! $smtp) {
                // Only IMAP was saved: keep it, with no SMTP host yet.
                $update += ['host' => null, 'port' => null, 'encryption' => null, 'username' => null, 'password' => null];
            }

            DB::table('mail_settings')->where('id', $targetId)->update($update);

            if ($smtp && $imap) {
                DB::table('mail_settings')->where('id', $imap->id)->delete();
            }
        }

        Schema::table('mail_settings', function (Blueprint $table) {
            $table->dropUnique(['type']);
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->string('type')->nullable()->after('id');
        });

        // Split the default account back into the legacy smtp + imap rows; other accounts are dropped.
        $default = DB::table('mail_settings')->where('is_default', true)->first() ?? DB::table('mail_settings')->orderBy('id')->first();
        DB::table('mail_settings')->when($default, fn ($q) => $q->where('id', '!=', $default->id))->delete();

        if ($default) {
            DB::table('mail_settings')->where('id', $default->id)->update(['type' => 'smtp']);

            if ($default->imap_host) {
                DB::table('mail_settings')->insert([
                    'type' => 'imap',
                    'host' => $default->imap_host,
                    'port' => $default->imap_port,
                    'encryption' => $default->imap_encryption === 'none' ? 'notls' : $default->imap_encryption,
                    'username' => $default->imap_username,
                    'password' => $default->imap_password,
                    'folder' => $default->folder,
                    'is_active' => $default->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('mail_settings', function (Blueprint $table) {
            $table->unique('type');
            $table->dropColumn([
                'name', 'provider', 'is_default', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username',
                'imap_password', 'imap_folder', 'provider_config', 'daily_limit', 'rate_limit_per_minute',
                'smtp_ok', 'smtp_tested_at', 'imap_ok', 'imap_tested_at', 'imap_last_uid', 'imap_uid_validity',
                'imap_last_checked_at', 'imap_last_error',
            ]);
        });
    }
};
