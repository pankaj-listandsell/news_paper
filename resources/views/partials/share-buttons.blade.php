@props(['article'])

@php
    $shareUrl   = route('article.show', $article);
    $shareTitle = $article->title;

    $links = [
        'X' => [
            'url'  => 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareTitle) . '&url=' . rawurlencode($shareUrl),
            'icon' => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
        ],
        'Facebook' => [
            'url'  => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl),
            'icon' => '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
        ],
        'LinkedIn' => [
            'url'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($shareUrl),
            'icon' => '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
        ],
        'WhatsApp' => [
            'url'  => 'https://wa.me/?text=' . rawurlencode($shareTitle . ' ' . $shareUrl),
            'icon' => '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 00-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 016.988 2.896 9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.4"/>',
        ],
        'E-Mail' => [
            'url'  => 'mailto:?subject=' . rawurlencode($shareTitle) . '&body=' . rawurlencode($shareUrl),
            'icon' => '<path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z"/><path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z"/>',
        ],
    ];
@endphp

<div class="mt-8 rounded-lg border border-gray-200 bg-white p-4">
    <p class="text-sm font-semibold text-gray-700">Artikel teilen mit:</p>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        @foreach ($links as $label => $link)
            <a href="{{ $link['url'] }}"
               target="_blank"
               rel="noopener noreferrer"
               title="Auf {{ $label }} teilen"
               aria-label="Auf {{ $label }} teilen"
               class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-600 transition hover:border-[var(--brand)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">{!! $link['icon'] !!}</svg>
            </a>
        @endforeach

        {{-- Copy link --}}
        <button type="button"
                data-share-url="{{ $shareUrl }}"
                title="Link kopieren"
                aria-label="Link kopieren"
                onclick="copyArticleLink(this)"
                class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-600 transition hover:border-[var(--brand)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244"/>
            </svg>
        </button>

        <span id="share-copied" class="hidden text-xs font-semibold text-green-600">Link kopiert!</span>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function copyArticleLink(button) {
                const url = button.dataset.shareUrl;
                const note = document.getElementById('share-copied');

                const done = () => {
                    if (!note) return;
                    note.classList.remove('hidden');
                    setTimeout(() => note.classList.add('hidden'), 2000);
                };

                if (navigator.clipboard?.writeText) {
                    navigator.clipboard.writeText(url).then(done).catch(() => window.prompt('Link kopieren:', url));
                } else {
                    window.prompt('Link kopieren:', url);
                }
            }
        </script>
    @endpush
@endonce
