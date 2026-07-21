<x-mail::message>
# News scrape finished

**{{ $totalCreated }}** new article(s), **{{ $totalUpdated }}** updated@if($failures > 0), **{{ $failures }}** source(s) failed@endif.

<x-mail::table>
| Source | New | Updated | Status |
|:-------|:---:|:-------:|:-------|
@foreach($rows as $row)
| {{ $row['source'] }} | {{ $row['created'] }} | {{ $row['updated'] }} | {{ $row['error'] ? '⚠ ' . $row['error'] : 'OK' }} |
@endforeach
</x-mail::table>

<x-mail::button :url="url('/admin')">
Open admin panel
</x-mail::button>

You are receiving this because “Email me a summary after each run” is on in General Settings. Turn it off there to stop these emails.

{{ config('app.name') }}
</x-mail::message>
