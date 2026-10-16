<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bulk sends: the messages of one bulk share a batch_id, and each keeps
        // its own template values so the queued job can send it later.
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->index()->after('user_id');
            $table->json('params')->nullable()->after('template_language');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['batch_id', 'params']);
        });
    }
};
