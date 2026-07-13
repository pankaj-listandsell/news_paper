@extends('layouts.app')

@section('title', config('app.name') . ' — Latest News')

@section('content')
    {{-- Hero: featured --}}
    @if ($featured->isNotEmpty())
        @php $lead = $featured->first(); $rest = $featured->slice(1); @endphp
        <section class="grid gap-6 lg:grid-cols-3">
            <a href="{{ route('article.show', $lead) }}"
               class="group relative col-span-2 overflow-hidden rounded-xl bg-gray-900 text-white">
                <img src="{{ $lead->image_url }}" alt="{{ $lead->title }}"
                     class="h-96 w-full object-cover opacity-70 transition duration-300 group-hover:scale-105 group-hover:opacity-60">
                <div class="absolute bottom-0 p-6">
                    @if ($lead->category)
                        <span class="rounded bg-red-600 px-2 py-0.5 text-xs font-bold uppercase">{{ $lead->category->name }}</span>
                    @endif
                    <h2 class="mt-3 text-3xl font-black leading-tight">{{ $lead->title }}</h2>
                    <p class="mt-2 max-w-2xl text-sm text-gray-200 line-clamp-2">{{ $lead->excerpt }}</p>
                </div>
            </a>

            <div class="flex flex-col gap-4">
                @foreach ($rest as $item)
                    <a href="{{ route('article.show', $item) }}" class="group flex gap-3">
                        <img src="{{ $item->image_url }}" alt="{{ $item->title }}"
                             class="h-20 w-28 shrink-0 rounded-lg object-cover">
                        <div>
                            @if ($item->category)
                                <span class="text-xs font-bold uppercase text-red-600">{{ $item->category->name }}</span>
                            @endif
                            <h3 class="text-sm font-bold leading-snug group-hover:text-red-600">{{ $item->title }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-10 grid gap-10 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="lg:col-span-2">
            <h2 class="mb-4 border-b-2 border-red-600 pb-2 text-lg font-black uppercase">Latest News</h2>
            <div class="grid gap-6 sm:grid-cols-2">
                @forelse ($latest as $article)
                    @include('partials.article-card', ['article' => $article])
                @empty
                    <p class="text-gray-500">Abhi koi article publish nahi hui.</p>
                @endforelse
            </div>

            {{-- Per-category sections --}}
            @foreach ($categoryBlocks as $cat)
                <section class="mt-10">
                    <div class="mb-4 flex items-center justify-between border-b-2 border-gray-900 pb-2">
                        <h2 class="text-lg font-black uppercase">{{ $cat->name }}</h2>
                        <a href="{{ route('category.show', $cat) }}" class="text-sm font-semibold text-red-600 hover:underline">View all →</a>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-2">
                        @foreach ($cat->articles as $article)
                            @include('partials.article-card', ['article' => $article])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Sidebar --}}
        <aside>
            <h2 class="mb-4 border-b-2 border-red-600 pb-2 text-lg font-black uppercase">Most Viewed</h2>
            <ol class="space-y-4">
                @foreach ($mostViewed as $i => $article)
                    <li class="flex gap-3">
                        <span class="text-2xl font-black text-gray-300">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <a href="{{ route('article.show', $article) }}" class="text-sm font-semibold leading-snug hover:text-red-600">
                            {{ $article->title }}
                            <span class="mt-1 block text-xs font-normal text-gray-400">{{ number_format($article->views) }} views</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </aside>
    </div>
@endsection
