@php($brand = app(\App\Platform\Settings\PlatformSettings::class)->brandName())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locales::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', __('Sign in')) — {{ $brand }}</title>
{!! \App\Support\Fonts::preloadTags() !!}
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div class="nav-progress" data-nav-progress aria-hidden="true"></div>
<div class="grid min-h-screen lg:grid-cols-[1.1fr_minmax(420px,560px)]">

  <aside class="relative hidden overflow-hidden bg-brand-800 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
    <div class="pointer-events-none absolute -end-24 -top-24 size-[420px] rounded-full bg-brand-500/40 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -start-16 size-[360px] rounded-full bg-road-500/25 blur-3xl"></div>

    <div class="relative flex items-center gap-3">
      <span class="grid size-10 place-items-center rounded-xl bg-white/15"><x-icon name="truck" class="size-6" /></span>
      <span class="font-display text-xl font-semibold">{{ $brand }}</span>
    </div>

    <div class="relative max-w-md">
      <h2 class="font-display text-4xl font-semibold leading-tight">{{ __('Every trip, every rupee, proved from the road.') }}</h2>
      <ul class="mt-8 space-y-5">
        @foreach ([
          [__('Plan trips, the crew records the rest'), __('Fuel, tolls and payments with GPS, time and photos — even without network.')],
          [__('Ledgers for every person and party'), __('Drivers, helpers, clients, suppliers and vendors, settled without disputes.')],
          [__('Invoices, LR and books that meet the law'), __('GST and VAT ready, with your own templates.')],
        ] as [$headline, $detail])
          <li class="flex gap-3">
            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-white/15"><x-icon name="check" class="size-3.5" /></span>
            <div><b class="block font-semibold">{{ $headline }}</b><span class="text-sm text-white/75">{{ $detail }}</span></div>
          </li>
        @endforeach
      </ul>
    </div>

    <p class="relative text-xs text-white/60">{{ __('Version') }} {{ config('app.version') }} ({{ config('app.build') }})</p>
  </aside>

  <main class="flex items-center justify-center px-5 py-10 sm:px-8">
    <div class="w-full max-w-[440px] space-y-5">
      <div class="flex items-center gap-2 lg:hidden">
        <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><x-icon name="truck" class="size-5" /></span>
        <span class="font-display text-lg font-semibold">{{ $brand }}</span>
      </div>
      @include('partials.flash')
      @yield('content')
      <p class="text-center text-[11px] text-ink-400 lg:hidden">{{ __('Version') }} {{ config('app.version') }} ({{ config('app.build') }})</p>
    </div>
  </main>
</div>
</body>
</html>
