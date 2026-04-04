<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('platform');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_id')->nullable()->after('status');
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
        });

        // Set default platform_id to Google (id=1) for existing leads
        $google = DB::table('platforms')->where('name', 'Google')->first();
        if ($google) {
            DB::table('leads')->update(['platform_id' => $google->id]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['platform_id']);
            $table->dropColumn('platform_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('platform')->default('Google')->after('status');
        });
    }
};
