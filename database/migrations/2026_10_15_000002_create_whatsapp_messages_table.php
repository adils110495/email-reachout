<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every WhatsApp message sent from the app. The contact's name and number
        // are a snapshot, so the log survives the GMB lead or deal being deleted.
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gmb_lead_id')->nullable()->constrained('gmb_leads')->nullOnDelete();
            $table->foreignId('cash_lead_id')->nullable()->constrained('cash_leads')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('phone', 20);                     // international digits, no "+" (919876543210)
            $table->string('channel', 10);                   // api | link
            $table->string('type', 10);                      // text | template
            $table->string('template_name')->nullable();
            $table->string('template_language', 15)->nullable();
            $table->text('body')->nullable();
            $table->string('wamid')->nullable()->unique();   // Meta's message id, matched against webhook statuses
            $table->string('status', 15);                    // sent | delivered | read | failed | manual
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('reply_body')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'phone']);             // webhook: incoming message -> latest message to that number
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
