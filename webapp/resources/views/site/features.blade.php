@extends('layouts.site')
@section('title', __('Features'))
@section('description', __('Trips for full and part loads, a crew app that works offline, fuel control, invoices and LR, GST-ready accounts, trucks and documents.'))
@section('content')
<section class="site-hero">
  <div class="mx-auto max-w-7xl px-4 pb-24 pt-14 sm:px-6 lg:pb-28">
    <span class="site-kicker">{{ __('Features') }}</span>
    <h1 class="mt-4 max-w-3xl font-display text-4xl font-semibold leading-tight sm:text-5xl">{{ __('From the loading point to the bank, in one place') }}</h1>
    <p class="mt-4 max-w-2xl text-lg text-white/80">{{ __('Each part works on its own and all of them share the same trips, people and books — nothing is typed twice.') }}</p>
    <nav class="mt-8 flex flex-wrap gap-2" aria-label="{{ __('Features') }}">
      @foreach ($sections as $s)<a href="#{{ $s['id'] }}" class="rounded-full border border-white/20 px-3 py-1.5 text-sm text-white/85 hover:bg-white/10">{{ $s['title'] }}</a>@endforeach
    </nav>
  </div>
</section>

<div class="mx-auto max-w-7xl space-y-20 px-4 py-20 sm:px-6">
  @foreach ($sections as $i => $s)
    <section id="{{ $s['id'] }}" class="site-reveal grid scroll-mt-24 items-center gap-10 lg:grid-cols-2">
      <div class="{{ $i % 2 ? 'lg:order-2' : '' }}">
        <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon :name="$s['icon']" class="size-5" /></span>
        <h2 class="mt-4 font-display text-3xl font-semibold">{{ $s['title'] }} @if ($s['soon'] ?? false)<span class="pill ms-2 bg-amber-50 align-middle text-amber-800">{{ __('Coming soon') }}</span>@endif</h2>
        <p class="mt-3 text-ink-600">{{ $s['lead'] }}</p>
        <ul class="mt-5 space-y-2.5 text-sm text-ink-800">
          @foreach ($s['points'] as $point)<li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /> {{ $point }}</li>@endforeach
        </ul>
      </div>
      <div class="{{ $i % 2 ? 'lg:order-1' : '' }}" aria-hidden="true">
        <div class="site-card relative overflow-hidden bg-gradient-to-br from-brand-50 to-white p-8">
          <div class="absolute -end-10 -top-10 size-40 rounded-full bg-brand-100/70"></div>
          <div class="relative space-y-3">
            @foreach ($s['sample'] as [$label, $value, $tone])
              <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm">
                <span class="text-sm text-ink-600">{{ $label }}</span>
                <span class="font-semibold {{ ['good' => 'text-emerald-700', 'warn' => 'text-amber-700', 'bad' => 'text-overdue'][$tone] ?? 'text-ink-900' }}">{{ $value }}</span>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </section>
  @endforeach
</div>

<section class="px-4 pb-24 sm:px-6">
  <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-6 rounded-[2rem] bg-ink-900 px-8 py-10 text-white">
    <div><h2 class="font-display text-2xl font-semibold">{{ __('See it with your own trucks') }}</h2><p class="mt-1 text-white/75">{{ __(':days days free, every feature.', ['days' => $trialDays]) }}</p></div>
    <a href="{{ route('signup') }}" class="btn-lg btn rounded-xl bg-road-400 font-semibold text-ink-900 hover:bg-road-500">{{ __('Start free trial') }}</a>
  </div>
</section>
@endsection
