<?php

namespace App\Sequencer\Enums;

enum Encryption: string
{
    case None = 'none';
    case Tls = 'tls';    // STARTTLS upgrade (SMTP 587 / IMAP 143)
    case Ssl = 'ssl';    // implicit TLS (SMTP 465 / IMAP 993)

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
