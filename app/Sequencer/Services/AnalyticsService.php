<?php

namespace App\Sequencer\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Enums\EmailLogStatus;
use App\Sequencer\Enums\EnrollmentStatus;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Numbers for the dashboard's outreach section and per-sequence analytics. */
class AnalyticsService
{
    /** Delivery states that mean "it left our server" (bounced included: it was attempted). */
    private const DELIVERED = ['sent', 'bounced'];

    /** A percentage with one decimal; 0 when there is nothing to divide by. */
    public static function percent(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }

    /** @return array<string, int|float> */
    public function outreachTotals(): array
    {
        $emails = fn (): Builder => LeadEmail::query();
        $delivered = $emails()->whereIn('status', self::DELIVERED)->count();

        $totals = [
            'sequences' => Sequence::where('status', '!=', SequenceStatus::Archived->value)->count(),
            'active_sequences' => Sequence::where('status', SequenceStatus::Active->value)->count(),
            'enrolled' => SequenceEnrollment::whereIn('status', array_map(fn ($s) => $s->value, EnrollmentStatus::open()))->count(),
            'scheduled' => SequenceEnrollment::where('status', EnrollmentStatus::Active->value)->whereNotNull('next_action_at')->count(),
            'sent' => $emails()->whereNotNull('sent_at')->where('status', '!=', 'failed')->count(),
            'delivered' => $delivered,
            'opened' => $emails()->where('open_count', '>', 0)->count(),
            'clicked' => $emails()->whereNotNull('clicked_at')->count(),
            'replies' => $emails()->whereNotNull('replied_at')->count(),
            'bounces' => $emails()->where('status', EmailLogStatus::Bounced->value)->count(),
            'unsubscribes' => Lead::where('contact_status', ContactStatus::Unsubscribed->value)->count(),
        ];

        return $totals + [
            'open_rate' => self::percent($totals['opened'], $delivered),
            'click_rate' => self::percent($totals['clicked'], $delivered),
            'reply_rate' => self::percent($totals['replies'], $delivered),
            'bounce_rate' => self::percent($totals['bounces'], $delivered),
        ];
    }

    /**
     * Daily sent / opened / clicked / replied / bounced, bucketed in $timezone.
     *
     * @return array{labels: list<string>, sent: list<int>, opened: list<int>, clicked: list<int>, replied: list<int>, bounced: list<int>}
     */
    public function dailySeries(string $timezone, int $days = 14): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = $today->subDays($days - 1);
        // MySQL hands timestamps back in the app timezone (the session's); shift them into
        // $timezone before taking the date - no shift at all when the two are the same. A
        // numeric offset avoids needing MySQL's named-timezone tables; across a DST change a
        // few hours of one day land in its neighbour, acceptable for a chart.
        $offset = ($today->utcOffset() - Tz::app($today)->utcOffset()) * 60;

        $columns = ['sent' => 'sent_at', 'opened' => 'first_opened_at', 'clicked' => 'clicked_at', 'replied' => 'replied_at', 'bounced' => 'bounced_at'];
        $series = ['labels' => []] + array_fill_keys(array_keys($columns), []);

        $buckets = [];
        foreach ($columns as $key => $column) {
            $buckets[$key] = LeadEmail::query()
                ->where($column, '>=', Tz::app($start))
                ->when($key === 'sent', fn ($q) => $q->where('status', '!=', 'failed'))
                ->select(DB::raw("DATE(DATE_ADD($column, INTERVAL {$offset} SECOND)) as day"), DB::raw('COUNT(*) as total'))
                ->groupBy('day')
                ->pluck('total', 'day')
                ->all();
        }

        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i);
            $series['labels'][] = $day->format('j M');
            foreach (array_keys($columns) as $key) {
                $series[$key][] = (int) ($buckets[$key][$day->toDateString()] ?? 0);
            }
        }

        return $series;
    }

    /**
     * Per-sequence numbers: enrollment states, engagement rates, per-step funnel.
     *
     * @return array<string, mixed>
     */
    public function sequence(Sequence $sequence): array
    {
        $byStatus = SequenceEnrollment::where('sequence_id', $sequence->id)
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all();

        $enrollments = ['total' => array_sum($byStatus)];
        foreach (EnrollmentStatus::cases() as $status) {
            $enrollments[$status->value] = (int) ($byStatus[$status->value] ?? 0);
        }

        $emails = $this->engagement(fn (): Builder => LeadEmail::where('sequence_id', $sequence->id));

        $rates = [
            'open' => self::percent($emails['opened'], $emails['attempts']),
            'click' => self::percent($emails['clicked'], $emails['attempts']),
            'reply' => self::percent($emails['replied'], $emails['attempts']),
            'bounce' => self::percent($emails['bounced'], $emails['attempts']),
        ];

        $steps = $sequence->steps->map(fn ($step) => ['step' => $step]
            + $this->engagement(fn (): Builder => LeadEmail::where('sequence_step_id', $step->id)))->all();

        return compact('enrollments', 'emails', 'rates', 'steps');
    }

    /** @param  callable(): Builder  $q */
    private function engagement(callable $q): array
    {
        return [
            'attempts' => $q()->whereIn('status', self::DELIVERED)->count(),
            'sent' => $q()->where('status', EmailLogStatus::Sent->value)->count(),
            'opened' => $q()->where('open_count', '>', 0)->count(),
            'clicked' => $q()->whereNotNull('clicked_at')->count(),
            'replied' => $q()->whereNotNull('replied_at')->count(),
            'bounced' => $q()->where('status', EmailLogStatus::Bounced->value)->count(),
            'failed' => $q()->where('status', EmailLogStatus::Failed->value)->count(),
        ];
    }
}
