@extends('layouts.app')
@section('title', __('Fines'))
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $tz = $now->getTimezone()->getName();
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('trucks.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Trucks') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('Fines and e-challans') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Every traffic fine with the truck and the driver of that moment: paid by the office or on the road, disputed, or taken from the driver’s pay.') }}</p>
  </div>
  @can('truck.manage')<a href="#add" class="btn-primary"><x-icon name="plus" /> {{ __('Record a fine') }}</a>@endcan
</div>

@if ($open['count'])
  <p class="mt-5 rounded-2xl border {{ $open['late'] ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900' }} px-4 py-3 text-sm" data-open-fines>
    {{ trans_choice(':count fine unpaid, :amount|:count fines unpaid, :amount', $open['count'], ['amount' => $m($open['amount'])]) }}@if ($open['late']) · <strong>{{ trans_choice(':count late|:count late', $open['late']) }}</strong>@endif
  </p>
@endif

@if ($points->isNotEmpty())
  <section class="card mt-5 p-5" data-points>
    <h2 class="font-semibold">{{ __('Black points in the last 12 months') }}</h2>
    <ul class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
      @foreach ($points as $p)
        @php($share = min(1, $p->points / \App\Modules\Fleet\Models\Fine::POINTS_LIMIT))
        <li class="text-sm">
          <div class="flex items-center justify-between gap-2"><a href="{{ route('trucks.fines', ['person' => $p->person_id, 'tab' => 'all']) }}" class="font-medium hover:text-brand-700">{{ $pointNames[$p->person_id] ?? '—' }}</a>
            <span class="{{ $p->points >= \App\Modules\Fleet\Models\Fine::POINTS_LIMIT ? 'font-semibold text-overdue' : ($share >= 0.5 ? 'font-semibold text-partial' : 'text-ink-600') }}">{{ __(':n of :limit points', ['n' => $p->points, 'limit' => \App\Modules\Fleet\Models\Fine::POINTS_LIMIT]) }}</span></div>
          <div class="mt-1 h-1.5 rounded-full bg-ink-100"><div class="h-1.5 rounded-full {{ $share >= 1 ? 'bg-red-500' : ($share >= 0.5 ? 'bg-amber-500' : 'bg-brand-500') }}" style="width: {{ (int) round($share * 100) }}%"></div></div>
        </li>
      @endforeach
    </ul>
  </section>
@endif

<div class="mt-5 flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-2" role="tablist">
    @foreach (\App\Http\Controllers\Fleet\FineController::TABS as $key => $label)
      <a href="{{ route('trucks.fines', array_filter(['tab' => $key, 'q' => $q, 'truck' => $truck?->id, 'person' => $person?->id])) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __($label) }} <span class="text-xs text-ink-400">{{ $counts[$key] }}</span></a>
    @endforeach
  </div>
  <form method="GET" class="flex gap-2">
    @foreach (array_filter(['tab' => $tab, 'truck' => $truck?->id, 'person' => $person?->id]) as $k => $val)<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endforeach
    <input type="search" name="q" value="{{ $q }}" class="input py-2" placeholder="{{ __('Challan no., offence or place') }}" aria-label="{{ __('Search fines') }}">
    <button class="btn-secondary"><x-icon name="search" /><span class="sr-only">{{ __('Search') }}</span></button>
  </form>
</div>
@if ($truck || $person)
  <p class="mt-3 text-sm text-ink-600">{{ __('Only :who', ['who' => $truck?->reg_no ?? $person?->name]) }} · <a href="{{ route('trucks.fines', array_filter(['tab' => $tab])) }}" class="text-brand-700 hover:underline">{{ __('Show all') }}</a></p>
@endif

<section class="card mt-4 overflow-hidden">
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Challan') }}</th><th>{{ __('Truck') }}</th><th>{{ __('Driver') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Who bears it') }}</th><th>{{ __('Status') }}</th></tr></thead>
      <tbody>
        @forelse ($list as $fine)
          <tr>
            <td class="whitespace-nowrap text-sm">{{ $fine->issued_at->setTimezone($tz)->format('d M Y') }}<div class="text-xs text-ink-500">{{ $fine->issued_at->setTimezone($tz)->format('H:i') }}</div></td>
            <td class="text-sm"><a href="{{ route('trucks.fines.show', $fine) }}" class="docno font-mono font-semibold text-brand-700 hover:underline">{{ $fine->challan_no ?: __('No challan no.') }}</a>
              <div class="text-xs text-ink-500">{{ $fine->offence }}{{ $fine->place ? ' · ' . $fine->place : '' }}@if ($fine->points) · {{ trans_choice(':count point|:count points', $fine->points) }}@endif</div></td>
            <td class="whitespace-nowrap text-sm">@if ($fine->truck)<a href="{{ route('trucks.show', $fine->truck) }}" class="font-mono hover:text-brand-700">{{ $fine->truck->reg_no }}</a>@else — @endif</td>
            <td class="text-sm">{{ $fine->person?->name ?? '—' }}</td>
            <td class="money whitespace-nowrap text-end text-sm">{{ $m($fine->amount) }}</td>
            <td class="text-sm">@include('trucks.partials.fine-bears')</td>
            <td>@include('trucks.partials.fine-status')</td>
          </tr>
        @empty
          <tr><td colspan="7" class="py-10 text-center text-ink-500">{{ $q !== '' ? __('No fine matches.') : __('No fines here.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($list->hasPages())<div class="border-t border-ink-100 px-6 py-3">{{ $list->links() }}</div>@endif
</section>

@can('truck.manage')
<form id="add" method="POST" action="{{ route('trucks.fines.store') }}" class="card mt-6 grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4" novalidate>@csrf
  <div class="sm:col-span-2 xl:col-span-4">
    <h2 class="text-lg font-semibold">{{ __('Record a fine') }}</h2>
    <p class="text-sm text-ink-500">{{ __('From the e-challan portal, an SMS or the paper challan. The trip and the driver are found from the truck and the time; a fine the driver bears comes off their next pay run.') }}</p>
  </div>
  @include('trucks.partials.fine-form', ['fine' => null])
  <div class="text-end sm:col-span-2 xl:col-span-4"><button class="btn-primary"><x-icon name="check" /> {{ __('Record the fine') }}</button></div>
</form>
@endcan
@endsection
