@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $short = fn ($v) => \App\Support\Money::short($v, $company->currency, $company->country === 'IN' ? 'in' : 'us');
  $c = $counts;
  $hour = (int) now($company->timezone ?: 'UTC')->format('G');
  $greeting = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <p class="text-sm font-medium text-brand-700">{{ $company->name }} · {{ now($company->timezone ?: 'UTC')->translatedFormat('l, j F') }}</p>
    <h1 class="mt-1 font-display text-3xl font-semibold">{{ $greeting }}, {{ auth()->user()->name }}</h1>
  </div>
  <div class="flex flex-wrap gap-2">
    @can('trip.create')<a href="{{ route('trips.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('New trip') }}</a>@endcan
  </div>
</div>

@if ($setup)
  <a href="{{ route('welcome') }}" class="card flex flex-wrap items-center gap-4 border-brand-200 bg-brand-50/60 p-5 hover:border-brand-300" data-setup-card>
    <span class="grid size-11 place-items-center rounded-xl bg-brand-600 text-white"><x-icon name="sparkle" class="size-5" /></span>
    <span class="min-w-0 flex-1">
      <span class="block font-semibold">{{ __('Getting started: :done of :total done', $setup) }}</span>
      <span class="block text-sm text-ink-600">{{ __('Your company details, a truck, a driver, the crew app and the first trip.') }}</span>
    </span>
    <span class="btn-primary">{{ __('Continue') }} <x-icon name="arrow-right" /></span>
  </a>
@endif

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <a href="{{ route('trucks.index') }}" class="stat block hover:border-brand-200">
    <div class="flex items-center justify-between"><span class="stat-label">{{ __('Trucks') }}</span><span class="nav-tile bg-blue-50 text-blue-700"><x-icon name="truck" class="size-4" /></span></div>
    <div class="stat-value">{{ $c['trucks']['total'] }}</div>
    <div class="mt-1 text-xs text-ink-500">{{ __(':a on trip · :b free · :c in workshop', ['a' => $c['trucks']['on_trip'], 'b' => $c['trucks']['idle'], 'c' => $c['trucks']['workshop']]) }}</div>
  </a>
  <a href="{{ route('trips.index') }}" class="stat block hover:border-brand-200">
    <div class="flex items-center justify-between"><span class="stat-label">{{ __('Trips running') }}</span><span class="nav-tile bg-amber-50 text-amber-700"><x-icon name="route" class="size-4" /></span></div>
    <div class="stat-value">{{ $c['trips']['running'] }}</div>
    <div class="mt-1 text-xs text-ink-500">{{ __(':a sent to crew · :b to settle', ['a' => $c['trips']['planned'], 'b' => $c['trips']['to_settle']]) }}</div>
  </a>
  <a href="{{ route('approvals.index') }}" class="stat block hover:border-brand-200">
    <div class="flex items-center justify-between"><span class="stat-label">{{ __('Entries to check') }}</span><span class="nav-tile bg-emerald-50 text-emerald-700"><x-icon name="check" class="size-4" /></span></div>
    <div class="stat-value">{{ $c['approvals']['waiting_check'] }}</div>
    <div class="mt-1 text-xs text-ink-500">{{ __(':a disputed · :b questions open', ['a' => $c['approvals']['disputed'], 'b' => $c['approvals']['queried']]) }}</div>
  </a>
  @if ($money)
    <a href="{{ auth()->user()->can('accounts.view') ? route('accounts.profit-loss', ['from' => now($company->timezone ?: 'UTC')->startOfMonth()->toDateString()]) : '#' }}" class="stat block hover:border-brand-200">
      <div class="flex items-center justify-between"><span class="stat-label">{{ __('Profit this month') }}</span><span class="nav-tile bg-violet-50 text-violet-700"><x-icon name="wallet" class="size-4" /></span></div>
      <div class="stat-value money {{ $profit['profit'] < 0 ? 'text-overdue' : '' }}">{{ $short($profit['profit']) }}</div>
      <div class="mt-1 text-xs text-ink-500">
        @if ($profit['change'] !== null){{ $profit['change'] >= 0 ? '▲' : '▼' }} {{ __(':p% against the same days last month', ['p' => abs($profit['change'])]) }}@else{{ __('Income :i · costs :e', ['i' => $short($profit['income']), 'e' => $short($profit['expense'])]) }}@endif
      </div>
    </a>
  @else
    <div class="stat"><div class="stat-label">{{ __('Alerts') }}</div><div class="stat-value">{{ count($alerts) }}</div></div>
  @endif
</div>

