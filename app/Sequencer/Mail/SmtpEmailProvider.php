<?php

namespace App\Sequencer\Mail;

use App\Models\MailSetting;
use App\Sequencer\Contracts\EmailProviderInterface;
use App\Sequencer\Exceptions\PermanentSendException;
use App\Sequencer\Exceptions\RecipientRejectedException;
use App\Sequencer\Exceptions\SendException;
use App\Sequencer\Exceptions\TransientSendException;
use App\Services\MailConfigService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Delivers through an account's own SMTP server (Settings > Mail Settings), with the
 * transport built by MailConfigService so every part of the app connects the same way.
 */
class SmtpEmailProvider implements EmailProviderInterface
{
    public function __construct(
        private readonly MailSetting $account,
        private readonly MailConfigService $config,
    ) {}

    public function send(OutboundEmail $email): SendResult
    {
        $transport = $this->config->transportFor($this->account);

        try {
            $transport->send($this->buildMime($email));
        } catch (SendException $e) {
            throw $e;
        } catch (TransportExceptionInterface $e) {
            throw $this->classify($e);
        } catch (Throwable $e) {
            throw new TransientSendException($e->getMessage(), 0, $e);
        } finally {
            $this->close($transport);
        }

        // The SMTP transcript (getDebug) is deliberately discarded: it contains the
        // base64 credentials of the AUTH exchange.
        return new SendResult($email->messageId);
    }

    public function testConnection(): ConnectionResult
    {
        return $this->config->testSmtp($this->account);
    }

    private function buildMime(OutboundEmail $email): Email
    {
        $mime = (new Email)
            ->from(new Address($email->fromEmail, $email->fromName))
            ->to(new Address($email->toEmail, (string) $email->toName))
            ->subject($email->subject)
            ->html($email->html)
            ->text($email->text);

        if ($email->replyTo) {
            $mime->replyTo($email->replyTo);
        }

        $headers = $mime->getHeaders();
        $headers->addIdHeader('Message-ID', $email->messageId);

        foreach ($email->headers as $name => $value) {
            $headers->addTextHeader($name, $value);
        }

        return $mime;
    }

    /**
     * Map a Symfony transport failure onto the retry policy.
     *
     * 550/551/553 while addressing the recipient = the mailbox does not exist (hard
     * bounce). 535/534/530 = our credentials are wrong (retry, flag the account).
     * Other 5xx = refused for good. 4xx / network = try again later.
     */
    private function classify(TransportExceptionInterface $e): SendException
    {
        $message = $this->config->scrub($e->getMessage(), $this->account);
        $code = (int) $e->getCode();
        if ($code === 0 && preg_match('/got code "(\d{3})"/', $e->getMessage(), $m)) {
            $code = (int) $m[1];
        }

        $atRcpt = str_contains($e->getMessage(), '"250/251/252"');

        if ($atRcpt && in_array($code, [550, 551, 553], true)) {
            return new RecipientRejectedException($message, $code, $e);
        }

        if (in_array($code, [530, 534, 535], true)) {
            $this->account->forceFill(['smtp_ok' => false, 'smtp_tested_at' => now()])->saveQuietly();

            return new TransientSendException('SMTP authentication failed: '.$message, $code, $e);
        }

        if ($code >= 500 && $code < 600) {
            return new PermanentSendException($message, $code, $e);
        }

        return new TransientSendException($message, $code, $e);
    }

    private function close(EsmtpTransport $transport): void
    {
        try {
            $transport->stop();
        } catch (Throwable) {
            // closing a half-open connection can fail; nothing useful to do
        }
    }
}
