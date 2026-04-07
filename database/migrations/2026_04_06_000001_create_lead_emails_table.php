<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->text('attachments')->nullable(); // JSON array of {name, path, size, mime}
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_emails');
    }
};
