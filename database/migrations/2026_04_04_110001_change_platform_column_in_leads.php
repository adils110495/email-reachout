<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Change platform from enum to string (stores platform name)
            $table->string('platform')->default('Google')->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->enum('platform', ['google', 'linkedin', 'upwork', 'freelancing', 'facebook'])
                  ->default('google')->change();
        });
    }
};
