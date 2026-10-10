<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Concerns\FiltersRequests;
use App\Http\Controllers\Concerns\RedirectsBack;
use App\Jobs\ProcessBulkJob;
use App\Models\Bulk;
use App\Models\BulkItem;
use App\Models\Category;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Services\BulkImportService;
use App\Services\EmailFinderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulks: run the Verifier or the Finder over a whole CSV.
 *
 * The upload is parsed and stored as bulk_items in the request (fast, and it
 * gives the user an immediate row count), then ProcessBulkJob works through
 * them on the queue in chunks. show() polls status() for live progress.
 *
 * Requires a running queue worker - docker-compose's `queue` service already
 * consumes the "default" queue this dispatches onto.
 */
class BulkController extends Controller
{
    use ExportsCsv, FiltersRequests, RedirectsBack;

    /** Every result_status a bulk item can carry, across both run types. */
    private const RESULT_STATUSES = ['valid', 'risky', 'invalid', 'unknown', 'found', 'not_found', 'imported', 'updated', 'duplicate', 'done', 'skipped'];

    /** Run types a user can start from the upload form (sequence actions are started elsewhere). */
    private const UPLOAD_TYPES = [Bulk::TYPE_VERIFY, Bulk::TYPE_FIND, Bulk::TYPE_IMPORT];

    public function __construct(
        private readonly EmailFinderService $finder,
        private readonly BulkImportService $importer,
    ) {}

    /** Rows read from a CSV before the rest is ignored. */
    private function maxRows(): int
    {
        return max(1, (int) config('services.email_verifier.bulk_max_rows', 5000));
    }

    /** List every run, newest first, with its progress. */
    public function index(Request $request): View
    {
        // No withCount here: total_records already carries the row count, so a
        // per-row subquery would buy nothing.
        $query = Bulk::query()->orderByDesc('id');

        if ($type = $this->enumParam($request, 'type', [...self::UPLOAD_TYPES, ...Bulk::SEQUENCE_ACTIONS])) {
            $query->where('type', $type);
        }

        $status = $this->enumParam($request, 'status', [
            Bulk::STATUS_DRAFT, Bulk::STATUS_PENDING, Bulk::STATUS_PROCESSING,
            Bulk::STATUS_COMPLETED, Bulk::STATUS_FAILED, Bulk::STATUS_CANCELLED,
        ]);

        if ($status !== '') {
            $query->where('status', $status);
        }

        $data = [
            'bulks'      => $query->paginate($this->perPage($request))->withQueryString(),
            'categories' => Category::active()->orderBy('name')->get(),
            'totals'     => $this->totals(),
            'filters'    => ['type' => $type, 'status' => $status],
        ];

        if ($request->ajax()) {
            return view('bulks._table', $data);
        }

        return view('bulks.index', $data);
    }

