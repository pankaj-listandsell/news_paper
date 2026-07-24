<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMessage;
use App\Models\ContactMessage;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    /** A real person needs at least this long to write a message. */
    private const MIN_SECONDS_ON_FORM = 4;

    /** More links than this in one message is a spam signal. */
    private const MAX_LINKS = 2;

    public function show()
    {
        return view('news.contact');
    }

    public function send(Request $request)
    {
        // Layer 1 — honeypot. Only a bot fills the hidden field.
        // Pretend it worked so the bot doesn't learn to adapt.
        if (filled($request->input('website'))) {
            Log::info('Contact blocked (honeypot)', ['ip' => $request->ip()]);

            return $this->pretendSuccess();
        }

        // Layer 2 — submitted implausibly fast, or the timing token is missing/forged.
        if (! $this->spentEnoughTimeOnForm($request)) {
            Log::info('Contact blocked (too fast)', ['ip' => $request->ip()]);

            return $this->pretendSuccess();
        }

        // Layer 3 — reCAPTCHA (skipped while no keys are configured).
        if (! \App\Support\Recaptcha::passes($request->input('g-recaptcha-response'), $request->ip())) {
            throw ValidationException::withMessages([
                'g-recaptcha-response' => 'Bitte bestätigen Sie, dass Sie kein Roboter sind.',
            ]);
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email:rfc,dns', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        // Layer 4 — link spam.
        if ($this->countLinks($data['message']) > self::MAX_LINKS) {
            throw ValidationException::withMessages([
                'message' => 'Ihre Nachricht enthält zu viele Links.',
            ]);
        }

        // Store first, so a mail outage can never lose the message — it is
        // still waiting in the admin panel.
        ContactMessage::create($data + ['ip_address' => $request->ip()]);

        $to = SiteSettings::notifyRecipient();

        if (filled($to)) {
            try {
                Mail::to($to)->send(new ContactFormMessage(
                    senderName:  $data['name'],
                    senderEmail: $data['email'],
                    formSubject: $data['subject'],
                    body:        $data['message'],
                ));
            } catch (\Throwable $e) {
                // The visitor still gets a success message — we have the record.
                Log::warning('Could not email contact message: ' . $e->getMessage());
            }
        } else {
            Log::warning('Contact form has no recipient — set a contact email in General Settings.');
        }

        return back()->with('contact_status', 'Vielen Dank! Ihre Nachricht wurde gesendet — wir melden uns in Kürze.');
    }

    private function spentEnoughTimeOnForm(Request $request): bool
    {
        try {
            $startedAt = (int) decrypt($request->input('form_started_at'));
        } catch (\Throwable) {
            return false; // missing or tampered-with token
        }

        return (time() - $startedAt) >= self::MIN_SECONDS_ON_FORM;
    }

    private function countLinks(string $body): int
    {
        return preg_match_all('#(https?://|www\.)#i', $body);
    }

    /**
     * Same response a real submission gets, but nothing is sent.
     */
    private function pretendSuccess()
    {
        return back()->with('contact_status', 'Vielen Dank! Ihre Nachricht wurde gesendet — wir melden uns in Kürze.');
    }
}
