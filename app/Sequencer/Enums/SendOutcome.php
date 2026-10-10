<?php

namespace App\Sequencer\Enums;

/** What happened when the pipeline processed one due enrollment. */
enum SendOutcome: string
{
    case Sent = 'sent';
    case Completed = 'completed';     // nothing left to send: enrollment finished
    case Deferred = 'deferred';       // outside window / limit reached: rescheduled
    case Skipped = 'skipped';         // stale job or another worker owns the send
    case Stopped = 'stopped';         // a stop condition ended the enrollment
    case Retry = 'retry';             // transient failure: rescheduled with backoff
    case Failed = 'failed';           // permanent failure
    case Bounced = 'bounced';         // recipient rejected at SMTP time
    case Recovered = 'recovered';     // step had already been sent: progress was repaired
}
