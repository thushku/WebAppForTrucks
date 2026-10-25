{{--
  The public website (M14): header, footer with the legal pages and the
  version line, and a splash on the first page of a visit. Light pages: no
  images to wait for, the fonts preloaded, one CSS and one JS file.
--}}
@php
  $settings = app(\App\Platform\Settings\PlatformSettings::class);
  $brand = $settings->brandName();
  $trialDays = $settings->trialDays();
  $nav = [['site.features', __('Features')], ['site.pricing', __('Pricing')], ['site.faq', __('FAQ')], ['site.about', __('About us')], ['site.contact', __('Contact')]];
  $splash = ! session()->has('site.seen');
  session()->put('site.seen', true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locales::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') — {{ $brand }}</title>
<meta name="description" content="@yield('description', __('Trips, crew app, fuel and road spending with GPS and photos, invoices and LR, GST-ready books — for truck owners and transport companies.'))">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:title" content="@yield('title') — {{ $brand }}">
<meta property="og:description" content="@yield('description', __('Trips, crew app, fuel and road spending with GPS and photos, invoices and LR, GST-ready books — for truck owners and transport companies.'))">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta name="theme-color" content="#162F5E">
{!! \App\Support\Fonts::preloadTags() !!}
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-ink-900">
<div class="nav-progress" data-nav-progress aria-hidden="true"></div>
@if ($splash)
  <div class="splash splash-site" data-splash role="status" aria-label="{{ __('Loading') }}">
    <div class="text-center">
      <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-white/15"><x-icon name="truck" class="size-9" /></div>
      <div class="mt-4 font-display text-2xl font-semibold">{{ $brand }}</div>
      <div class="splash-bar mx-auto mt-5"><div class="splash-fill"></div></div>
    </div>
  </div>
@endif

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">{{ __('Skip to content') }}</a>
<header class="sticky top-0 z-40 border-b border-ink-100/70 bg-white/85 backdrop-blur">
  <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6">
    <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="{{ $brand }} — {{ __('home') }}">
      <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><x-icon name="truck" class="size-5" /></span>
      <span class="font-display text-lg font-semibold">{{ $brand }}</span>
    </a>
    <nav class="ms-6 hidden items-center gap-1 md:flex" aria-label="{{ __('Website') }}">
      @foreach ($nav as [$route, $label])
        <a href="{{ route($route) }}" class="site-link {{ request()->routeIs($route) ? 'is-active' : '' }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>
    <div class="ms-auto flex items-center gap-2">
      @auth
        <a href="{{ route('dashboard') }}" class="btn-primary">{{ __('Open the app') }}</a>
      @else
        <a href="{{ route('login') }}" class="site-link hidden sm:inline-flex">{{ __('Sign in') }}</a>
        <a href="{{ route('signup') }}" class="btn-primary">{{ __('Start free trial') }}</a>
      @endauth
      <details class="relative md:hidden">
        <summary class="btn-ghost list-none px-2" aria-label="{{ __('Menu') }}"><x-icon name="menu" /></summary>
        <div class="card absolute end-0 mt-2 w-56 p-2">
          @foreach ($nav as [$route, $label])<a href="{{ route($route) }}" class="nav-link">{{ $label }}</a>@endforeach
          @guest<a href="{{ route('login') }}" class="nav-link">{{ __('Sign in') }}</a>@endguest
        </div>
      </details>
    </div>
  </div>
</header>

<main id="main">
  @yield('content')
</main>

<footer class="border-t border-ink-100 bg-ink-50/60">
  <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
    <div class="md:col-span-2">
      <div class="flex items-center gap-2.5"><span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-white"><x-icon name="truck" class="size-4" /></span><span class="font-display font-semibold">{{ $brand }}</span></div>
      <p class="mt-3 max-w-sm text-sm text-ink-600">{{ __('Trips, crew, fuel, invoices and books for truck owners and transport companies — India first, then the UAE.') }}</p>
      <a href="{{ route('signup') }}" class="btn-primary mt-5">{{ __('Start your :days-day free trial', ['days' => $trialDays]) }}</a>
    </div>
    <div>
      <h2 class="text-sm font-semibold">{{ __('Product') }}</h2>
      <ul class="mt-3 space-y-2 text-sm text-ink-600">
        @foreach ($nav as [$route, $label])<li><a href="{{ route($route) }}" class="hover:text-brand-700">{{ $label }}</a></li>@endforeach
      </ul>
    </div>
    <div>
      <h2 class="text-sm font-semibold">{{ __('Legal') }}</h2>
      <ul class="mt-3 space-y-2 text-sm text-ink-600">
        <li><a href="{{ route('site.legal', 'terms') }}" class="hover:text-brand-700">{{ __('Terms of service') }}</a></li>
        <li><a href="{{ route('site.legal', 'privacy') }}" class="hover:text-brand-700">{{ __('Privacy policy') }}</a></li>
        <li><a href="{{ route('site.legal', 'refund') }}" class="hover:text-brand-700">{{ __('Refund policy') }}</a></li>
      </ul>
      @if ($settings->get('support_email'))<p class="mt-4 text-sm text-ink-600">{{ $settings->get('support_email') }}</p>@endif
    </div>
  </div>
  <p class="pb-8 text-center text-[11px] text-ink-400">© {{ now()->year }} {{ $brand }} · {{ __('Version') }} {{ config('app.version') }} ({{ config('app.build') }})</p>
</footer>
</body>
</html>
