<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user: their own SerpAPI key and search defaults
        // (Settings > SerpAPI). Without one, the server's SERPAPI_KEY is used.
        Schema::create('serpapi_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('api_key')->nullable();             // stored encrypted
            $table->string('country', 2)->nullable();        // Google "gl" for lead searches, e.g. in
            $table->string('language', 10)->nullable();      // Google "hl", e.g. en
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serpapi_settings');
    }
};
