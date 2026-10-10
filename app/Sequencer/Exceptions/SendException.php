<?php

namespace App\Sequencer\Exceptions;

use RuntimeException;

/**
 * Base for every delivery failure. The send pipeline only cares about the
 * subclass: transient errors are retried with backoff, permanent ones are not.
 */
abstract class SendException extends RuntimeException
{
    abstract public function isPermanent(): bool;
}
