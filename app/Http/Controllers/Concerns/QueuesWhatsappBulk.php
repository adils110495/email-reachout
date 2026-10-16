<?php

namespace App\Http\Controllers\Concerns;

use App\Services\WhatsappBulkService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * "Bulk WhatsApp" on a list screen (GMB Leads, Deals): the selected rows, or
 * every row matching the list's current filters, get one approved template.
 *
 * The list controller passes its own filtered query, so "all matching" means
 * exactly what the user is looking at. Queuing itself is WhatsappBulkService.
 */
trait QueuesWhatsappBulk
{
    /**
     * @param  Builder   $query      the list's rows - filtered when "all" is posted
     * @param  callable  $toContact  Model -> ['name', 'phone', 'gmb_lead_id' | 'cash_lead_id']
     */
    protected function queueWhatsappBulk(Request $request, Builder $query, callable $toContact): JsonResponse
    {
        $max = max(1, (int) config('services.whatsapp.bulk_max', 250));

        $data = $request->validate([
            'all'               => ['nullable', 'boolean'],
            'ids'               => ['exclude_if:all,1', 'required', 'array', 'max:' . $max],
            'ids.*'             => ['integer'],
            'template_name'     => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['required', 'string', 'max:15', 'regex:/^[A-Za-z0-9_]+$/'],
            'template_header'   => ['nullable', 'string', 'max:1024'],
            'template_body'     => ['nullable', 'string', 'max:4096'],
            'params'            => ['nullable', 'array', 'max:20'],
            'params.*'          => ['required', 'string', 'max:1024'],
            'interval'          => ['required', 'integer', Rule::in(WhatsappBulkService::INTERVALS)],
            'skip_contacted'    => ['nullable', 'boolean'],
        ], [
            'ids.required' => 'Select at least one contact.',
            'ids.max'      => "One bulk send can have at most {$max} contacts.",
        ]);

        foreach (array_keys($data['params'] ?? []) as $key) {
            if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $key)) {
                return $this->bulkFail('Invalid template variable.');
            }
        }

        if (! $request->boolean('all')) {
            $query->whereKey(array_map('intval', $data['ids']));
        }

        // Only rows that have a number at all; the owner scope keeps it to rows the user may see.
        $query->whereNotNull('phone')->where('phone', '!=', '');

        $count = (clone $query)->count();

        if ($count === 0) {
            return $this->bulkFail('None of these contacts has a phone number.');
        }

        if ($count > $max) {
            return $this->bulkFail("{$count} contacts match - one bulk send can have at most {$max}. Narrow the filters and send in parts.");
        }

        $records = $query->get();
        $owners  = $records->pluck('user_id')->unique();

        // Each message goes out from its owner's WhatsApp number, so one bulk
        // send cannot span two users' workspaces.
        if ($owners->count() > 1) {
            return $this->bulkFail("These contacts belong to different users. Select one user's contacts at a time.");
        }

        try {
            $summary = $this->access()->runAs((int) $owners->first(), fn () => app(WhatsappBulkService::class)->queue(
                $records->map(fn (Model $record) => $toContact($record)),
                [
                    'name'     => $data['template_name'],
                    'language' => $data['template_language'],
                    'header'   => $data['template_header'] ?? null,
                    'body'     => $data['template_body'] ?? null,
                    'params'   => $data['params'] ?? [],
                ],
                (int) $data['interval'],
                $request->boolean('skip_contacted'),
            ));
        } catch (RuntimeException $e) {
            return $this->bulkFail($e->getMessage());
        }

        $skipped = array_filter([
            $summary['already_messaged'] ? "{$summary['already_messaged']} already messaged" : null,
            $summary['no_number'] ? "{$summary['no_number']} without a usable number" : null,
            $summary['duplicate'] ? "{$summary['duplicate']} with a repeated number" : null,
        ]);

        if ($summary['queued'] === 0) {
            return $this->bulkFail('Nothing to send - ' . implode(', ', $skipped) . '.');
        }

        $minutes = (int) ceil(($summary['queued'] - 1) * (int) $data['interval'] / 60);

        return response()->json([
            'ok'      => true,
            'message' => "{$summary['queued']} WhatsApp message" . ($summary['queued'] === 1 ? '' : 's') . ' queued'
                . ($minutes > 0 ? ", one every {$data['interval']}s (about {$minutes} min)" : '') . '.'
                . ($skipped ? ' Skipped: ' . implode(', ', $skipped) . '.' : ''),
            'url'     => route('whatsapp.index', ['batch' => $summary['batch_id']]),
        ]);
    }

    private function bulkFail(string $message): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], 422);
    }
}
