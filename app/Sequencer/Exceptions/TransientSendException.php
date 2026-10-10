<?php

namespace App\Sequencer\Exceptions;

/** Network error, timeout, 4xx reply, auth hiccup: worth retrying later. */
class TransientSendException extends SendException
{
    public function isPermanent(): bool
    {
        return false;
    }
}
