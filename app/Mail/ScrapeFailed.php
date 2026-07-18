<?php

namespace App\Mail;

use App\Models\NewsSource;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScrapeFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsSource $source,
        public string $error,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Scrape failed: {$this->source->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.scrape-failed',
        );
    }
}
