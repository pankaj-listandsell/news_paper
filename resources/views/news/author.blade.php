@extends('layouts.app')

@section('title', $author->name . ' — ' . \App\Support\SiteSettings::name())
@section('meta_description', $author->bio
    ? \Illuminate\Support\Str::limit(strip_tags($author->bio), 155)
    : 'Alle Artikel von ' . $author->name . '.')

@section('content')
    {{-- Author profile card --}}
    <header class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 sm:p-8">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
            <img src="{{ $author->avatar_url }}" alt="{{ $author->name }}"
                 class="h-24 w-24 shrink-0 rounded-full object-cover ring-4 ring-gray-50">

            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Autor</p>
                <h1 class="mt-1 text-3xl font-black leading-tight">{{ $author->name }}</h1>

                @if ($author->designation)
                    <p class="mt-1 text-sm font-semibold text-[var(--brand)]">{{ $author->designation }}</p>
                @endif

                @if ($author->bio)
                    <p class="mt-3 max-w-2xl text-sm leading-relaxed text-gray-600">{{ $author->bio }}</p>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                        {{ $articles->total() }} {{ $articles->total() === 1 ? 'Artikel' : 'Artikel' }}
                    </span>

                    @foreach ($author->profile_links as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"
                           class="font-semibold text-[var(--brand)] hover:underline">{{ $label }} ↗</a>
                    @endforeach
                </div>
            </div>
        </div>
    </header>

    {{-- Their articles --}}
    <h2 class="mt-10 mb-4 border-b-2 border-[var(--brand)] pb-2 text-lg font-black uppercase">
        Artikel von {{ $author->name }}
    </h2>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="text-gray-500">Von diesem Autor gibt es noch keine Artikel.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $articles->links() }}</div>
@endsection
