@extends('layouts.app')

@section('title', $heading . ' — ' . \App\Support\SiteSettings::name())
@section('meta_description', $heading . ' von ' . \App\Support\SiteSettings::name() . '.')

@section('content')
    <header class="mb-6 border-b-2 border-[var(--brand)] pb-3">
        <h1 class="text-2xl font-black uppercase">{{ $heading }}</h1>
    </header>

    <div class="prose prose-lg max-w-3xl prose-a:text-[var(--brand)]">
        {!! $content !!}
    </div>
@endsection
