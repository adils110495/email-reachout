<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_emails', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('status'); // open-pixel key
            $table->string('message_id')->nullable()->index()->after('tracking_token');  // Message-ID header, matched against replies
            $table->unsignedInteger('open_count')->default(0)->after('message_id');
            $table->timestamp('first_opened_at')->nullable()->after('open_count');
            $table->timestamp('last_opened_at')->nullable()->after('first_opened_at');
            $table->timestamp('replied_at')->nullable()->after('last_opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('lead_emails', function (Blueprint $table) {
            $table->dropColumn(['tracking_token', 'message_id', 'open_count', 'first_opened_at', 'last_opened_at', 'replied_at']);
        });
    }
};
