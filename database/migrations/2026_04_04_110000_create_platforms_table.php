<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Seed default platforms
        DB::table('platforms')->insert([
            ['name' => 'Google',      'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'LinkedIn',    'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Upwork',      'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Freelancing', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Facebook',    'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
