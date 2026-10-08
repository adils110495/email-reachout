<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every address the Finder turns up, kept whether or not it becomes a lead.
 *
 * `leads` holds one row per company and one address on it - which is right for
 * outreach, but it means a search that finds five addresses can only record the
 * best, person-search guesses cannot be stored at all (they would be emailed),
 * and nothing remembers which search produced what.
 *
 * This is the Finder's own record: all candidates, their scores, and a link to
 * the lead when one was created from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finder_results', function (Blueprint $table) {
            $table->id();

            // How it was found.
            $table->enum('mode', ['domain', 'person'])->default('domain')->index();
            $table->string('domain')->index();
            $table->string('person')->nullable();      // person mode: the name searched
            $table->string('company')->nullable();     // scraped from the site

            $table->string('email')->index();

            // Verification verdict at the time of the search.
            $table->enum('status', ['valid', 'invalid', 'risky', 'unknown'])->default('unknown')->index();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('reason')->nullable();

            // website = published on the site, pattern = generated for a person.
            $table->string('source', 20)->default('website');
            // A generated address is never a confirmed one; the UI must keep saying so.
            $table->boolean('guessed')->default(false)->index();
            // personal = a named mailbox, generic = info@ / sales@ and friends.
            $table->string('type', 20)->default('personal');
            $table->string('pattern', 40)->nullable(); // person mode: which pattern produced it

            // Set once this address has been filed as a lead.
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // Re-searching a domain refreshes its rows instead of duplicating
            // them - the score and status are worth updating, the address is not
            // worth storing twice.
            $table->unique(['domain', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finder_results');
    }
};
