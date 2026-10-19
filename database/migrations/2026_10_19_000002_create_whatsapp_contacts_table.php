<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The address book of a coexistence number: contacts synced from the
        // WhatsApp Business app (smb_app_state_sync) and the people of its chat
        // history. Kept inside the WhatsApp module - not copied into leads.
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);                 // international digits, no "+"
            $table->string('name')->nullable();
            $table->string('source', 16);                // app (contact sync) | history
            $table->timestamp('removed_at')->nullable(); // removed on the phone
            $table->timestamps();

            $table->unique(['user_id', 'phone']);
        });

        // Messages are no longer only ours: a coexistence number also brings
        // the ones sent from the phone app and its imported chat history,
        // received messages included.
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('direction', 3)->default('out')->after('channel');   // out | in
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('direction');
        });

        Schema::dropIfExists('whatsapp_contacts');
    }
};
