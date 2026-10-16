<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user: their WhatsApp Business Cloud API connection.
        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('default_country_code', 4)->nullable();  // put in front of local numbers, e.g. 91
            $table->string('phone_number_id')->nullable();          // Meta: the sending number's id
            $table->string('business_account_id')->nullable();      // Meta: WABA id, lists the templates
            $table->text('access_token')->nullable();               // stored encrypted
            $table->text('app_secret')->nullable();                 // stored encrypted, signs webhooks
            $table->string('webhook_key', 64)->unique();            // random, identifies the user in the webhook URL
            $table->string('verify_token', 64);                     // random, Meta echoes it when the webhook is set up
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_settings');
    }
};
