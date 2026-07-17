<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Article $article)
    {
        $data = $request->validate([
            'author_name'  => ['required', 'string', 'max:255'],
            'author_email' => ['required', 'email', 'max:255'],
            'body'         => ['required', 'string', 'max:2000'],
        ]);

        $article->comments()->create($data + ['is_approved' => false]);

        return back()->with('comment_status', 'Ihr Kommentar wurde zur Prüfung eingereicht und erscheint nach der Freigabe.');
    }
}
