@php($brand = app(\App\Platform\Settings\PlatformSettings::class)->brandName())
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Console') — {{ $brand }} console</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div class="nav-progress" data-nav-progress aria-hidden="true"></div>
<header class="flex items-center gap-4 border-b border-ink-100 bg-ink-900 px-6 py-3 text-white">
  <span class="grid size-9 place-items-center rounded-xl bg-white/10"><x-icon name="shield" class="size-5" /></span>
  <span class="font-display text-lg font-semibold">{{ $brand }} · {{ __('Console') }}</span>
  @auth('platform')
    <nav class="ms-6 hidden gap-1 text-sm sm:flex">
      <a href="{{ route('platform.home') }}" class="rounded-lg px-3 py-1.5 hover:bg-white/10 {{ request()->routeIs('platform.home') ? 'bg-white/10' : '' }}">{{ __('Overview') }}</a>
      <a href="{{ route('platform.settings') }}" class="rounded-lg px-3 py-1.5 hover:bg-white/10 {{ request()->routeIs('platform.settings') ? 'bg-white/10' : '' }}">{{ __('Settings') }}</a>
    </nav>
    <form method="POST" action="{{ route('platform.logout') }}" class="ms-auto">
      @csrf
      <button class="rounded-lg px-3 py-1.5 text-sm hover:bg-white/10">{{ __('Sign out') }}</button>
    </form>
  @endauth
</header>
<main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
  @include('partials.flash')
  @yield('content')
</main>
<footer class="pb-8 text-center text-[11px] text-ink-400">{{ __('Version') }} {{ config('app.version') }} ({{ config('app.build') }})</footer>
</body>
</html>
