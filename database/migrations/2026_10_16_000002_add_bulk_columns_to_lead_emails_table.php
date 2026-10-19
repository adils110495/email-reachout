<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bulk email to deals: a deal is not a lead, so lead_id becomes optional
        // and the recipient is kept on the row itself (a snapshot, like
        // whatsapp_messages). The emails of one bulk share a batch_id and wait
        // on the queue as "queued" until their turn.
        Schema::table('lead_emails', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });

        Schema::table('lead_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable()->change();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();

            $table->uuid('batch_id')->nullable()->index()->after('user_id');
            $table->foreignId('cash_lead_id')->nullable()->after('lead_id')->constrained('cash_leads')->nullOnDelete();
            $table->string('contact_name')->nullable()->after('cash_lead_id');
            $table->string('to_email')->nullable()->after('contact_name');
            $table->string('status', 15)->default('sent')->change();   // queued | sending | sent | failed | cancelled
            $table->text('error')->nullable()->after('status');

            $table->index(['user_id', 'to_email']);                    // bulk: "skip contacts already emailed"
        });
    }

    public function down(): void
    {
        // Rows the old schema cannot hold: emails to deals, and statuses it does not know.
        DB::table('lead_emails')->whereNull('lead_id')->delete();
        DB::table('lead_emails')->whereNotIn('status', ['sent', 'failed'])->update(['status' => 'failed']);

        Schema::table('lead_emails', function (Blueprint $table) {
            // MySQL let (user_id, to_email) stand in for the user_id foreign key's
            // own index, so user_id needs an index again before that one can go.
            $table->index('user_id');
            $table->dropIndex(['user_id', 'to_email']);
            $table->dropIndex(['batch_id']);
            $table->dropConstrainedForeignId('cash_lead_id');
            $table->dropColumn(['batch_id', 'contact_name', 'to_email', 'error']);
            $table->enum('status', ['sent', 'failed'])->default('sent')->change();
            $table->dropForeign(['lead_id']);
        });

        Schema::table('lead_emails', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable(false)->change();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });
    }
};
