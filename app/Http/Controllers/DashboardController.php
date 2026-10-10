<?php

namespace App\Http\Controllers;

use App\Models\Bulk;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\EmailVerification;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Sequencer\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Landing page: a live overview of everything the other modules produce.
 *
 * Every figure is read from the database at request time - nothing here is
 * seeded or hardcoded. The heavier aggregates are grouped queries rather than
 * per-row counts so the page stays flat as the lead table grows.
 */
class DashboardController extends Controller
{
    /** Days covered by the activity chart. */
    private const ACTIVITY_DAYS = 14;

    public function index(): View
    {
        return view('dashboard.index', $this->metrics());
    }

    /** Same figures as JSON, so the page can refresh itself without a reload. */
    public function stats(): JsonResponse
    {
        $data = $this->metrics();

        return response()->json([
            'leads'         => $data['leadStats'],
            'emails'        => $data['emailStats'],
            'verifications' => $data['verifyStats'],
            'bulks'         => [
                'total'   => $data['bulkStats']['total'],
                'running' => $data['bulkStats']['running'],
            ],
            'outreach'      => $data['outreach'],
            'generated_at'  => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, mixed> */
    private function metrics(): array
    {
        // One grouped query for the whole status breakdown instead of four counts.
        $byStatus = Lead::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalLeads = (int) $byStatus->sum();
        $withEmail  = (int) Lead::withEmail()->count();

        $leadStats = [
            'total'      => $totalLeads,
            'new'        => (int) ($byStatus[Lead::STATUS_NEW] ?? 0),
            'sent'       => (int) ($byStatus[Lead::STATUS_SENT] ?? 0),
            'failed'     => (int) ($byStatus[Lead::STATUS_FAILED] ?? 0),
            'replied'    => (int) ($byStatus[Lead::STATUS_REPLIED] ?? 0),
            'with_email' => $withEmail,
            // What share of the list is actually contactable.
            'coverage'   => $totalLeads > 0 ? (int) round(($withEmail / $totalLeads) * 100) : 0,
        ];

        $emailsByStatus = LeadEmail::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $sentCount = (int) ($emailsByStatus['sent'] ?? 0);

        $emailStats = [
            'sent'      => $sentCount,
            'failed'    => (int) ($emailsByStatus['failed'] ?? 0),
            'today'     => (int) LeadEmail::where('status', 'sent')->whereDate('sent_at', today())->count(),
            'this_week' => (int) LeadEmail::where('status', 'sent')->where('sent_at', '>=', now()->subDays(7))->count(),
            // Replies are tracked on the lead, not the email row.
            'reply_rate' => $sentCount > 0
                ? (int) round(($leadStats['replied'] / $sentCount) * 100)
                : 0,
        ];

        $verifyByStatus = EmailVerification::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $verifyStats = [
            'total'   => (int) $verifyByStatus->sum(),
            'valid'   => (int) ($verifyByStatus[EmailVerification::STATUS_VALID] ?? 0),
            'risky'   => (int) ($verifyByStatus[EmailVerification::STATUS_RISKY] ?? 0),
            'invalid' => (int) ($verifyByStatus[EmailVerification::STATUS_INVALID] ?? 0),
            'unknown' => (int) ($verifyByStatus[EmailVerification::STATUS_UNKNOWN] ?? 0),
        ];

        $bulkStats = [
            'total'   => (int) Bulk::count(),
            'running' => (int) Bulk::whereIn('status', [Bulk::STATUS_PENDING, Bulk::STATUS_PROCESSING])->count(),
            // Named to match BulkController::totals(), so the same figure is
            // called the same thing in both modules.
            'records' => (int) Bulk::sum('processed_records'),
        ];

        return [
            'leadStats'      => $leadStats,
            'emailStats'     => $emailStats,
            'verifyStats'    => $verifyStats,
            'bulkStats'      => $bulkStats,
            'activity'       => $this->activity(),
            'topCategories'  => $this->topCategories(),
            'recentLeads'    => Lead::with('category')->latest('id')->limit(8)->get(),
            'runningBulks'   => Bulk::latest('id')->limit(5)->get(),
            'templateCount'  => (int) EmailTemplate::where('status', 'active')->count(),
            // Sequences: totals, rates and the daily engagement series (viewer's timezone).
            'outreach'       => app(AnalyticsService::class)->outreachTotals(),
            'engagement'     => app(AnalyticsService::class)->dailySeries(auth()->user()?->timezoneName() ?? config('app.timezone'), self::ACTIVITY_DAYS),
        ];
    }

    /**
     * Leads discovered vs emails sent, per day, for the last fortnight.
     *
     * Grouped in SQL, then replayed over a complete date range so days with no
     * activity still render as a zero rather than being skipped by the chart.
     *
     * @return array{labels:string[], leads:int[], emails:int[], max:int}
     */
    private function activity(): array
    {
        $from = today()->subDays(self::ACTIVITY_DAYS - 1);

        $leads = Lead::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('total', 'day');

        $emails = LeadEmail::query()
            ->select(DB::raw('DATE(sent_at) as day'), DB::raw('COUNT(*) as total'))
            ->where('status', 'sent')
            ->where('sent_at', '>=', $from)
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = $leadSeries = $emailSeries = [];

        for ($i = 0; $i < self::ACTIVITY_DAYS; $i++) {
            $date = $from->copy()->addDays($i);
            $key  = $date->toDateString();

            $labels[]      = $date->format('j M');
            $leadSeries[]  = (int) ($leads[$key] ?? 0);
            $emailSeries[] = (int) ($emails[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'leads'  => $leadSeries,
            'emails' => $emailSeries,
            // Shared scale for both series, and never 0 (bars divide by it).
            'max'    => max(1, max($leadSeries), max($emailSeries)),
        ];
    }

    /**
     * Busiest categories, with how many of their leads are contactable.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topCategories(): array
    {
        return Category::query()
            ->select('categories.id', 'categories.name')
            ->selectRaw('COUNT(leads.id) as leads_count')
            ->selectRaw("SUM(CASE WHEN leads.email IS NOT NULL AND leads.email != '' THEN 1 ELSE 0 END) as with_email")
            ->join('leads', 'leads.category_id', '=', 'categories.id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('leads_count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id'         => $row->id,
                'name'       => $row->name,
                'total'      => (int) $row->leads_count,
                'with_email' => (int) $row->with_email,
                'percent'    => $row->leads_count > 0
                    ? (int) round(($row->with_email / $row->leads_count) * 100)
                    : 0,
            ])
            ->all();
    }
}
