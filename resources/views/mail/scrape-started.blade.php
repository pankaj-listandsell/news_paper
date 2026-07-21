<x-mail::message>
# News scrape started

The scraper started at **{{ $startedAt }}** for **{{ count($sources) }}** source(s):

@foreach($sources as $name)
- {{ $name }}
@endforeach

A summary email with the results (new / updated articles) will follow when it finishes.

{{ config('app.name') }}
</x-mail::message>
