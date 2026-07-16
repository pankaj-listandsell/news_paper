@extends('layouts.app')

@section('title', '#' . $tag->name . ' — ' . config('app.name'))

@section('content')
    <header class="mb-6 border-b-2 border-red-600 pb-3">
        <h1 class="text-2xl font-black">#{{ $tag->name }}</h1>
    </header>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="text-gray-500">No articles for this tag yet.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $articles->links() }}</div>
@endsection
