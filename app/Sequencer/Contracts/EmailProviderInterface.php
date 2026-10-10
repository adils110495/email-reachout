<?php

namespace App\Sequencer\Contracts;

use App\Sequencer\Exceptions\SendException;
use App\Sequencer\Mail\ConnectionResult;
use App\Sequencer\Mail\OutboundEmail;
use App\Sequencer\Mail\SendResult;

/**
 * The only thing the sequence engine knows about delivering mail.
 *
 * Gmail, Microsoft, Amazon SES, Mailgun, SendGrid... each become another
 * implementation registered in EmailProviderManager; no sequence code changes.
 */
interface EmailProviderInterface
{
    /**
     * Deliver one message.
     *
     * @throws SendException TransientSendException (retry), PermanentSendException
     *                       or RecipientRejectedException (do not retry).
     */
    public function send(OutboundEmail $email): SendResult;

    /** Verify credentials / reachability without sending anything. */
    public function testConnection(): ConnectionResult;
}
