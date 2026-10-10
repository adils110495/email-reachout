<?php

namespace App\Sequencer\Exceptions;

/** The recipient address was rejected at SMTP time (550/551/553): a hard bounce. */
class RecipientRejectedException extends PermanentSendException {}
