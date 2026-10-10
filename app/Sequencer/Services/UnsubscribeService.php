<?php

namespace App\Sequencer\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Sequencer\Enums\ContactStatus;
use App\Sequencer\Events\ContactUnsubscribed;
use Illuminate\Support\Facades\DB;

/**
 * Records an unsubscribe on the lead. The ContactUnsubscribed event makes the
 * listener stop every open enrollment, so nothing further can be sent.
 */
class UnsubscribeService
{
    /** Tokens are random and unguessable. */
    public function findByToken(string $token): ?Lead
    {
        if (strlen($token) < 32 || strlen($token) > 64) {
            return null;
        }

        return Lead::where('unsubscribe_token', $token)->first();
    }

    /** @return bool true when this call changed the lead (false if already unsubscribed) */
    public function unsubscribe(Lead $lead, ?LeadEmail $email = null, string $source = 'link'): bool
    {
        $changed = DB::transaction(function () use ($lead) {
            $row = Lead::lockForUpdate()->find($lead->id);
            if (! $row || $row->contact_status === ContactStatus::Unsubscribed) {
                return false;
            }

            $row->forceFill(['contact_status' => ContactStatus::Unsubscribed, 'unsubscribed_at' => now()])->save();
            $lead->setRawAttributes($row->getAttributes(), true);

            return true;
        });

        // Even when already unsubscribed, make sure no enrollment is left running.
        event(new ContactUnsubscribed($lead, $email, $source));

        return $changed;
    }
}
