@extends('layouts.app')
@section('title', __('Tyres'))
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $tabs = ['' => __('In use')] + array_map(fn ($l) => __($l), \App\Modules\Fleet\Models\Tyre::STATUSES);
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('trucks.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Trucks') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('Tyres') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Every tyre by its serial number: in the store, on a truck, at the retreader or scrapped — the km it has run and what each km costs. Fit, take off and move tyres from the truck’s Tyres tab.') }}</p>
  </div>
  @can('complaint.manage')<a href="#add" class="btn-primary"><x-icon name="plus" /> {{ __('Add tyres') }}</a>@endcan
</div>

@if ($near->isNotEmpty())
  <section class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" data-near-end>
    <h2 class="font-semibold">{{ trans_choice(':count tyre is near the end of its expected km|:count tyres are near the end of their expected km', $near->count()) }}</h2>
    <ul class="mt-2 grid gap-1 sm:grid-cols-2 xl:grid-cols-3">
      @foreach ($near->take(12) as $r)
        <li><a href="{{ route('trucks.tyres.show', $r['tyre']) }}" class="font-mono font-semibold hover:underline">{{ $r['tyre']->serial_no }}</a> · <a href="{{ route('trucks.show', [$r['tyre']->truck_id, 'tab' => 'tyres']) }}" class="hover:underline">{{ $r['tyre']->truck?->reg_no }} {{ $r['tyre']->position }}</a> · {{ number_format($r['km']) }} / {{ number_format($r['tyre']->expected_km) }} km ({{ (int) round($r['used'] * 100) }}%)</li>
      @endforeach
    </ul>
  </section>
@endif

<div class="mt-5 flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-2" role="tablist">
    @foreach ($tabs as $key => $label)
      @php($n = $key === '' ? $counts->except('scrapped')->sum() : ($counts[$key] ?? 0))
      <a href="{{ route('trucks.tyres', array_filter(['status' => $key, 'q' => $q])) }}" role="tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $status === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }} <span class="text-xs text-ink-400">{{ $n }}</span></a>
    @endforeach
  </div>
  <form method="GET" class="flex gap-2">
    @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
    <input type="search" name="q" value="{{ $q }}" class="input py-2" placeholder="{{ __('Serial no., brand or size') }}" aria-label="{{ __('Search tyres') }}">
    <button class="btn-secondary"><x-icon name="search" /><span class="sr-only">{{ __('Search') }}</span></button>
  </form>
</div>

