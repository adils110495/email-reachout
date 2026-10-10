<?php

namespace App\Sequencer\Exceptions;

use RuntimeException;

/** The mailbox could not be opened or read. Retried on the next poll. */
class ImapException extends RuntimeException {}
