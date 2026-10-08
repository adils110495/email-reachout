<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leads we have talked to and expect to turn into paying customers.
        // A snapshot of the lead is stored (not just a link), so the cash lead
        // survives if the original lead or GMB lead is later deleted.
        Schema::create('cash_leads', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('manual');          // manual | lead | gmb
            $table->foreignId('lead_id')->nullable()->unique()->constrained('leads')->nullOnDelete();
            $table->foreignId('gmb_lead_id')->nullable()->unique()->constrained('gmb_leads')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_leads');
    }
};
