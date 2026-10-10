<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Leads are the sequencer's contacts and categories are its lists.
 *
 * leads      + person fields, custom fields, a contactability status (separate from the
 *              outreach `status` new/sent/failed/replied), unsubscribe token, bounce/unsubscribe times.
 *              company_name / website become optional: an imported person may have neither.
 * categories + description, and a many-to-many pivot so a lead can sit in several lists.
 *              leads.category_id stays the lead's primary category and is mirrored into the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('company_name')->nullable()->change();
            $table->string('website')->nullable()->change();

            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('job_title')->nullable()->after('company_name');
            $table->string('phone', 64)->nullable()->after('linkedin');
            $table->string('country', 100)->nullable()->after('phone');
            $table->json('custom_fields')->nullable()->after('country');
            $table->string('contact_status', 16)->default('active')->after('status');
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('contact_status');
            $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribe_token');
            $table->timestamp('bounced_at')->nullable()->after('unsubscribed_at');

            $table->index('email');
            $table->index('contact_status');
        });

        // Every existing lead gets its unguessable unsubscribe token.
        DB::table('leads')->whereNull('unsubscribe_token')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('leads')->where('id', $row->id)->update(['unsubscribe_token' => Str::random(48)]);
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('name');
        });

        Schema::create('category_lead', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['category_id', 'lead_id']);
            $table->index('lead_id');
        });

        DB::statement('INSERT INTO category_lead (category_id, lead_id, created_at) SELECT category_id, id, NOW() FROM leads WHERE category_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('category_lead');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['contact_status']);
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn([
                'first_name', 'last_name', 'job_title', 'phone', 'country', 'custom_fields',
                'contact_status', 'unsubscribe_token', 'unsubscribed_at', 'bounced_at',
            ]);
        });

        DB::table('leads')->whereNull('company_name')->update(['company_name' => '']);
        DB::table('leads')->whereNull('website')->update(['website' => '']);

        Schema::table('leads', function (Blueprint $table) {
            $table->string('company_name')->nullable(false)->change();
            $table->string('website')->nullable(false)->change();
        });
    }
};
