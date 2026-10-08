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
     * Write every candidate from one search into the Finder's own table.
     *
     * Keyed on (domain, email): searching the same company again refreshes each
     * address's score and verdict rather than stacking duplicate rows.
     *
     * @param  array<string,mixed>       $result  what EmailFinderService returned
     * @param  array<string,mixed>|null  $saved   the lead created, if any
     */
    protected function recordResults(array $result, string $mode, ?array $saved): void
    {
        $savedEmail = $saved ? strtolower((string) $saved['email']) : null;

        foreach ($result['candidates'] as $candidate) {
            // lead_id is deliberately absent here. Writing it on every refresh
            // would clear the link on an address the user saved during an
            // earlier search - re-running a search must not un-save anything.
            $row = FinderResult::updateOrCreate(
                [
                    'domain' => $result['domain'],
                    'email'  => $candidate['email'],
                ],
                [
                    'mode'    => $mode,
                    'person'  => $result['person'] ?? null,
                    'company' => $result['company'] ?? null,
                    'status'  => $candidate['status'],
                    'score'   => $candidate['score'],
                    'reason'  => $candidate['reason'],
                    'source'  => $candidate['source'],
                    'guessed' => $candidate['guessed'],
                    'type'    => $candidate['type'],
                    'pattern' => $candidate['pattern'],
                ],
            );

            if ($saved && $savedEmail === strtolower($candidate['email'])) {
                $row->update(['lead_id' => $saved['lead_id']]);
            }
        }
    }

    /**
     * Record one discovered address against its company's lead.
     *
     * Shared by the explicit Save button and the automatic save that follows a
     * domain search, so both behave identically: one lead per website, and an
     * address the user already has is never overwritten.
     *
     * @return array{ok:bool, created:bool, lead_id:int, email:string, message:string}
     */
    protected function saveLead(string $domain, string $email, ?string $companyName, ?int $categoryId): array
    {
        $website = 'https://'.$domain;

        $lead = Lead::where('website', $website)
            ->orWhere('website', $website.'/')
            ->first();

        if ($lead) {
            // Never clobber an address the user already has on the lead.
            if (empty($lead->email)) {
                $lead->update(['email' => $email]);
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
            'email'        => $email,
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
