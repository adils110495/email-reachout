<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification history. Every check performed by EmailVerifierService is
 * recorded here - single checks from the Verifier page, and every email that
 * passes through a bulk verify run - so the history/report is queryable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('domain')->index();
            $table->enum('status', ['valid', 'invalid', 'risky', 'unknown'])->default('unknown')->index();
            // 0-100 confidence derived from the individual checks.
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('reason')->nullable();
            // {syntax, domain, mx, smtp, disposable, role, free, catch_all, mx_hosts}
            $table->json('checks')->nullable();
            // single | bulk | finder
            $table->string('source', 20)->default('single')->index();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bulk_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};
