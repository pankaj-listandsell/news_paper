@props(['article'])
<article class="group overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-100 transition hover:shadow-md">
    <a href="{{ route('article.show', $article) }}" class="block overflow-hidden">
        <img src="{{ $article->image_url }}" alt="{{ $article->title }}"
             class="h-44 w-full object-cover transition duration-300 group-hover:scale-105">
    </a>
    <div class="p-4">
        @if ($article->category)
            <a href="{{ route('category.show', $article->category) }}"
               class="text-xs font-bold uppercase tracking-wide text-red-600">{{ $article->category->name }}</a>
        @endif
        <h3 class="mt-1 font-bold leading-snug text-gray-900">
            <a href="{{ route('article.show', $article) }}" class="hover:text-red-600">{{ $article->title }}</a>
        </h3>
        @if ($article->excerpt)
            <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $article->excerpt }}</p>
        @endif
        <div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
            <span>{{ $article->author?->name }}</span>
            <span>&middot;</span>
            <span>{{ $article->published_at?->format('d M Y') }}</span>
        </div>
    </div>
</article>
