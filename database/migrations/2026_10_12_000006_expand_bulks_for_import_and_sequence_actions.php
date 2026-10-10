<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulks already run a CSV (or any list of records) through the queue in chunks with
 * live progress. They now also:
 *
 *   type import      - create/update leads from a CSV (mapped columns) into a category
 *   type enroll      - enroll leads in a sequence
 *   type pause|resume|remove - act on sequence enrollments
 *   type unsubscribe - unsubscribe leads
 *
 * status "draft" = an import uploaded and previewed, waiting for the user to confirm
 * the column mapping. options carries per-type settings (mapping, sequence, account...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulks', function (Blueprint $table) {
            $table->string('type', 20)->default('verify')->change();
            $table->string('status', 16)->default('pending')->change();
            $table->json('options')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        DB::table('bulks')->whereNotIn('type', ['verify', 'find'])->delete();
        DB::table('bulks')->where('status', 'draft')->update(['status' => 'cancelled']);

        Schema::table('bulks', function (Blueprint $table) {
            $table->dropColumn('options');
        });

        Schema::table('bulks', function (Blueprint $table) {
            $table->enum('type', ['verify', 'find'])->default('verify')->change();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending')->change();
        });
    }
};
