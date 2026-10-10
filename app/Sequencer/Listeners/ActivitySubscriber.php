<?php

namespace App\Sequencer\Listeners;

use App\Sequencer\Enums\ActivityType;
use App\Sequencer\Events\BounceDetected;
use App\Sequencer\Events\ContactUnsubscribed;
use App\Sequencer\Events\EmailFailed;
use App\Sequencer\Events\EmailOpened;
use App\Sequencer\Events\EmailQueued;
use App\Sequencer\Events\EmailSent;
use App\Sequencer\Events\EnrollmentStopped;
use App\Sequencer\Events\LinkClicked;
use App\Sequencer\Events\ReplyDetected;
use App\Sequencer\Services\ActivityRecorder;
use Illuminate\Events\Dispatcher;

/** Turns domain events into rows of the activity timeline. */
class ActivitySubscriber
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            EmailQueued::class => 'onQueued',
            EmailSent::class => 'onSent',
            EmailFailed::class => 'onFailed',
            EmailOpened::class => 'onOpened',
            LinkClicked::class => 'onClicked',
            ReplyDetected::class => 'onReply',
            BounceDetected::class => 'onBounce',
            ContactUnsubscribed::class => 'onUnsubscribed',
            EnrollmentStopped::class => 'onStopped',
        ];
    }

    public function onQueued(EmailQueued $e): void
    {
        $this->activity->forEmail(ActivityType::EmailQueued, $e->log, 'Email queued: '.$e->log->subject);
    }

    public function onSent(EmailSent $e): void
    {
        $this->activity->forEmail(ActivityType::EmailSent, $e->log, 'Email sent to '.$e->log->to_email, [
            'subject' => $e->log->subject,
            'message_id' => $e->log->message_id,
        ], $e->log->sent_at);
    }

    public function onFailed(EmailFailed $e): void
    {
        $this->activity->forEmail(ActivityType::EmailFailed, $e->log, 'Email failed: '.$e->error);
    }

    public function onOpened(EmailOpened $e): void
    {
        $this->activity->forEmail(ActivityType::EmailOpened, $e->log, 'Opened by '.$e->log->to_email, [], $e->log->first_opened_at);
    }

    public function onClicked(LinkClicked $e): void
    {
        $this->activity->forEmail(ActivityType::LinkClicked, $e->log, 'Clicked '.$e->link->url, ['url' => $e->link->url]);
    }

    public function onReply(ReplyDetected $e): void
    {
        $this->activity->forEmail(ActivityType::ReplyReceived, $e->log, 'Reply received from '.$e->log->to_email, [], $e->log->replied_at);
    }

    public function onBounce(BounceDetected $e): void
    {
        $description = 'Bounce received: '.mb_substr($e->reason, 0, 300);

        if ($e->log) {
            $this->activity->forEmail(ActivityType::BounceReceived, $e->log, $description);

            return;
        }

        $this->activity->record(ActivityType::BounceReceived, $description, ['lead_id' => $e->lead->id]);
    }

    public function onUnsubscribed(ContactUnsubscribed $e): void
    {
        $this->activity->record(ActivityType::Unsubscribed, $e->lead->email.' unsubscribed', [
            'lead_id' => $e->lead->id,
            'lead_email_id' => $e->log?->id,
            'sequence_id' => $e->log?->sequence_id,
            'enrollment_id' => $e->log?->enrollment_id,
        ], ['source' => $e->source]);
    }

    public function onStopped(EnrollmentStopped $e): void
    {
        $this->activity->record(ActivityType::SequenceStopped, 'Sequence stopped: '.$e->reason->label(), [
            'sequence_id' => $e->enrollment->sequence_id,
            'lead_id' => $e->enrollment->lead_id,
            'enrollment_id' => $e->enrollment->id,
        ], ['reason' => $e->reason->value]);
    }
}
