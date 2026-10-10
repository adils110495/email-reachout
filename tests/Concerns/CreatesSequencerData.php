<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\Lead;
use App\Models\MailSetting;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\User;
use App\Sequencer\Enums\SequenceStatus;
use App\Sequencer\Mail\EmailProviderManager;
use App\Sequencer\Services\EnrollmentService;
use App\Services\ImapService;
use Illuminate\Support\Str;
use Tests\Support\FakeEmailProvider;
use Tests\Support\FakeImapService;

/** Builders for realistic fixtures on the app's own tables: leads, categories, mail settings, sequences. */
trait CreatesSequencerData
{
    protected FakeImapService $inbox;

    /** Swap SMTP and IMAP for in-memory fakes. Call from setUp(). */
    protected function fakeTransports(): void
    {
        FakeEmailProvider::reset();
        app(EmailProviderManager::class)->extend('smtp', fn () => new FakeEmailProvider, 'SMTP');

        $this->inbox = new FakeImapService;
        $this->app->instance(ImapService::class, $this->inbox);
    }

    protected function makeUser(array $attrs = []): User
    {
        return User::create($attrs + [
            'name' => 'Test User '.Str::random(4),
            'username' => 'user_'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.com',
            'password' => 'secret-password',
            'timezone' => 'UTC',
        ]);
    }

    /** A sending account (Settings > Mail Settings). The first one made is the default. */
    protected function makeAccount(array $attrs = []): MailSetting
    {
        $account = MailSetting::create($attrs + [
            'name' => 'Outreach '.Str::random(4),
            'provider' => 'smtp',
            'from_name' => 'Sam Sender',
            'from_address' => 'sam@sender.test',
            'host' => 'smtp.sender.test',
            'port' => 587,
            'username' => 'sam@sender.test',
            'password' => 'smtp-secret',
            'encryption' => 'tls',
            'imap_host' => 'imap.sender.test',
            'imap_port' => 993,
            'imap_username' => 'sam@sender.test',
            'imap_password' => 'imap-secret',
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'folder' => 'INBOX.Sent',
            'rate_limit_per_minute' => 1000,
            'is_active' => true,
        ]);

        if (MailSetting::where('is_default', true)->doesntExist()) {
            $account->makeDefault();
        }

        return $account->refresh();
    }

    protected function makeLead(array $attrs = []): Lead
    {
        return Lead::create($attrs + [
            'first_name' => 'Alex',
            'last_name' => 'Morgan',
            'email' => Str::lower(Str::random(8)).'@prospect.test',
            'company_name' => 'Acme',
            'website' => 'https://acme.test',
            'status' => 'new',
        ])->refresh();
    }

    protected function makeCategory(?string $name = null): Category
    {
        return Category::create(['name' => $name ?? 'Cat '.Str::random(5), 'status' => 'active']);
    }

    /**
     * A sequence open Monday-Sunday all day by default so tests are time-independent.
     *
     * @param  list<array{subject?: string, body?: string, days?: int, hours?: int, minutes?: int}>  $steps
     */
    protected function makeSequence(array $steps = [[]], array $attrs = [], bool $active = true): Sequence
    {
        $sequence = new Sequence($attrs + [
            'name' => 'Seq '.Str::random(4),
            'timezone' => 'UTC',
            'sending_start_time' => '00:00',
            'sending_end_time' => '23:59:59',
            'sending_days' => [1, 2, 3, 4, 5, 6, 7],
            'daily_limit' => 1000,
            'track_opens' => true,
            'track_clicks' => true,
        ]);
        $sequence->status = $active ? SequenceStatus::Active : SequenceStatus::Draft;
        $sequence->save();

        foreach (array_values($steps) as $i => $step) {
            $row = new SequenceStep([
                'subject' => $step['subject'] ?? 'Hello {{first_name}}',
                'body' => $step['body'] ?? "Hi {{first_name}},\n\nQuick note from {{sender_name}}.",
                'delay_days' => $step['days'] ?? 0,
                'delay_hours' => $step['hours'] ?? 0,
                'delay_minutes' => $step['minutes'] ?? 0,
            ]);
            $row->sequence_id = $sequence->id;
            $row->step_number = $i + 1;
            $row->save();
        }

        return $sequence->refresh();
    }

    protected function enroll(Sequence $sequence, Lead $lead, ?MailSetting $account = null): SequenceEnrollment
    {
        $account ??= MailSetting::defaultAccount() ?? $this->makeAccount();

        $result = app(EnrollmentService::class)->enroll($sequence, $lead, $account);
        $this->assertTrue($result->wasEnrolled(), 'Enrollment failed: '.$result->reason);

        return $result->enrollment->refresh();
    }
}
