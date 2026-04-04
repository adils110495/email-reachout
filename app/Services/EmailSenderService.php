<?php

namespace App\Services;

use App\Jobs\SendEmailJob;
use App\Models\Lead;

class EmailSenderService
{
    /**
     * Dispatch a queued job to send an outreach email to a lead.
     * A random delay between EMAIL_DELAY_MIN and EMAIL_DELAY_MAX seconds
     * is added to avoid triggering spam filters.
     */
    public function dispatch(Lead $lead): void
    {
        $delayMin = (int) env('EMAIL_DELAY_MIN', 30);
        $delayMax = (int) env('EMAIL_DELAY_MAX', 60);

        $delaySeconds = rand($delayMin, $delayMax);

        SendEmailJob::dispatch($lead)
            ->delay(now()->addSeconds($delaySeconds))
            ->onQueue('emails');
    }

    /**
     * Dispatch outreach emails to all new leads that have an email address.
     * Each job is staggered so emails are not sent in a burst.
     */
    public function dispatchAll(): int
    {
        $leads = Lead::new()->withEmail()->get();

        $delayMin     = (int) env('EMAIL_DELAY_MIN', 30);
        $delayMax     = (int) env('EMAIL_DELAY_MAX', 60);
        $cumulativeDelay = 0;
        $dispatched   = 0;

        foreach ($leads as $lead) {
            $cumulativeDelay += rand($delayMin, $delayMax);

            SendEmailJob::dispatch($lead)
                ->delay(now()->addSeconds($cumulativeDelay))
                ->onQueue('emails');

            $dispatched++;
        }

        return $dispatched;
    }
}
