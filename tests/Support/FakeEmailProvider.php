<?php

namespace Tests\Support;

use App\Sequencer\Contracts\EmailProviderInterface;
use App\Sequencer\Exceptions\SendException;
use App\Sequencer\Mail\ConnectionResult;
use App\Sequencer\Mail\OutboundEmail;
use App\Sequencer\Mail\SendResult;
use Closure;

/**
 * In-memory stand-in for SMTP. Records every message and can be told to fail.
 * Static so it survives being re-instantiated per account by the provider manager.
 */
class FakeEmailProvider implements EmailProviderInterface
{
    /** @var list<OutboundEmail> */
    public static array $sent = [];

    /** @var (Closure(OutboundEmail): void)|null  throw from here to simulate a failure */
    public static ?Closure $onSend = null;

    public static ?ConnectionResult $connection = null;

    /** File to append one line per send to (lets forked processes share a record). */
    public static ?string $logFile = null;

    public static function reset(): void
    {
        self::$sent = [];
        self::$onSend = null;
        self::$connection = null;
        self::$logFile = null;
    }

    public static function failWith(SendException $e, int $times = PHP_INT_MAX): void
    {
        $remaining = $times;
        self::$onSend = function () use ($e, &$remaining) {
            if ($remaining-- > 0) {
                throw $e;
            }
        };
    }

    public function send(OutboundEmail $email): SendResult
    {
        if (self::$onSend) {
            (self::$onSend)($email);
        }

        self::$sent[] = $email;

        if (self::$logFile) {
            file_put_contents(self::$logFile, $email->messageId.' '.$email->toEmail.PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        return new SendResult($email->messageId);
    }

    public function testConnection(): ConnectionResult
    {
        return self::$connection ?? ConnectionResult::success('Fake SMTP ok.');
    }
}
