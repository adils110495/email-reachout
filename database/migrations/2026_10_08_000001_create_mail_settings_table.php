<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per type: 'smtp' (sending) and 'imap' (Sent-folder copy).
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();          // smtp | imap
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('encryption')->nullable();  // smtp: tls|ssl|none  imap: ssl|tls|notls
            $table->string('username')->nullable();
            $table->text('password')->nullable();      // stored encrypted
            $table->string('from_address')->nullable(); // smtp only
            $table->string('from_name')->nullable();    // smtp only
            $table->string('folder')->nullable();       // imap only (Sent folder)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
