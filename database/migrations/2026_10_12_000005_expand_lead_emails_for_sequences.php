<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lead_emails already records every email sent to a lead, with open tracking
 * (tracking_token, open_count, first/last_opened_at), threading (message_id) and
 * replies (replied_at). Sequence emails are recorded in the same table, so the
 * Email Activity page, the dashboard and reply checking see both kinds.
 *
 * Added: the sequence / step / enrollment / sending account it belongs to, the
 * delivery pipeline state (queued -> sending -> sent | failed | bounced, attempts,
 * claimed_at), clicks, bounces and error details - plus the idempotency key.
 *
 * Also new: tracked_links (click tracking), daily_send_counters (limits),
 * inbound_messages (IMAP de-duplication) and activity_events (timeline).
 */
return new class extends Migration
{
    public function up(): void
    {
        // enum('sent','failed') -> string, so the pipeline can use queued/sending/bounced too.
        Schema::table('lead_emails', function (Blueprint $table) {
            $table->string('status', 16)->default('sent')->change();
        });

        Schema::table('lead_emails', function (Blueprint $table) {
            $table->foreignId('sequence_id')->nullable()->after('lead_id')->constrained()->nullOnDelete();
            $table->foreignId('sequence_step_id')->nullable()->after('sequence_id')->constrained('sequence_steps')->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->after('sequence_step_id')->constrained('sequence_enrollments')->nullOnDelete();
            $table->foreignId('mail_setting_id')->nullable()->after('enrollment_id')->constrained()->nullOnDelete();
            $table->string('from_email')->nullable()->after('mail_setting_id');
            $table->string('to_email')->nullable()->after('from_email');
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
            $table->timestamp('claimed_at')->nullable()->after('attempts');
            $table->timestamp('clicked_at')->nullable()->after('last_opened_at');
            $table->unsignedInteger('click_count')->default(0)->after('clicked_at');
            $table->timestamp('bounced_at')->nullable()->after('replied_at');
            $table->text('error_message')->nullable()->after('bounced_at');
            $table->json('metadata')->nullable()->after('error_message');

            // THE idempotency key: one enrollment + one step = one send attempt row.
            $table->unique(['enrollment_id', 'sequence_step_id'], 'lead_emails_enrollment_step_unique');
            $table->index(['status', 'claimed_at']);
            $table->index('sent_at');
        });

        // Older rows: fill the recipient from the lead so every row reads the same way.
        DB::statement('UPDATE lead_emails le JOIN leads l ON l.id = le.lead_id SET le.to_email = l.email WHERE le.to_email IS NULL');

        Schema::create('tracked_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_email_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->text('url');
            $table->char('url_hash', 40);
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_email_id', 'url_hash']);
        });

        // Atomic per-day send counters (see DailyLimitService).
        Schema::create('daily_send_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);                           // sequence | account
            $table->unsignedBigInteger('scope_id');
            $table->date('day');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'day']);
        });

        // Every IMAP message inspected, so none is processed twice.
        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_setting_id')->constrained()->cascadeOnDelete();
            $table->string('folder');
            $table->unsignedBigInteger('uid');
            $table->unsignedBigInteger('uid_validity')->default(0);
            $table->string('message_id')->nullable();
            $table->string('from_email')->nullable();
            $table->string('subject', 998)->nullable();
            $table->string('classification', 16);                  // reply | bounce | soft_bounce | auto_reply | unmatched
            $table->foreignId('lead_email_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['mail_setting_id', 'folder', 'uid_validity', 'uid'], 'inbound_messages_uid_unique');
            $table->unique(['mail_setting_id', 'message_id'], 'inbound_messages_message_id_unique');
        });

        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('sequence_enrollments')->nullOnDelete();
            $table->foreignId('lead_email_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('description', 500);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index('occurred_at');
            $table->index(['enrollment_id', 'occurred_at']);
            $table->index(['lead_id', 'occurred_at']);
            $table->index(['sequence_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('inbound_messages');
        Schema::dropIfExists('daily_send_counters');
        Schema::dropIfExists('tracked_links');

        Schema::table('lead_emails', function (Blueprint $table) {
            $table->dropUnique('lead_emails_enrollment_step_unique');
            $table->dropIndex(['status', 'claimed_at']);
            $table->dropIndex(['sent_at']);
            $table->dropConstrainedForeignId('sequence_id');
            $table->dropConstrainedForeignId('sequence_step_id');
            $table->dropConstrainedForeignId('enrollment_id');
            $table->dropConstrainedForeignId('mail_setting_id');
            $table->dropColumn(['from_email', 'to_email', 'attempts', 'claimed_at', 'clicked_at', 'click_count', 'bounced_at', 'error_message', 'metadata']);
        });

        DB::table('lead_emails')->whereNotIn('status', ['sent', 'failed'])->update(['status' => 'failed']);

        Schema::table('lead_emails', function (Blueprint $table) {
            $table->enum('status', ['sent', 'failed'])->default('sent')->change();
        });
    }
};