<div class="grid gap-6 xl:grid-cols-3">
  @if ($showMap)
    <section class="card overflow-hidden xl:col-span-2">
      <div class="flex items-center justify-between px-6 pt-5"><h2 class="text-lg font-semibold">{{ __('Live map') }}</h2><a href="{{ route('map.index') }}" class="text-sm text-brand-700 hover:underline">{{ __('Open the map') }}</a></div>
      <div class="mt-3 h-72 bg-ink-50" data-live-map data-url="{{ route('map.positions') }}" data-positions='@json($positions)'
        data-minutes="{{ __(':n min ago') }}" data-now="{{ __('just now') }}" data-never="{{ __('no GPS yet') }}" aria-label="{{ __('Map of the trucks on trips') }}"></div>
    </section>
  @endif
  <section class="card p-6 {{ $showMap ? '' : 'xl:col-span-3' }}">
    <h2 class="text-lg font-semibold">{{ __('Alerts') }}</h2>
    <ul class="mt-3 space-y-2 text-sm">
      @forelse ($alerts as $a)
        <li>
          <a href="{{ $a['url'] ?? '#' }}" class="flex items-start gap-2 rounded-xl px-2 py-1.5 hover:bg-ink-50">
            <x-icon name="alert" class="mt-0.5 size-4 shrink-0 {{ ['critical' => 'text-overdue', 'serious' => 'text-orange-600', 'warning' => 'text-partial'][$a['level']] }}" />
            <span><span class="sr-only">{{ __(ucfirst($a['level'])) }}: </span>{{ $a['text'] }}</span>
          </a>
        </li>
      @empty
        <li class="flex items-center gap-2 text-ink-500"><x-icon name="check" class="size-4 text-emerald-600" /> {{ __('Nothing needs you right now.') }}</li>
      @endforelse
    </ul>
  </section>
</div>

@if ($money || $spending)
  <div class="grid gap-6 xl:grid-cols-3">
    @if ($money)
      <section class="card p-6 xl:col-span-2">
        <h2 class="text-lg font-semibold">{{ __('Income and costs, last six months') }}</h2>
        <p class="text-xs text-ink-500">{{ __('From the books: bills issued, and every cost posted (road spending, hire, bata, repairs).') }}</p>
        <div class="mt-3">
          @include('charts._columns', [
            'title' => __('Income and costs by month'),
            'rows' => array_map(fn ($r) => ['label' => $r['label'], 'title' => \Carbon\CarbonImmutable::parse($r['month'] . '-01')->translatedFormat('F Y'), 'values' => ['income' => $r['income'], 'cost' => $r['cost']]], $months),
            'series' => [['key' => 'income', 'label' => __('Income'), 'color' => '#2a78d6'], ['key' => 'cost', 'label' => __('Costs'), 'color' => '#eb6834']],
            'format' => $m, 'short' => $short,
          ])
        </div>
      </section>
    @endif
    <section class="card p-6 {{ $money ? '' : 'xl:col-span-3' }}">
      <h2 class="text-lg font-semibold">{{ __('Spent on the road today') }}</h2>
      <dl class="mt-3 space-y-1.5 text-sm">
        @forelse ($spending as $label => $amount)
          <div class="flex justify-between"><dt class="text-ink-700">{{ $label }}</dt><dd class="money">{{ $m($amount) }}</dd></div>
        @empty
          <p class="text-ink-500">{{ __('No spending entered today yet.') }}</p>
        @endforelse
        @if ($spending)<div class="flex justify-between border-t border-ink-100 pt-1.5 font-semibold"><dt>{{ __('Total') }}</dt><dd class="money">{{ $m(array_sum($spending)) }}</dd></div>@endif
      </dl>
    </section>
  </div>
@endif

@if ($queues)
  <section>
    <h2 class="mb-3 text-lg font-semibold">{{ __('From LR to cash') }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        ['pod_pending', __('POD pending'), __('Delivered, the proof of delivery not in yet'), route('trips.index', ['status' => 'ended'])],
        ['ready_to_bill', __('Ready to bill'), __('POD in, no bill yet'), route('invoices.create')],
        ['not_submitted', __('Billed, not sent'), __('Issued but not yet sent or handed to the client'), route('invoices.index', ['tab' => 'open'])],
        ['overdue', __('Overdue bills'), $queues['overdue_amount'] ? __(':amount past the due date', ['amount' => $m($queues['overdue_amount'])]) : __('Past the due date'), route('invoices.index', ['tab' => 'overdue'])],
      ] as [$key, $label, $hint, $url])
        <a href="{{ $url }}" class="stat block hover:border-brand-200">
          <div class="stat-label">{{ $label }}</div>
          <div class="stat-value {{ $key === 'overdue' && $queues[$key] ? 'text-overdue' : '' }}">{{ $queues[$key] }}</div>
          <div class="mt-1 text-xs text-ink-500">{{ $hint }}</div>
        </a>
      @endforeach
    </div>
  </section>
@endif
@endsection