<section class="card mt-4 overflow-hidden">
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Serial no.') }}</th><th>{{ __('Tyre') }}</th><th>{{ __('Where') }}</th><th class="text-end">{{ __('Km run') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Per km') }}</th></tr></thead>
      <tbody>
        @forelse ($list as $t)
          @php($km = $service->kmRun($t))
          @php($used = $service->used($t))
          <tr>
            <td><a href="{{ route('trucks.tyres.show', $t) }}" class="docno font-mono font-semibold text-brand-700 hover:underline">{{ $t->serial_no }}</a></td>
            <td class="text-sm">{{ $t->brand }}{{ $t->model ? ' ' . $t->model : '' }}<div class="text-xs text-ink-500">{{ $t->size }} · {{ $t->kind === 'retread' ? trans_choice('retreaded once|retreaded :count times', $t->retreads) : __('New') }}</div></td>
            <td class="text-sm">
              @if ($t->status === 'fitted' && $t->truck)
                <a href="{{ route('trucks.show', [$t->truck, 'tab' => 'tyres']) }}" class="font-mono hover:text-brand-700">{{ $t->truck->reg_no }}</a> <span class="font-mono text-xs text-ink-500">{{ $t->position }}</span>
              @else
                <span class="pill {{ ['in_store' => 'bg-brand-50 text-brand-700', 'retreading' => 'bg-amber-50 text-partial', 'scrapped' => 'bg-ink-100 text-ink-500'][$t->status] ?? '' }}">{{ __(\App\Modules\Fleet\Models\Tyre::STATUSES[$t->status]) }}</span>
              @endif
            </td>
            <td class="whitespace-nowrap text-end text-sm {{ $used !== null && $used >= \App\Modules\Fleet\Services\Tyres::NEAR_END ? 'font-semibold text-partial' : '' }}">{{ number_format($km) }}@if ($t->expected_km)<div class="text-xs font-normal text-ink-500">/ {{ number_format($t->expected_km) }}</div>@endif</td>
            <td class="money text-end text-sm">{{ $m($t->cost) }}</td>
            <td class="money text-end text-sm">{{ $t->costPerKm() !== null ? $m($t->costPerKm()) : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-10 text-center text-ink-500">{{ $q !== '' ? __('No tyre matches.') : __('No tyres here yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($list->hasPages())<div class="border-t border-ink-100 px-6 py-3">{{ $list->links() }}</div>@endif
</section>

@if ($brands->isNotEmpty())
<section class="card mt-6 overflow-hidden">
  <h2 class="px-6 pt-5 font-semibold">{{ __('What each brand gives') }}</h2>
  <p class="px-6 text-sm text-ink-500">{{ __('For the tyres finished (scrapped): the km they ran and what each km cost after the scrap money — to choose the next tyres.') }}</p>
  <div class="relative mt-2 overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Brand') }}</th><th>{{ __('Size') }}</th><th class="text-end">{{ __('Tyres') }}</th><th class="text-end">{{ __('Still running') }}</th><th class="text-end">{{ __('Finished') }}</th><th class="text-end">{{ __('Km a tyre gave') }}</th><th class="text-end">{{ __('Cost per km, actual') }}</th><th class="text-end">{{ __('Cost per km, expected') }}</th></tr></thead>
      <tbody>
        @foreach ($brands as $b)
          <tr>
            <td class="text-sm font-medium">{{ $b->brand }}</td><td class="text-sm">{{ $b->size }}</td>
            <td class="text-end text-sm">{{ $b->tyres }}</td><td class="text-end text-sm">{{ $b->running }}</td><td class="text-end text-sm">{{ $b->finished }}</td>
            <td class="text-end text-sm">{{ $b->km ? number_format($b->km) : '—' }}</td>
            <td class="money text-end text-sm font-semibold">{{ $b->per_km !== null ? $m($b->per_km) : '—' }}</td>
            <td class="money text-end text-sm">{{ $b->expected_per_km !== null ? $m($b->expected_per_km) : '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@can('complaint.manage')
<form id="add" method="POST" action="{{ route('trucks.tyres.store') }}" class="card mt-6 grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4" novalidate>@csrf
  <div class="sm:col-span-2 xl:col-span-4">
    <h2 class="text-lg font-semibold">{{ __('Add tyres to the store') }}</h2>
    <p class="text-sm text-ink-500">{{ __('A set bought together: one serial number per line, each a tyre with the same details. The dealer’s bill is paid or booked as usual — the register keeps no books.') }}</p>
  </div>
  <div class="sm:row-span-2"><label class="label" for="ty_serials">{{ __('Serial numbers') }}</label><textarea id="ty_serials" name="serials" rows="5" class="input font-mono" required placeholder="MRF2611A0123&#10;MRF2611A0124">{{ old('serials') }}</textarea>@error('serials')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="ty_brand">{{ __('Brand') }}</label><input id="ty_brand" name="brand" value="{{ old('brand') }}" class="input" maxlength="40" required placeholder="MRF, Apollo, JK, Michelin…">@error('brand')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="ty_model">{{ __('Model / pattern') }}</label><input id="ty_model" name="model" value="{{ old('model') }}" class="input" maxlength="40">@error('model')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="ty_size">{{ __('Size') }}</label><input id="ty_size" name="size" value="{{ old('size') }}" class="input" maxlength="30" required placeholder="295/80 R22.5">@error('size')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="ty_kind">{{ __('New or retreaded') }}</label><select id="ty_kind" name="kind" class="input">@foreach (\App\Modules\Fleet\Models\Tyre::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind', 'new') === $k)>{{ __($l) }}</option>@endforeach</select></div>
  <div><label class="label" for="ty_cost">{{ __('Cost of each') }}</label><input id="ty_cost" name="cost" type="number" step="0.01" min="0" value="{{ old('cost') }}" class="input" required>@error('cost')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="ty_km">{{ __('Expected km') }}</label><input id="ty_km" name="expected_km" type="number" min="1000" value="{{ old('expected_km') }}" class="input" placeholder="80000"><span class="hint">{{ __('What a tyre like it should run; its cost per km comes from it.') }}</span>@error('expected_km')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="ty_date">{{ __('Bought on') }}</label><input id="ty_date" name="purchased_on" type="date" value="{{ old('purchased_on', $today->toDateString()) }}" max="{{ $today->toDateString() }}" class="input">@error('purchased_on')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="ty_vendor">{{ __('From') }}</label><select id="ty_vendor" name="vendor_party_id" class="input"><option value="">—</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}" @selected((int) old('vendor_party_id') === $id)>{{ $name }}</option>@endforeach</select></div>
  @if ($branches->count() > 1)<div><label class="label" for="ty_branch">{{ __('Branch store') }}</label><select id="ty_branch" name="branch_id" class="input"><option value="">{{ __('Shared') }}</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((int) old('branch_id') === $id)>{{ $name }}</option>@endforeach</select></div>@endif
  <div class="sm:col-span-2"><label class="label" for="ty_notes">{{ __('Notes') }}</label><input id="ty_notes" name="notes" value="{{ old('notes') }}" class="input" maxlength="255" placeholder="{{ __('Bill no., warranty…') }}"></div>
  <div class="text-end sm:col-span-2 xl:col-span-4"><button class="btn-primary"><x-icon name="check" /> {{ __('Add to the store') }}</button></div>
</form>
@endcan
@endsection
