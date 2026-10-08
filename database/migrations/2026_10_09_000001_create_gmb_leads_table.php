<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Businesses that have a Google Business Profile but NO website.
        Schema::create('gmb_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('keyword');
            $table->string('place_id')->unique();       // Google's id - stops duplicates across searches
            $table->string('name');
            $table->string('type')->nullable();          // e.g. "Plumber"
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('reviews')->nullable();
            $table->text('maps_url')->nullable();        // link to the Google Maps listing
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmb_leads');
    }
};
