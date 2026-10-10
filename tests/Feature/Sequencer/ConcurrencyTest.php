<?php

namespace Tests\Feature\Sequencer;

use App\Models\DailySendCounter;
use App\Models\LeadEmail;
use App\Models\SequenceEnrollment;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesSequencerData;
use Tests\TestCase;

/**
 * Real concurrency: several OS processes, each with its own MySQL connection, hit the
 * send pipeline at the same instant. Data is committed (no wrapping transaction) so the
 * workers can see it.
 */
class ConcurrencyTest extends TestCase
{
    use CreatesSequencerData, DatabaseTruncation;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeTransports();
        $this->dir = sys_get_temp_dir().'/seq-race-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        // This class commits real rows (worker processes must see them). Leave the schema empty
        // so the transaction-wrapped tests that follow start from a clean database.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    /**
     * @param  list<string>  $idLists  one comma separated id list per worker process
     * @return array{outputs: list<list<string>>, sends: list<string>}
     */
    private function race(array $idLists, array $env = []): array
    {
        $go = $this->dir.'/go';
        $log = $this->dir.'/sends.log';
        touch($log);

        $procs = [];
        foreach ($idLists as $i => $ids) {
            $cmd = [PHP_BINARY, base_path('tests/Support/race_worker.php'), $go, $log, $ids];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), $env));
            $this->assertIsResource($proc, 'could not start worker '.$i);
            $procs[] = [$proc, $pipes];
        }

        usleep(1_500_000);          // let every worker boot and reach the starting line
        touch($go);                 // ...then release them all at once

        $outputs = [];
        foreach ($procs as [$proc, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            proc_close($proc);
            $errors = array_filter(explode("\n", $stdout.$stderr), fn ($l) => str_contains($l, 'ERROR'));
            $this->assertSame([], array_values($errors), "worker failed:\n".implode("\n", array_slice($errors, 0, 3)));
            $this->assertStringNotContainsString('Fatal', $stderr, $stderr);
            $outputs[] = array_values(array_filter(explode("\n", trim($stdout))));
        }

        return ['outputs' => $outputs, 'sends' => array_values(array_filter(explode("\n", trim((string) file_get_contents($log)))))];
    }

    public function test_many_workers_racing_for_one_enrollment_send_exactly_one_email(): void
    {
        $this->makeAccount();
        $sequence = $this->makeSequence([['days' => 0], ['days' => 1]]);
        $enrollment = $this->enroll($sequence, $this->makeLead());

        $result = $this->race(array_fill(0, 8, (string) $enrollment->id));

        $this->assertCount(1, $result['sends'], "Exactly one SMTP delivery expected, got:\n".implode("\n", $result['sends']));
        $this->assertSame(1, LeadEmail::count());
        $this->assertSame('sent', LeadEmail::first()->status);
        $this->assertSame(1, $enrollment->refresh()->current_step);

        $all = array_merge(...$result['outputs']);
        $this->assertSame(1, count(array_filter($all, fn ($o) => $o === 'sent')), 'One worker won: '.implode(',', $all));
    }

    public function test_many_workers_cannot_exceed_the_daily_limit(): void
    {
        $account = $this->makeAccount();
        $sequence = $this->makeSequence([['days' => 0]], ['daily_limit' => 20]);

        $ids = [];
        for ($i = 0; $i < 60; $i++) {
            $ids[] = $this->enroll($sequence, $this->makeLead(), $account)->id;
        }

        // 10 workers, each walking all 60 enrollments in a different order.
        $lists = [];
        for ($w = 0; $w < 10; $w++) {
            $shuffled = $ids;
            mt_srand($w + 7);
            shuffle($shuffled);
            $lists[] = implode(',', $shuffled);
        }

        $result = $this->race($lists);

        $this->assertCount(20, $result['sends'], 'The daily limit of 20 must hold across 10 concurrent workers.');
        $this->assertCount(20, array_unique($result['sends']), 'No message was delivered twice.');
        $this->assertSame(20, LeadEmail::count());
        $this->assertSame(20, SequenceEnrollment::where('status', 'completed')->count());
        $this->assertSame(20, (int) DailySendCounter::where('scope', 'sequence')->sum('count'));
        $this->assertSame(40, SequenceEnrollment::where('status', 'active')->count(), 'The rest wait for tomorrow.');
    }

    public function test_the_per_minute_rate_limit_holds_across_workers(): void
    {
        try {
            $redis = Cache::store('redis');
            $redis->put('probe', 1, 5);
        } catch (\Throwable) {
            $this->markTestSkipped('Redis is not reachable.');
        }

        $account = $this->makeAccount(['rate_limit_per_minute' => 5]);
        $sequence = $this->makeSequence([['days' => 0]]);

        $minute = now()->format('YmdHi');
        $redis->forget("sequencer:rate:{$account->id}:{$minute}");

        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $ids[] = $this->enroll($sequence, $this->makeLead(), $account)->id;
        }

        // The workers share one cache, as in production (CACHE_STORE=redis).
        $result = $this->race(array_fill(0, 5, implode(',', $ids)), ['CACHE_STORE' => 'redis']);

        $sentThisMinute = count($result['sends']);
        // If the wall clock rolled into a new minute mid-test a second batch of 5 is legitimate.
        $minutes = now()->format('YmdHi') === $minute ? 1 : 2;

        $this->assertLessThanOrEqual(5 * $minutes, $sentThisMinute, 'Rate limit of 5/minute exceeded: '.$sentThisMinute);
        $this->assertGreaterThan(0, $sentThisMinute);
        $this->assertCount($sentThisMinute, array_unique($result['sends']));

        $redis->forget("sequencer:rate:{$account->id}:{$minute}");
    }
}
