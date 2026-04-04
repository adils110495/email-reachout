<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Lead   $lead,
        public readonly string $emailBody,
        public readonly string $subjectLine,
        public readonly string $senderName,
        public readonly string $senderCompany,
    ) {}

    /**
     * Get the message envelope (subject, from, reply-to, etc.)
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.outreach',
        );
    }

    /**
     * Get the attachments for the message (none for cold outreach).
     */
    public function attachments(): array
    {
        return [];
    }
}
