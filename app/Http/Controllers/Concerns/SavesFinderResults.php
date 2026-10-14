<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FinderResult;
use App\Models\Lead;
use App\Models\Platform;

/**
 * Filing of Finder results: the per-search result rows and the one lead a
 * search may create. Shared by the Finder page (explicit Save) and the
 * background FinderSearchJob so both behave identically.
 */
trait SavesFinderResults
{
    /**
     * Write one search into the Finder's own table: one row per website.
     *
     * Every address found goes into the row's `emails` JSON list; `email` and
     * the verdict columns describe the best-ranked candidate. Searching the same
     * domain again adds any new addresses to the list rather than a new row.
     *
     * @param  array<string,mixed>       $result  what EmailFinderService returned
     * @param  array<string,mixed>|null  $saved   the lead created, if any
     */
    protected function recordResults(array $result, string $mode, ?array $saved): void
    {
        $best = $result['candidates'][0] ?? null;

        if (! $best) {
            return;
        }

        $row = FinderResult::firstOrNew(['domain' => $result['domain']]);

        // Candidates arrive ranked, so the new best leads the list; addresses
        // from earlier searches are kept after it.
        $emails = Lead::normalizeEmails(array_merge(
            array_column($result['candidates'], 'email'),
            $row->email_list,
        ));

        // lead_id is only ever set here, never cleared - re-running a search
        // must not un-save anything.
        $row->fill([
            'mode'    => $mode,
            'person'  => $result['person'] ?? null,
            'company' => $result['company'] ?? $row->company,
            'email'   => strtolower($best['email']),
            'emails'  => $emails,
            'status'  => $best['status'],
            'score'   => $best['score'],
            'reason'  => $best['reason'],
            'source'  => $best['source'],
            'guessed' => $best['guessed'],
            'type'    => $best['type'],
            'pattern' => $best['pattern'],
        ]);

        if ($saved) {
            $row->lead_id = $saved['lead_id'];
        }

        $row->save();
    }

    /**
     * Record one discovered address against its company's lead.
     *
     * Shared by the explicit Save button and the automatic save that follows a
     * domain search, so both behave identically: one lead per website, and an
     * address the user already has is never overwritten.
     *
     * $email is the primary address; $others are the rest found on the same
     * website, which join the lead's `emails` list behind it.
     *
     * @param  array<int, string>  $others
     * @return array{ok:bool, created:bool, lead_id:int, email:string, message:string}
     */
    protected function saveLead(string $domain, string $email, ?string $companyName, ?int $categoryId, array $others = []): array
    {
        $website = 'https://'.$domain;
        $found   = Lead::normalizeEmails(array_merge([$email], $others));

        $lead = Lead::where('website', $website)
            ->orWhere('website', $website.'/')
            ->first();

        if ($lead) {
            // Existing addresses stay first, so the primary is never clobbered;
            // new ones are added after them.
            $merged = Lead::normalizeEmails(array_merge($lead->email_list, $found));

            if ($merged !== $lead->email_list) {
                $lead->update(['emails' => $merged]);
            }

            return [
                'ok'      => true,
                'created' => false,
                'lead_id' => $lead->id,
                'email'   => $lead->email,
                'message' => "Lead already existed - updated \"{$lead->company_name}\".",
            ];
        }

        $lead = Lead::create([
            'company_name' => $companyName ?: $domain,
            'website'      => $website,
            'emails'       => $found,
            'status'       => Lead::STATUS_NEW,
            'platform_id'  => Platform::where('name', 'Google')->value('id'),
            'category_id'  => $categoryId ?: null,
        ]);

        return [
            'ok'      => true,
            'created' => true,
            'lead_id' => $lead->id,
            'email'   => $lead->email,
            'message' => "Saved \"{$lead->company_name}\" to your leads.",
        ];
    }
}