    /**
     * Accept a CSV upload and queue it.
     *
     * The file is read with fgetcsv rather than loaded into memory whole, so a
     * large upload costs the same as a small one.
     */
    public function store(Request $request): RedirectResponse
    {
        // Importing leads has its own flow: upload -> preview + column mapping -> confirm.
        if ($request->input('type') === Bulk::TYPE_IMPORT) {
            return $this->storeImport($request);
        }

        $validated = $request->validate([
            'name'        => ['nullable', 'string', 'max:120'],
            'type'        => ['required', 'in:verify,find'],
            // txt is allowed because a one-column list is often saved that way.
            'file'        => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'has_header'  => ['nullable', 'boolean'],
            // 1-based column index chosen in the upload form.
            'column'      => ['nullable', 'integer', 'min:1', 'max:50'],
            'name_column' => ['nullable', 'integer', 'min:1', 'max:50'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        $file      = $request->file('file');
        $column    = ((int) ($validated['column'] ?? 1)) - 1;
        $extraCol  = ! empty($validated['name_column']) ? ((int) $validated['name_column']) - 1 : null;
        $hasHeader = $request->boolean('has_header');
        $isFind    = $validated['type'] === Bulk::TYPE_FIND;

        try {
            $rows = $this->readCsv($file->getRealPath(), $column, $extraCol, $hasHeader, $isFind);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'That file could not be read. Please upload a valid CSV.')->withInput();
        }

        if ($rows === []) {
            return back()
                ->with('error', $isFind
                    ? 'No usable domains found in that column. Check the column number and whether the first row is a header.'
                    : 'No usable email addresses found in that column. Check the column number and whether the first row is a header.')
                ->withInput();
        }

        // Keep the original upload so the run can be traced back to its source.
        $storedPath = $file->store('bulk-uploads', 'local');

        $bulk = Bulk::create([
            'name'              => ($validated['name'] ?? null) ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME),
            'type'              => $validated['type'],
            'category_id'       => $isFind ? ($validated['category_id'] ?? null) : null,
            'original_filename' => $file->getClientOriginalName(),
            'file_path'         => $storedPath,
            'status'            => Bulk::STATUS_PENDING,
            'total_records'     => count($rows),
        ]);

        $now = now();

        foreach (array_chunk($rows, 200) as $chunk) {
            BulkItem::insert(array_map(static fn (array $row) => [
                'bulk_id'    => $bulk->id,
                'input'      => $row['input'],
                'extra'      => $row['extra'],
                'status'     => BulkItem::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        ProcessBulkJob::dispatch($bulk->id)->onQueue('default');

        Log::info('BulkController: bulk queued', [
            'bulk_id' => $bulk->id,
            'type'    => $bulk->type,
            'rows'    => count($rows),
        ]);

        return redirect()
            ->route('bulks.show', $bulk->id)
            ->with('success', count($rows).' record(s) queued. Progress updates automatically below.');
    }

    /** Progress, counters and the paginated results table for one run. */
    public function show(Request $request, int $id): View
    {
        $bulk = Bulk::with('category')->findOrFail($id);

        $query = $bulk->items()->orderBy('id');

        if ($result = $this->enumParam($request, 'result', self::RESULT_STATUSES)) {
            $query->where('result_status', $result);
        }

        if ($term = $this->strParam($request, 'q')) {
            $escaped = $this->likePattern($term);
            // `extra` holds the lead's address on sequence-action runs (and the company on verify runs).
            $query->where(fn ($q) => $q->where('input', 'like', $escaped)
                ->orWhere('result_value', 'like', $escaped)
                ->orWhere('extra', 'like', $escaped));
        }

        $items = $query->paginate($this->perPage($request))->withQueryString();

        $data = [
            'bulk'       => $bulk,
            'items'      => $items,
            'itemLeads'  => $this->leadsForItems($bulk, $items),
            'breakdown'  => $this->breakdown($bulk),
            'filters'    => ['result' => $result, 'q' => $term],
            // A draft import shows its first rows and the column mapping to confirm.
            'preview'    => $bulk->isImport() && $bulk->status === Bulk::STATUS_DRAFT ? $this->importer->preview($bulk) : null,
            'importFields' => [...array_keys(BulkImportService::ALIASES), 'custom'],
        ];

        if ($request->ajax()) {
            return view('bulks._items', $data);
        }

        return view('bulks.show', $data);
    }

    /**
     * The lead behind each row of a sequence-action run, so the table can name it
     * instead of showing a bare id. Enroll / unsubscribe rows hold a lead id;
     * pause / resume / remove rows hold an enrollment id.
     *
     * @return array<int, Lead>  keyed by bulk item id
     */
    private function leadsForItems(Bulk $bulk, $items): array
    {
        if (! $bulk->isSequenceAction() || $items->isEmpty()) {
            return [];
        }

        $ids = $items->pluck('input')->map(fn ($id) => (int) $id)->all();

        $leads = in_array($bulk->type, [Bulk::TYPE_ENROLL, Bulk::TYPE_UNSUBSCRIBE], true)
            ? Lead::whereIn('id', $ids)->get()->keyBy('id')
            : SequenceEnrollment::with('lead')->whereIn('id', $ids)->get()->mapWithKeys(fn ($e) => [$e->id => $e->lead]);

        $map = [];
        foreach ($items as $item) {
            if ($lead = $leads[(int) $item->input] ?? null) {
                $map[$item->id] = $lead;
            }
        }

        return $map;
    }

    /**
     * Live progress for the polling front end. Kept deliberately small - it is
     * requested every few seconds while a run is in flight.
     */
    public function status(int $id): JsonResponse
    {
        $bulk = Bulk::findOrFail($id);

        return response()->json([
            'id'         => $bulk->id,
            'status'     => $bulk->status,
            'running'    => $bulk->isRunning(),
            'progress'   => $bulk->progress,
            'total'      => $bulk->total_records,
            'processed'  => $bulk->processed_records,
            'successful' => $bulk->successful_records,
            'failed'     => $bulk->failed_records,
            'breakdown'  => $this->breakdown($bulk),
            'error'      => $bulk->error,
        ]);
    }

    /**
     * Step 1 of a lead import: store the CSV as a draft run and show the preview.
     * Nothing is imported until the mapping is confirmed.
     */
    private function storeImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => ['nullable', 'string', 'max:120'],
            'file'            => ['required', 'file', 'mimes:csv,txt', 'max:'.config('sequencer.import.max_upload_kb', 20480)],
            'category_id'     => ['nullable', 'exists:categories,id'],
            'update_existing' => ['nullable', 'boolean'],
        ]);

        $bulk = $this->importer->createDraft(
            $request->file('file'),
            ! empty($validated['category_id']) ? Category::find($validated['category_id']) : null,
            $request->boolean('update_existing'),
            $validated['name'] ?? null,
        );

        return redirect()->route('bulks.show', $bulk->id)
            ->with('success', "{$bulk->total_records} row(s) read. Check the column mapping below, then confirm the import.");
    }

    /** Step 2 of a lead import: save the column mapping and queue the run. */
    public function confirmImport(Request $request, int $id): RedirectResponse
    {
        $bulk = Bulk::where('type', Bulk::TYPE_IMPORT)->findOrFail($id);

        $data = $request->validate([
            'mapping'         => ['required', 'array'],
            'mapping.*'       => ['nullable', 'string', 'max:20'],
            'update_existing' => ['nullable', 'boolean'],
        ]);

        $wasDraft = $bulk->status === Bulk::STATUS_DRAFT;
        $this->importer->confirm($bulk, $data['mapping'], $request->has('update_existing') ? $request->boolean('update_existing') : null);

        if ($wasDraft) {   // a double submit must not queue the run twice
            ProcessBulkJob::dispatch($bulk->id)->onQueue('default');
        }

        return redirect()->route('bulks.show', $bulk->id)
            ->with('success', 'Import queued. Progress updates automatically below; you can leave this page.');
    }

    /** Stop a run. Items already processed keep their results. */
    public function cancel(Request $request, int $id): RedirectResponse
    {
        $bulk = Bulk::findOrFail($id);

        if (! $bulk->isRunning()) {
            return back()->with('error', 'That run has already finished.');
        }

        // ProcessBulkJob checks isRunning() before each chunk, so flipping the
        // status here is enough to stop it at the next chunk boundary.
        $bulk->update(['status' => Bulk::STATUS_CANCELLED, 'completed_at' => now()]);

        return back()->with('success', 'Run cancelled. Results collected so far are kept.');
    }

    /** Re-queue every item that has not produced a result yet. */
    public function retry(int $id): RedirectResponse
    {
        $bulk = Bulk::findOrFail($id);

        if ($bulk->isImport()) {
            return back()->with('error', 'An import cannot be retried. Upload the corrected file as a new import; existing leads are skipped.');
        }

        $reset = $bulk->items()
            ->whereIn('status', [BulkItem::STATUS_FAILED, BulkItem::STATUS_PROCESSING])
            ->update([
                'status'        => BulkItem::STATUS_PENDING,
                'result_status' => null,
                'message'       => null,
            ]);

        if ($reset === 0 && ! $bulk->items()->where('status', BulkItem::STATUS_PENDING)->exists()) {
            return back()->with('error', 'Nothing left to retry - every record already has a result.');
        }

        $bulk->update([
            'status'       => Bulk::STATUS_PENDING,
            'error'        => null,
            'completed_at' => null,
        ]);

        ProcessBulkJob::dispatch($bulk->id)->onQueue('default');

        return back()->with('success', "{$reset} record(s) re-queued.");
    }

    /** Export one run's results. */
    public function export(int $id): StreamedResponse
    {
        $bulk   = Bulk::findOrFail($id);
        $isFind = $bulk->type === Bulk::TYPE_FIND;

        // Imports and sequence actions: one line per row / record with what happened to it.
        if ($bulk->isImport() || $bulk->isSequenceAction()) {
            return $this->streamCsv(
                'bulk-'.$bulk->id.'-results',
                [$bulk->isImport() ? 'Email' : 'Record ID', $bulk->isImport() ? 'Company' : 'Email', 'Result', 'Notes'],
                $bulk->items()->orderBy('id')->cursor(),
                fn (BulkItem $item) => [$item->input, $item->extra, $item->result_status, $item->message],
            );
        }

        return $this->streamCsv(
            'bulk-'.$bulk->id.'-results',
            $isFind
                ? ['Domain', 'Company', 'Email Found', 'Result', 'Score', 'Notes']
                : ['Email', 'Result', 'Score', 'Notes'],
            $bulk->items()->orderBy('id')->cursor(),
            fn (BulkItem $item) => $isFind
                ? [$item->input, $item->extra, $item->result_value, $item->result_status, $item->score, $item->message]
                : [$item->input, $item->result_status, $item->score, $item->message],
        );
    }

    /** Delete a run, its items, and the stored upload. */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $bulk = Bulk::findOrFail($id);

        if ($bulk->file_path && Storage::disk('local')->exists($bulk->file_path)) {
            Storage::disk('local')->delete($bulk->file_path);
        }

        // bulk_items and email_verifications cascade on the foreign key.
        $bulk->delete();

        return redirect()
            ->route('bulks.index', $this->redirectQuery($request))
            ->with('success', 'Bulk run deleted.');
    }

    /**
     * Read one column out of a CSV, cleaning and de-duplicating as it goes.
     *
     * @param  int|null  $extraCol  optional second column (company / person name)
     * @return array<int, array{input:string, extra:?string}>
     */
    private function readCsv(string $path, int $column, ?int $extraCol, bool $hasHeader, bool $isFind): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Could not open the uploaded file.");
        }

