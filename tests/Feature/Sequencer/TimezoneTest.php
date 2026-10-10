<?php

namespace Tests\Feature\Sequencer;

use App\Models\LeadEmail;
use App\Models\SequenceEnrollment;
use App\Sequencer\Support\MessageHeaders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSequencerData;
use Tests\TestCase;

/** The app runs on India Standard Time; a time from any other zone must land on the same instant. */
class TimezoneTest extends TestCase
{
    use CreatesSequencerData, RefreshDatabase;

    public function test_the_app_and_the_database_session_run_on_india_standard_time(): void
    {
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->assertSame('Asia/Kolkata', now()->timezoneName);
        $this->assertSame('+05:30', DB::selectOne('select @@session.time_zone as tz')->tz);
    }

    public function test_a_time_given_in_another_zone_is_stored_as_the_same_instant(): void
    {
        $this->fakeTransports();
        $enrollment = $this->enroll($this->makeSequence(), $this->makeLead());
        $utc = CarbonImmutable::parse('2026-03-04 04:00:00', 'UTC');

        $enrollment->forceFill(['next_action_at' => $utc])->save();

        $this->assertSame('2026-03-04 09:30:00', DB::table('sequence_enrollments')->value('next_action_at'), 'Stored as IST wall-clock.');
        $this->assertTrue($enrollment->refresh()->next_action_at->equalTo($utc));
        $this->assertSame('Asia/Kolkata', $enrollment->next_action_at->timezoneName);
    }

    public function test_sql_comparisons_with_a_utc_time_are_correct(): void
    {
        $this->fakeTransports();
        $this->enroll($this->makeSequence(), $this->makeLead())
            ->forceFill(['next_action_at' => CarbonImmutable::parse('2026-03-04 09:30:00', 'Asia/Kolkata')])->save();

        // 04:00 UTC is exactly 09:30 IST: due "at or before" it, not before 03:59 UTC.
        $this->assertSame(1, SequenceEnrollment::where('next_action_at', '<=', CarbonImmutable::parse('2026-03-04 04:00:00', 'UTC'))->count());
        $this->assertSame(0, SequenceEnrollment::where('next_action_at', '<=', CarbonImmutable::parse('2026-03-04 03:59:00', 'UTC'))->count());
    }

    public function test_incoming_mail_dates_are_converted_to_ist(): void
    {
        $date = MessageHeaders::date('Wed, 4 Mar 2026 04:00:00 +0000');

        $this->assertSame('Asia/Kolkata', $date->timezoneName);
        $this->assertSame('2026-03-04 09:30:00', $date->toDateTimeString());
    }

    public function test_pages_show_times_in_ist(): void
    {
        $this->fakeTransports();
        $this->actingAs($this->makeUser(['timezone' => 'Asia/Kolkata']));
        $lead = $this->makeLead(['company_name' => 'Kolkata Co']);
        LeadEmail::create([
            'lead_id' => $lead->id, 'subject' => 'IST check', 'body' => 'x', 'status' => 'sent',
            'sent_at' => CarbonImmutable::parse('2026-03-04 13:15:00', 'UTC'),   // 18:45 IST
        ]);

        $this->get('/email-activity')->assertOk()->assertSee('IST check')->assertSee('06:45 PM');
    }
}
