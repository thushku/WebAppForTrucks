@php
  $context = app(\App\Platform\Tenancy\CompanyContext::class);
  $company = $context->has() ? $context->company() : null;
  $account = auth()->user();
  $brand   = app(\App\Platform\Settings\PlatformSettings::class)->brandName();
  $rtl     = \App\Support\Locales::isRtl(app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('Dashboard')) — {{ $brand }}</title>
{!! \App\Support\Fonts::preloadTags() !!}
<style>{!! \App\Support\Fonts::linkedCss(['Noto Sans', 'Noto Sans Devanagari', 'Noto Sans Arabic']) !!}</style>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('head')
</head>
<body class="min-h-full">
<div class="nav-progress" data-nav-progress aria-hidden="true"></div>

@if (session()->pull('splash'))
  {{-- Once after signing in: the brand and a progress bar that follows real loading (ui.js). --}}
  <div class="splash" data-splash role="status" aria-label="{{ __('Opening your data') }}">
    <div class="text-center">
      <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-white/15"><x-icon name="truck" class="size-9" /></div>
      <div class="mt-4 font-display text-2xl font-semibold">{{ $brand }}</div>
      <p class="mt-1 text-sm text-white/70">{{ __('Opening your data…') }}</p>
      <div class="splash-bar mx-auto mt-5"><div class="splash-fill"></div></div>
      <p class="mt-4 text-xs text-white/50">{{ __('Version') }} {{ config('app.version') }} ({{ config('app.build') }})</p>
    </div>
  </div>
@endif

<div class="min-h-screen lg:grid lg:grid-cols-[264px_1fr]">
  <aside id="sidebar" class="hidden flex-col gap-4 border-e border-ink-100 bg-white px-3.5 py-5 lg:sticky lg:top-0 lg:flex lg:h-screen">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2">
      <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-white shadow-[var(--shadow-card)]"><x-icon name="truck" class="size-6" /></span>
      <span class="min-w-0">
        <span class="block font-display text-lg font-semibold leading-tight">{{ $brand }}</span>
        <span class="block truncate text-xs text-ink-500">{{ $company?->name }}</span>
      </span>
    </a>

    @include('partials.main-nav')

    @if ($company?->onTrial())
      @php($days = $company->trialDaysLeft())
      <div class="rounded-2xl border border-ink-100 bg-ink-50 p-3.5">
        <div class="flex items-center justify-between text-sm">
          <span class="font-semibold">{{ __('Free trial') }}</span>
          <span class="pill bg-brand-50 text-brand-700">{{ $days }} {{ trans_choice('day|days', $days) }} {{ __('left') }}</span>
        </div>
        <p class="mt-2 text-xs text-ink-500">{{ __('Choose a plan any time before it ends — nothing is lost.') }}</p>
      </div>
    @endif

    {{-- The version line (guideline: visible versioning). --}}
    <p class="mt-auto px-2 text-[11px] text-ink-400" data-version>{{ __('Version') }} {{ config('app.version') }} ({{ config('app.build') }})</p>
  </aside>

  <div class="flex min-h-screen flex-col">
    <header class="fl-topbar sticky top-0 z-20 flex items-center gap-3 border-b border-ink-100 bg-white/85 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">
      <button type="button" class="btn-ghost -ms-2 px-2 lg:hidden" data-toggle="sidebar" aria-controls="sidebar" aria-expanded="false" aria-label="{{ __('Menu') }}">
        <x-icon name="menu" />
      </button>
      <h1 class="min-w-0 flex-1 truncate font-display text-lg font-semibold">@yield('title', __('Dashboard'))</h1>

      <div class="relative" data-menu>
        <button type="button" data-menu-button class="flex items-center gap-2 rounded-full border border-ink-100 bg-white py-1 ps-1 pe-3 text-sm hover:border-brand-300" aria-haspopup="menu" aria-expanded="false">
          <span class="grid size-8 place-items-center rounded-full bg-brand-100 font-display text-xs font-bold text-brand-800">{{ $account?->initials() }}</span>
          <span class="hidden max-w-[10rem] truncate font-medium sm:block">{{ $account?->name }}</span>
        </button>
        <div data-menu-panel class="card absolute end-0 z-30 mt-2 hidden w-64 p-2" role="menu">
          <div class="px-3 py-2">
            <div class="truncate text-sm font-semibold">{{ $account?->name }}</div>
            <div class="truncate text-xs text-ink-500">{{ $account?->email ?? $account?->mobile }}</div>
          </div>
          <div class="my-1 border-t border-ink-100"></div>
          @if ($account && $account->activeMemberships()->count() > 1)
            <a href="{{ route('company.choose') }}" class="nav-link" role="menuitem"><x-icon name="switch" /> {{ __('Switch company') }}</a>
          @endif
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nav-link w-full" role="menuitem"><x-icon name="log-out" /> {{ __('Sign out') }}</button>
          </form>
        </div>
      </div>
    </header>

    <main class="flex-1 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      @if ($company?->isReadOnly())
        <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
          <x-icon name="lock" class="size-5 shrink-0" />
          <p>{{ __('This company is read-only because its plan has ended. Everything is kept; renew to make changes.') }}</p>
        </div>
      @endif
      @yield('content')
    </main>
  </div>

  <div class="pointer-events-none fixed inset-x-4 top-[4.25rem] z-30 flex flex-col items-end gap-2 sm:inset-x-auto sm:end-6 sm:w-[30rem] [&_[data-flash]]:pointer-events-auto [&_[data-flash]]:w-full">
    @include('partials.flash')
  </div>
</div>
</body>
</html>
