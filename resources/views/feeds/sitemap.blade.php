<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
        <changefreq>hourly</changefreq>
        <priority>1.0</priority>
    </url>

    @foreach ($pages as $page)
        <url>
            <loc>{{ route('page', $page) }}</loc>
            <changefreq>yearly</changefreq>
            <priority>0.3</priority>
        </url>
    @endforeach

    @foreach ($categories as $category)
        <url>
            <loc>{{ route('category.show', $category) }}</loc>
            <lastmod>{{ $category->updated_at->toAtomString() }}</lastmod>
            <changefreq>daily</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach

    @foreach ($articles as $article)
        <url>
            <loc>{{ route('article.show', $article) }}</loc>
            <lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.7</priority>
        </url>
    @endforeach

    @foreach ($tags as $tag)
        <url>
            <loc>{{ route('tag.show', $tag) }}</loc>
            <lastmod>{{ $tag->updated_at->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.4</priority>
        </url>
    @endforeach
</urlset>
