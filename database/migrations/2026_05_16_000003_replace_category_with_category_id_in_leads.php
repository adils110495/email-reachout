<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Drop the old free-text category column if it exists
            if (Schema::hasColumn('leads', 'category')) {
                $table->dropColumn('category');
            }

            // Add proper FK to categories table
            $table->unsignedBigInteger('category_id')->nullable()->after('platform_id');
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
            $table->string('category')->nullable()->after('platform_id');
        });
    }
};
