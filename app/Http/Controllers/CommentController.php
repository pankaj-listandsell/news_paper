<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    /** A real person needs at least this long to write a comment. */
    private const MIN_SECONDS_ON_FORM = 4;

    /** More links than this in one comment is a spam signal. */
    private const MAX_LINKS = 2;

    public function store(Request $request, Article $article)
    {
        // Comments switched off in admin — reject any direct posts too.
        abort_unless(\App\Support\SiteSettings::commentsEnabled(), 404);

        // Layer 1 — honeypot. Only a bot fills the hidden field.
        // Pretend it worked so the bot doesn't learn to adapt.
        if (filled($request->input('website'))) {
            Log::info('Comment blocked (honeypot)', ['ip' => $request->ip()]);

            return $this->pretendSuccess();
        }

        // Layer 2 — submitted implausibly fast, or the timing token is missing/forged.
        if (! $this->spentEnoughTimeOnForm($request)) {
            Log::info('Comment blocked (too fast)', ['ip' => $request->ip()]);

            return $this->pretendSuccess();
        }

        $data = $request->validate([
            'author_name'  => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email:rfc,dns', 'max:255'],
            'body'         => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        // Layer 3 — link spam.
        if ($this->countLinks($data['body']) > self::MAX_LINKS) {
            throw ValidationException::withMessages([
                'body' => 'Ihr Kommentar enthält zu viele Links.',
            ]);
        }

        $article->comments()->create($data + ['is_approved' => false]);

        return back()->with('comment_status', 'Ihr Kommentar wurde zur Prüfung eingereicht und erscheint nach der Freigabe.');
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
     * Same response a real submission gets, but nothing is saved.
     */
    private function pretendSuccess()
    {
        return back()->with('comment_status', 'Ihr Kommentar wurde zur Prüfung eingereicht und erscheint nach der Freigabe.');
    }
}
