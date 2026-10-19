<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Numbers connected through Meta's Embedded Signup (Settings > WhatsApp >
        // Connect WhatsApp Business App). A coexistence number keeps working in
        // the WhatsApp Business app on the phone too. Sending prefers the owner's
        // connected account here and falls back to whatsapp_settings.
        Schema::create('wa_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('waba_id', 32);
            $table->string('phone_number_id', 32)->nullable()->unique();
            $table->string('display_phone_number', 32)->nullable();
            $table->string('verified_name')->nullable();
            $table->string('business_id', 32)->nullable();
            $table->text('access_token');                              // business token, stored encrypted
            $table->string('token_type', 32)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('two_step_pin')->nullable();                   // non-coexistence register PIN, encrypted
            $table->boolean('is_coexistence')->default(false);
            $table->string('onboarding_event', 64)->nullable();        // FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING, FINISH, …
            $table->timestamp('history_sync_requested_at')->nullable();
            $table->timestamp('contacts_sync_requested_at')->nullable();
            $table->timestamp('onboarded_at')->nullable();             // last Embedded Signup - Meta's 24h sync window starts here
            $table->string('history_status', 16)->nullable();          // requested | importing | complete | declined
            $table->string('status', 16)->default('connected');        // connected | disconnected
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('waba_id');                                  // app-level webhook: entry.id -> account
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_accounts');
    }
};
