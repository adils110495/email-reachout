<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per input line of a bulk operation. Kept separate from `bulks` so a
 * large upload can be processed in small chunks and resumed after a worker
 * restart: ProcessBulkJob simply picks up the next `pending` items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_id')->constrained()->cascadeOnDelete();
            // The email (verify) or domain/website (find) taken from the CSV.
            $table->string('input');
            // Optional second CSV column - company name, or person name for finder.
            $table->string('extra')->nullable();
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            // verify: valid|invalid|risky|unknown   find: found|not_found
            $table->string('result_status', 20)->nullable();
            // find: the discovered email address.
            $table->string('result_value')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['bulk_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_items');
    }
};
