<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The only genuinely new concepts: sequences, their steps, and leads enrolled in them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mail_setting_id')->nullable()->constrained()->nullOnDelete();   // default sending account
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft');
            $table->string('timezone', 64)->nullable();            // NULL = app timezone
            $table->time('sending_start_time')->default('09:00:00');
            $table->time('sending_end_time')->default('17:00:00');
            $table->json('sending_days');                           // ISO weekdays 1 (Mon) - 7 (Sun)
            $table->unsignedInteger('daily_limit')->default(100);
            $table->boolean('track_opens')->default(true);
            $table->boolean('track_clicks')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('subject', 998);
            $table->longText('body');
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->unsignedInteger('delay_hours')->default(0);
            $table->unsignedInteger('delay_days')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['sequence_id', 'step_number']);
        });

        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_setting_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('current_step')->default(0);   // last step number completed
            $table->string('status', 16)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason', 32)->nullable();
            $table->timestamp('paused_at')->nullable();
            // Set by the scheduler when it dispatches a job, so overlapping scheduler
            // runs do not queue the same enrollment twice.
            $table->timestamp('dispatch_lease_until')->nullable();
            $table->timestamps();

            // A lead is enrolled at most once per sequence.
            $table->unique(['sequence_id', 'lead_id']);
            $table->index(['status', 'next_action_at']);
            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_enrollments');
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('sequences');
    }
};
