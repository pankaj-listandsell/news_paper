@props(['article'])
<article class="group flex h-full flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100 transition hover:-translate-y-0.5 hover:shadow-md">
    <a href="{{ route('article.show', $article) }}" class="block overflow-hidden">
        <img src="{{ $article->image_url }}" alt="{{ $article->title }}" loading="lazy"
             class="aspect-[16/9] w-full object-cover transition duration-300 group-hover:scale-105">
    </a>
    <div class="flex flex-1 flex-col p-4">
        @if ($article->category)
            <a href="{{ route('category.show', $article->category) }}"
               class="text-xs font-bold uppercase tracking-wide text-[var(--brand)]">{{ $article->category->name }}</a>
        @endif
        <h3 class="mt-1 font-bold leading-snug text-gray-900">
            <a href="{{ route('article.show', $article) }}" class="line-clamp-2 hover:text-[var(--brand)]">{{ $article->title }}</a>
        </h3>
        @if ($article->excerpt)
            <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $article->excerpt }}</p>
        @endif
        <div class="mt-auto flex items-center gap-2 pt-3 text-xs text-gray-500">
            <span class="truncate">{{ $article->byline }}</span>
            <span>&middot;</span>
            <span class="shrink-0">{{ $article->published_at?->locale('de')->translatedFormat('d. M Y') }}</span>
        </div>
    </div>
</article>
