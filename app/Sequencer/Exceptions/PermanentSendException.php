<?php

namespace App\Sequencer\Exceptions;

/** The server refused the message for good (5xx other than a bad recipient). Never retried. */
class PermanentSendException extends SendException
{
    public function isPermanent(): bool
    {
        return true;
    }
}
