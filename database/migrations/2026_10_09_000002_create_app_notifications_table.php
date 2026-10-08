<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In-app notifications raised by background jobs (finds, bulks).
        // Named app_notifications so it never clashes with Laravel's own
        // `notifications` table should that be added later.
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('success'); // success | error | info
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('url')->nullable();           // where "View" should lead
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
