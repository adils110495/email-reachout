<?php

namespace Tests\Unit;

use App\Models\Sequence;
use App\Sequencer\Services\SendingWindowService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Tests\TestCase;

class SendingWindowTest extends TestCase
{
    private SendingWindowService $window;

    protected function setUp(): void
    {
        parent::setUp();
        $this->window = new SendingWindowService;
    }

    private function sequence(array $over = []): Sequence
    {
        return new Sequence($over + [
            'timezone' => 'UTC', 'sending_start_time' => '09:00:00', 'sending_end_time' => '17:00:00', 'sending_days' => [1, 2, 3, 4, 5],
        ]);
    }

    private function at(string $time, string $tz = 'UTC'): CarbonImmutable
    {
        return CarbonImmutable::parse($time, $tz);
    }

    /** The instant as UTC wall-clock time, so expectations read the same in any app timezone. */
    private function utc(CarbonInterface $at): string
    {
        return $at->copy()->utc()->toDateTimeString();
    }

    public function test_inside_the_window_the_time_is_unchanged(): void
    {
        $seq = $this->sequence();
        $t = $this->at('2026-03-04 11:30:00');   // Wednesday

        $this->assertTrue($this->window->isWithinWindow($seq, $t));
        $this->assertTrue($t->equalTo($this->window->nextAllowed($seq, $t)));
    }

    public function test_before_opening_moves_to_opening_the_same_day(): void
    {
        $this->assertSame('2026-03-04 09:00:00', $this->utc($this->window->nextAllowed($this->sequence(), $this->at('2026-03-04 06:15:00'))));
    }

    public function test_after_closing_moves_to_opening_the_next_allowed_day(): void
    {
        $seq = $this->sequence();

        $this->assertSame('2026-03-05 09:00:00', $this->utc($this->window->nextAllowed($seq, $this->at('2026-03-04 17:00:00'))), 'End is exclusive.');
        $this->assertSame('2026-03-05 09:00:00', $this->utc($this->window->nextAllowed($seq, $this->at('2026-03-04 22:00:00'))));
    }

    public function test_weekends_are_skipped(): void
    {
        $seq = $this->sequence();

        $this->assertFalse($this->window->isWithinWindow($seq, $this->at('2026-03-07 12:00:00')));   // Saturday
        $this->assertSame('2026-03-09 09:00:00', $this->utc($this->window->nextAllowed($seq, $this->at('2026-03-06 18:00:00'))));   // Fri evening -> Mon
        $this->assertSame('2026-03-09 09:00:00', $this->utc($this->window->nextAllowed($seq, $this->at('2026-03-08 12:00:00'))));   // Sunday -> Mon
    }

    public function test_the_sequence_timezone_decides_not_utc(): void
    {
        $seq = $this->sequence(['timezone' => 'Asia/Kolkata']);   // UTC+5:30

        // 04:00 UTC = 09:30 in Kolkata: inside the window.
        $this->assertTrue($this->window->isWithinWindow($seq, $this->at('2026-03-04 04:00:00')));
        // 12:00 UTC = 17:30 in Kolkata: closed, next opening is 09:00 IST = 03:30 UTC the next day.
        $next = $this->window->nextAllowed($seq, $this->at('2026-03-04 12:00:00'));
        $this->assertSame('2026-03-05 03:30:00', $this->utc($next));
    }

    public function test_results_come_back_in_the_app_timezone_ready_to_store(): void
    {
        $seq = $this->sequence(['timezone' => 'America/New_York']);

        $next = $this->window->nextAllowed($seq, $this->at('2026-03-04 22:00:00'));   // 17:00 in New York: closed

        $this->assertSame(config('app.timezone'), $next->timezoneName);
        $this->assertSame('2026-03-05 14:00:00', $this->utc($next));                 // 09:00 EST
        $this->assertSame(config('app.timezone'), $this->window->startOfNextDay($seq, $this->at('2026-03-04 03:00:00'))->timezoneName);
    }

    public function test_weekday_is_evaluated_in_the_sequence_timezone(): void
    {
        $seq = $this->sequence(['timezone' => 'Pacific/Auckland']);   // UTC+13 in March

        // Friday 20:00 UTC is already Saturday 09:00 in Auckland: closed (Saturday).
        $this->assertFalse($this->window->isWithinWindow($seq, $this->at('2026-03-06 20:00:00')));
    }

    public function test_custom_days_and_hours(): void
    {
        $seq = $this->sequence(['sending_days' => [6, 7], 'sending_start_time' => '10:00:00', 'sending_end_time' => '12:00:00']);

        $this->assertTrue($this->window->isWithinWindow($seq, $this->at('2026-03-07 11:00:00')));
        $this->assertFalse($this->window->isWithinWindow($seq, $this->at('2026-03-04 11:00:00')));
        $this->assertSame('2026-03-07 10:00:00', $this->utc($this->window->nextAllowed($seq, $this->at('2026-03-04 11:00:00'))));
    }

    public function test_a_degenerate_configuration_never_loops_or_blocks_forever(): void
    {
        $emptyDays = $this->sequence(['sending_days' => []]);                       // falls back to the defaults
        $inverted = $this->sequence(['sending_start_time' => '17:00:00', 'sending_end_time' => '09:00:00']);   // treated as all day

        $this->assertSame('2026-03-09 09:00:00', $this->utc($this->window->nextAllowed($emptyDays, $this->at('2026-03-07 12:00:00'))));
        $this->assertTrue($this->window->isWithinWindow($inverted, $this->at('2026-03-04 03:00:00')));
    }

    public function test_day_key_and_start_of_next_day_use_the_sequence_timezone(): void
    {
        $seq = $this->sequence(['timezone' => 'America/New_York']);
        $t = $this->at('2026-03-04 03:00:00');   // 22:00 on the 3rd in New York

        $this->assertSame('2026-03-03', $this->window->dayKey($seq, $t));
        $this->assertSame('2026-03-04 05:00:00', $this->utc($this->window->startOfNextDay($seq, $t)));   // 00:00 EST = 05:00 UTC
    }
}
