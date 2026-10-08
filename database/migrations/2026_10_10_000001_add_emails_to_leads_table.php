<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every address found for a lead, as a JSON array. leads.email stays as
        // the primary (first) address, which sending, the verifier and the
        // other modules keep using.
        Schema::table('leads', function (Blueprint $table) {
            $table->json('emails')->nullable()->after('email');
        });

        DB::table('leads')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->update(['emails' => DB::raw('JSON_ARRAY(email)')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('emails');
        });
    }
};
