<?php

namespace App\Console\Commands;

use App\Support\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test
                            {email? : Address to send to (defaults to the contact/admin email)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify the SMTP settings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $to = $this->argument('email') ?: SiteSettings::notifyRecipient();

        if (blank($to)) {
            $this->error('No recipient. Pass one (php artisan mail:test you@example.com) or set a contact email.');

            return self::FAILURE;
        }

        // Make sure we use the latest admin-configured SMTP account.
        SiteSettings::applyMailConfig();

        $host = config('mail.mailers.smtp.host');
        $this->line("Sending via {$host} to {$to} ...");

        try {
            Mail::raw(
                'This is a test email from '.SiteSettings::name().'. Your SMTP settings work.',
                fn ($m) => $m->to($to)->subject('Test email — '.SiteSettings::name())
            );

            $this->info("✓ Sent. Check the inbox (and spam) for {$to}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
