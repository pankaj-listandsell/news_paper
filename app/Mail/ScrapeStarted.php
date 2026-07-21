<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScrapeStarted extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $sources  Names of the sources being scraped.
     */
    public function __construct(
        public array $sources,
        public string $startedAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'News scrape started ('.count($this->sources).' source'.(count($this->sources) === 1 ? '' : 's').')',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.scrape-started');
    }
}
