@extends('layouts.app')

@section('title', '#' . $tag->name . ' — ' . \App\Support\SiteSettings::name())
@section('meta_description', 'Alle Artikel und Nachrichten zum Thema ' . $tag->name . ' – ' . \App\Support\SiteSettings::name() . '.')

@push('meta')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => '#' . $tag->name, 'item' => route('tag.show', $tag)],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="Breadcrumb" class="mb-4 text-xs text-gray-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="{{ route('home') }}" class="hover:text-[var(--brand)]">Startseite</a></li>
            <li aria-hidden="true" class="text-gray-300">›</li>
            <li class="text-gray-700" aria-current="page">#{{ $tag->name }}</li>
        </ol>
    </nav>

    <header class="mb-6 border-b-2 border-[var(--brand)] pb-3">
        <h1 class="text-2xl font-black">#{{ $tag->name }}</h1>
    </header>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="text-gray-500">Zu diesem Thema gibt es noch keine Artikel.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $articles->links() }}</div>
@endsection
