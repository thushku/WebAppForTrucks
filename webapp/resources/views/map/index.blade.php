@extends('layouts.app')
@section('title', __('Live map'))
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-semibold">{{ __('Live map') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Every running trip where its crew’s phone last was. Refreshes every minute. Orange: the phone has gone quiet.') }}</p>
  </div>
</div>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
  <section class="card overflow-hidden">
    <div class="h-[70vh] min-h-96 bg-ink-50" data-live-map data-wheel="on" data-list="#map-list" data-url="{{ route('map.positions') }}" data-positions='@json($positions)'
      data-minutes="{{ __(':n min ago') }}" data-now="{{ __('just now') }}" data-never="{{ __('no GPS yet') }}" aria-label="{{ __('Map of the trucks on trips') }}"></div>
  </section>
  <section class="card overflow-hidden">
    <h2 class="px-5 pt-5 font-semibold">{{ trans_choice(':count trip running|:count trips running', $positions->count()) }}</h2>
    <ul id="map-list" class="mt-2 divide-y divide-ink-100">
      @forelse ($positions as $p)
        <li class="px-5 py-3">
          <div class="flex items-center justify-between gap-2">
            <a href="{{ $p['url'] }}" class="font-mono text-sm font-semibold hover:text-brand-700">{{ $p['vehicle'] }}</a>
            <span class="text-xs {{ $p['quiet'] ? 'font-semibold text-orange-700' : 'text-ink-500' }}" data-ago="{{ $p['trip_id'] }}">{{ $p['minutes'] === null ? __('no GPS yet') : ($p['minutes'] < 1 ? __('just now') : __(':n min ago', ['n' => $p['minutes']])) }}</span>
          </div>
          <div class="text-xs text-ink-500">{{ $p['driver'] ?? '—' }} · {{ collect([$p['from'], $p['to']])->filter()->join(' → ') }}</div>
          <div class="text-xs text-ink-400">{{ $p['trip'] }}@if ($p['speed'] !== null) · {{ round($p['speed']) }} km/h @endif</div>
        </li>
      @empty
        <li class="px-5 py-8 text-center text-sm text-ink-500">{{ __('No trip is running.') }}</li>
      @endforelse
    </ul>
  </section>
</div>
<p class="text-xs text-ink-500">{{ __('Map tiles © OpenStreetMap contributors. Positions come from the crew app; a truck’s own GPS device can be connected later.') }}</p>
@endsection
