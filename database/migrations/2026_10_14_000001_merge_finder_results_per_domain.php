<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One Finder row per website instead of one per address.
 *
 * `emails` holds every address found for the domain as a JSON array; `email`
 * stays as the primary (best-scoring) one, which the Save button and the
 * verdict columns describe. Existing rows are folded into the best row for
 * their domain before the unique key moves from (domain, email) to domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finder_results', function (Blueprint $table) {
            $table->json('emails')->nullable()->after('email');
        });

        $domains = DB::table('finder_results')
            ->select('domain')
            ->groupBy('domain')
            ->pluck('domain');

        foreach ($domains as $domain) {
            // Saved row first, then the strongest candidate.
            $rows = DB::table('finder_results')
                ->where('domain', $domain)
                ->orderByRaw('lead_id IS NULL')
                ->orderByDesc('score')
                ->orderBy('id')
                ->get();

            $keep   = $rows->first();
            $emails = $rows->pluck('email')->map(fn ($e) => strtolower($e))->unique()->values()->all();

            DB::table('finder_results')->where('id', $keep->id)->update([
                'emails'  => json_encode($emails),
                'lead_id' => $keep->lead_id ?? $rows->whereNotNull('lead_id')->first()?->lead_id,
            ]);

            DB::table('finder_results')
                ->where('domain', $domain)
                ->where('id', '!=', $keep->id)
                ->delete();
        }

        Schema::table('finder_results', function (Blueprint $table) {
            $table->dropUnique(['domain', 'email']);
            $table->unique('domain');
        });
    }

    public function down(): void
    {
        // The merged-away rows are not restored; their addresses remain in
        // `emails` until the column is dropped.
        Schema::table('finder_results', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->unique(['domain', 'email']);
            $table->dropColumn('emails');
        });
    }
};
