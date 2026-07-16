<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', 'Latest news, breaking stories and analysis.')">
    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

    {{-- Top bar --}}
    <div class="bg-gray-900 text-gray-300 text-xs">
        <div class="mx-auto max-w-7xl px-4 py-2 flex items-center justify-between">
            <span>{{ now()->translatedFormat('l, d F Y') }}</span>
            <nav class="flex gap-4">
                <a href="{{ route('home') }}" class="hover:text-white">Home</a>
                <a href="/admin" class="hover:text-white">Admin</a>
            </nav>
        </div>
    </div>

    {{-- Masthead --}}
    <header class="bg-white border-b">
        <div class="mx-auto max-w-7xl px-4 py-6 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                <span class="text-3xl font-black tracking-tight text-red-600">{{ config('app.name') }}</span>
            </a>
            <form action="{{ route('search') }}" method="GET" class="hidden md:flex items-center">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search news..."
                       class="w-64 rounded-l-md border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none">
                <button class="rounded-r-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Go</button>
            </form>
        </div>

        {{-- Category nav --}}
        <nav class="border-t bg-white">
            <div class="mx-auto max-w-7xl px-4 flex flex-wrap items-center gap-x-6 gap-y-1 py-3 text-sm font-semibold">
                <a href="{{ route('home') }}" class="text-red-600 hover:text-red-700">Home</a>
                @foreach ($navCategories as $cat)
                    <a href="{{ route('category.show', $cat) }}" class="text-gray-700 hover:text-red-600">{{ $cat->name }}</a>
                @endforeach
            </div>
        </nav>

        {{-- Breaking news ticker --}}
        @if ($breakingNews->isNotEmpty())
            <div class="bg-red-600 text-white">
                <div class="mx-auto max-w-7xl px-4 py-2 flex items-center gap-3 overflow-hidden">
                    <span class="shrink-0 rounded bg-white px-2 py-0.5 text-xs font-bold uppercase text-red-600">Breaking</span>
                    <div class="flex gap-8 whitespace-nowrap text-sm animate-[marquee_25s_linear_infinite]">
                        @foreach ($breakingNews as $b)
                            <a href="{{ route('article.show', $b) }}" class="hover:underline">{{ $b->title }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="mt-12 bg-gray-900 text-gray-300">
        <div class="mx-auto max-w-7xl px-4 py-10 grid gap-8 md:grid-cols-3">
            <div>
                <h3 class="text-xl font-black text-white">{{ config('app.name') }}</h3>
                <p class="mt-2 text-sm text-gray-400">Latest news, analysis and updates — all day long.</p>
            </div>
            <div>
                <h4 class="font-semibold text-white">Categories</h4>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($navCategories->take(6) as $cat)
                        <li><a href="{{ route('category.show', $cat) }}" class="hover:text-white">{{ $cat->name }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white">Newsletter</h4>
                <p class="mt-2 text-sm text-gray-400">Subscribe to get the latest news in your inbox.</p>
                @if (session('subscribe_status'))
                    <p class="mt-2 text-sm text-green-400">{{ session('subscribe_status') }}</p>
                @endif
                <form action="{{ route('subscribe') }}" method="POST" class="mt-3 flex">
                    @csrf
                    <input type="email" name="email" required placeholder="Email address"
                           class="w-full rounded-l-md px-3 py-2 text-sm text-gray-900 focus:outline-none">
                    <button class="rounded-r-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Join</button>
                </form>
                @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="border-t border-gray-800 py-4 text-center text-xs text-gray-500">
            &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
        </div>
    </footer>

    <style>
        @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    </style>
</body>
</html>
