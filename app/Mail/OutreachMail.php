<?php

namespace App\Mail;

use App\Models\Address;
use App\Models\AppSetting;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class OutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array        $emailAttachments  [['path' => '/absolute/path', 'name' => 'original.pdf'], ...]
     * @param Address|null $address           Optional sender address for dynamic footer
     */
    public function __construct(
        public readonly Lead     $lead,
        public readonly string   $emailBody,
        public readonly string   $subjectLine,
        public readonly string   $senderName,
        public readonly string   $senderCompany,
        public readonly array    $emailAttachments = [],
        public readonly ?Address $address = null,
        public readonly ?string  $trackingToken = null,
        public readonly ?string  $messageId = null,
    ) {}

    /**
     * A known Message-ID lets replies be matched back to this email
     * (their In-Reply-To header carries it).
     */
    public function headers(): Headers
    {
        return new Headers(messageId: $this->messageId);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        // Header logo and footer come from Settings > Branding / Addresses.
        AppSetting::flush();

        return new Content(view: 'emails.outreach', with: [
            'logoPath'      => AppSetting::emailLogoPath(),
            'logoUrl'       => AppSetting::emailLogoUrl(),
            'socialLinks'   => AppSetting::socialLinks(),
            // No address picked in the compose modal (or sent from the queue): use the first active one.
            'footerAddress' => $this->address ?? Address::active()->orderBy('id')->first(),
        ]);
    }

    public function attachments(): array
    {
        return array_map(function ($att) {
            return Attachment::fromPath($att['path'])->as($att['name']);
        }, $this->emailAttachments);
    }
}
