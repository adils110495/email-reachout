<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per bulk operation (a CSV upload, or a pasted list of emails).
 * Counters are updated by ProcessBulkJob as it works through bulk_items, so
 * the Bulks UI can show live progress without scanning the item rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // verify = check deliverability of each email
            // find   = discover an email for each domain/company
            $table->enum('type', ['verify', 'find'])->default('verify');
            // Find-type bulks file their discovered leads under this category,
            // which is what the Leads module filters on.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                  ->default('pending')->index();
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulks');
    }
};
