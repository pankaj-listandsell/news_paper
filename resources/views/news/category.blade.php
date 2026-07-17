@extends('layouts.app')

@section('title', $category->name . ' — ' . \App\Support\SiteSettings::name())

@section('content')
    <header class="mb-6 border-b-2 border-[var(--brand)] pb-3">
        <h1 class="text-2xl font-black uppercase">{{ $category->name }}</h1>
        @if ($category->description)
            <p class="mt-1 text-gray-600">{{ $category->description }}</p>
        @endif
    </header>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="text-gray-500">In dieser Kategorie gibt es noch keine Artikel.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $articles->links() }}</div>
@endsection
