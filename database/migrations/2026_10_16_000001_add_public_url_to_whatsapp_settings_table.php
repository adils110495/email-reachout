<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The address Meta can reach the app at (live domain, or an ngrok /
        // cloudflared tunnel while testing). The webhook callback URL is built
        // from it; when blank, from the address the settings page was opened on.
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->string('public_url')->nullable()->after('app_secret');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_settings', function (Blueprint $table) {
            $table->dropColumn('public_url');
        });
    }
};