        $rows    = [];
        $seen    = [];
        $lineNo  = 0;
        $maxRows = $this->maxRows();

        try {
            while (($cells = fgetcsv($handle)) !== false) {
                $lineNo++;

                if ($hasHeader && $lineNo === 1) {
                    continue;
                }

                if ($cells === [null] || $cells === false) {
                    continue; // blank line
                }

                $value = trim((string) ($cells[$column] ?? ''));

                // Strip a UTF-8 BOM off the very first cell of the file.
                if ($lineNo === 1) {
                    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
                }

                if ($value === '') {
                    continue;
                }

                // Normalise, and drop anything that cannot possibly work: an
                // unusable row is better rejected here than counted as a
                // "failed record" later.
                $value = $isFind
                    ? $this->finder->normaliseDomain($value)
                    : strtolower($value);

                if ($value === '' || (! $isFind && ! str_contains($value, '@'))) {
                    continue;
                }

                if (isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;

                $extra = $extraCol !== null ? trim((string) ($cells[$extraCol] ?? '')) : null;

                $rows[] = ['input' => mb_substr($value, 0, 255), 'extra' => $extra ? mb_substr($extra, 0, 255) : null];

                if (count($rows) >= $maxRows) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Per-result-status counts for one run, used by the detail page's chips and
     * the polling endpoint.
     *
     * @return array<string,int>
     */
    private function breakdown(Bulk $bulk): array
    {
        $counts = $bulk->items()
            ->select('result_status', DB::raw('COUNT(*) as total'))
            ->whereNotNull('result_status')
            ->groupBy('result_status')
            ->pluck('total', 'result_status');

        $keys = match (true) {
            $bulk->type === Bulk::TYPE_FIND => ['found', 'not_found', 'unknown'],
            $bulk->isImport() => ['imported', 'updated', 'duplicate', 'invalid'],
            $bulk->isSequenceAction() => ['done', 'skipped'],
            default => ['valid', 'risky', 'invalid', 'unknown'],
        };

        $out = [];

        foreach ($keys as $key) {
            $out[$key] = (int) ($counts[$key] ?? 0);
        }

        $out['pending'] = (int) $bulk->items()->where('status', BulkItem::STATUS_PENDING)->count();

        return $out;
    }

    /** @return array<string,int> headline figures for the index stat cards */
    private function totals(): array
    {
        $byStatus = Bulk::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'runs'       => (int) $byStatus->sum(),
            'running'    => (int) ($byStatus[Bulk::STATUS_PENDING] ?? 0) + (int) ($byStatus[Bulk::STATUS_PROCESSING] ?? 0),
            'completed'  => (int) ($byStatus[Bulk::STATUS_COMPLETED] ?? 0),
            'records'    => (int) Bulk::sum('total_records'),
            'successful' => (int) Bulk::sum('successful_records'),
        ];
    }
}
