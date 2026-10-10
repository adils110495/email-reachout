<?php

namespace App\Sequencer\Mail;

use App\Models\MailSetting;
use App\Sequencer\Contracts\EmailProviderInterface;
use App\Services\MailConfigService;
use Closure;
use InvalidArgumentException;

/**
 * Resolves the delivery provider for a sending account (mail_settings.provider).
 *
 * Adding Gmail / Microsoft / SES / Mailgun / SendGrid later is one call:
 *
 *     $manager->extend('ses', fn (MailSetting $a) => new SesEmailProvider($a), 'Amazon SES');
 *
 * The sequence engine only ever sees EmailProviderInterface.
 */
class EmailProviderManager
{
    /** @var array<string, Closure(MailSetting): EmailProviderInterface> */
    private array $factories = [];

    /** @var array<string, string> */
    private array $labels = [];

    public function __construct()
    {
        $this->extend('smtp', fn (MailSetting $account) => new SmtpEmailProvider($account, app(MailConfigService::class)), 'SMTP');
    }

    /** @param  Closure(MailSetting): EmailProviderInterface  $factory */
    public function extend(string $name, Closure $factory, ?string $label = null): void
    {
        $this->factories[$name] = $factory;
        $this->labels[$name] = $label ?? ucfirst($name);
    }

    public function forAccount(MailSetting $account): EmailProviderInterface
    {
        $factory = $this->factories[$account->provider ?: 'smtp'] ?? null;

        if (! $factory) {
            throw new InvalidArgumentException("No email provider registered for [{$account->provider}].");
        }

        return $factory($account);
    }

    /** @return array<string, string> name => label, for forms and validation */
    public function available(): array
    {
        return $this->labels;
    }

    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }
}
