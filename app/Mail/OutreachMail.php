<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $emailAttachments  [['path' => '/absolute/path', 'name' => 'original.pdf'], ...]
     */
    public function __construct(
        public readonly Lead   $lead,
        public readonly string $emailBody,
        public readonly string $subjectLine,
        public readonly string $senderName,
        public readonly string $senderCompany,
        public readonly array  $emailAttachments = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.outreach');
    }

    public function attachments(): array
    {
        return array_map(function ($att) {
            return Attachment::fromPath($att['path'])->as($att['name']);
        }, $this->emailAttachments);
    }
}
