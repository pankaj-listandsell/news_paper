<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScrapeReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{source:string, created:int, updated:int, error:?string}>  $rows
     */
    public function __construct(
        public array $rows,
        public int $totalCreated,
        public int $totalUpdated,
        public int $failures,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->failures > 0
            ? "News scrape: {$this->totalCreated} new ({$this->failures} failed)"
            : "News scrape: {$this->totalCreated} new, {$this->totalUpdated} updated";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.scrape-report');
    }
}
