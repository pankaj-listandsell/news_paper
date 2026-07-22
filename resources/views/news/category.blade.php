@extends('layouts.app')

@section('title', $category->name . ' — ' . \App\Support\SiteSettings::name())
@section('meta_description', $category->description ?: ('Aktuelle Nachrichten und Artikel aus der Kategorie ' . $category->name . ' – ' . \App\Support\SiteSettings::name() . '.'))

@push('meta')
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $category->name, 'item' => route('category.show', $category)],
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
            <li class="text-gray-700" aria-current="page">{{ $category->name }}</li>
        </ol>
    </nav>

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
