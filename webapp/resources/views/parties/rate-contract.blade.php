@extends('layouts.app')
@section('title', $contract->exists ? $contract->label : __('New rate contract'))
@section('content')
@php
  $c = $contract;
  $editable = ! $c->exists || $c->isEditable();
  $lines = old('lines', $c->exists ? $c->lines->map(fn ($l) => $l->only(['from_place_id', 'to_place_id', 'material', 'truck_type', 'basis', 'rate', 'min_weight_mt', 'detention_free_hours', 'detention_rate_per_day', 'loading_charge', 'unloading_charge', 'extra_drop_charge']))->all() : []);
  if ($editable) { $lines[] = []; }
@endphp
<div class="flex flex-wrap items-center gap-3">
  <a href="{{ route('parties.show', [$party, 'tab' => 'rates']) }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ $party->name }}</a>
  @if ($c->exists)
    <span class="pill {{ $c->status === 'approved' ? 'bg-emerald-50 text-paid' : ($c->status === 'draft' ? 'bg-amber-50 text-partial' : 'bg-ink-100 text-ink-500') }}">{{ __(\App\Modules\Parties\Models\RateContract::STATUSES[$c->status]) }}</span>
  @endif
  @error('contract')<span class="error">{{ $message }}</span>@enderror
</div>

<form method="POST" action="{{ $c->exists ? route('parties.rates.update', [$party, $c]) : route('parties.rates.store', $party) }}" class="space-y-6" novalidate>
  @csrf
  @if ($c->exists) @method('PUT') @endif
  <fieldset @disabled(! $editable) class="space-y-6">
  <section class="card p-6">
    <div class="grid gap-4 sm:grid-cols-4">
      <div class="sm:col-span-2"><label class="label" for="rc_name">{{ __('Name') }}</label><input id="rc_name" name="name" class="input" value="{{ old('name', $c->name) }}" maxlength="120" required>@error('name')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="rc_from">{{ __('Valid from') }}</label><input id="rc_from" name="valid_from" type="date" class="input" value="{{ old('valid_from', $c->valid_from?->toDateString() ?? $c->valid_from) }}" required>@error('valid_from')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="rc_to">{{ __('Valid to') }}</label><input id="rc_to" name="valid_to" type="date" class="input" value="{{ old('valid_to', $c->valid_to?->toDateString()) }}">@error('valid_to')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="rc_diesel">{{ __('Diesel price the rates assume') }}</label><input id="rc_diesel" name="diesel_base_price" type="number" min="0" step="0.01" class="input" value="{{ old('diesel_base_price', $c->diesel_base_price) }}"></div>
      <div><label class="label" for="rc_esc">{{ __('Rate change % per ₹1 of diesel') }}</label><input id="rc_esc" name="escalation_percent_per_rupee" type="number" min="0" step="0.001" class="input" value="{{ old('escalation_percent_per_rupee', $c->escalation_percent_per_rupee) }}"></div>
      <div class="sm:col-span-2"><label class="label" for="rc_terms">{{ __('Payment terms') }}</label><input id="rc_terms" name="payment_terms" class="input" value="{{ old('payment_terms', $c->payment_terms) }}" maxlength="1000"></div>
    </div>
  </section>

  <section class="card overflow-hidden">
    <div class="flex items-center justify-between p-6 pb-3">
      <h2 class="text-lg font-semibold">{{ __('Route rates') }}</h2>
      @if ($places->isEmpty())<a href="{{ route('settings.places') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Add places first') }}</a>@endif
    </div>
    @error('lines.*')<p class="error px-6">{{ $message }}</p>@enderror
    @foreach ($errors->getMessages() as $key => $msgs)
      @if (str_starts_with($key, 'lines.'))<p class="error px-6">{{ $msgs[0] }}</p>@endif
    @endforeach
    <div class="overflow-x-auto">
      @if ($editable)
        <table class="table min-w-[1180px]" data-rate-lines>
          <thead><tr>
            <th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Material') }}</th><th>{{ __('Truck type') }}</th><th>{{ __('Basis') }}</th>
            <th>{{ __('Rate') }}</th><th>{{ __('Min. MT') }}</th><th>{{ __('Free hrs') }}</th><th>{{ __('Detention/day') }}</th>
          </tr></thead>
          <tbody>
            @foreach ($lines as $i => $line)
              @include('parties.partials.rate-line', ['i' => $i, 'line' => $line])
            @endforeach
          </tbody>
        </table>
        <template data-rate-line-template>@include('parties.partials.rate-line', ['i' => '__N__', 'line' => []])</template>
      @else
        @php($placeNames = $places->mapWithKeys(fn ($pl) => [$pl->id => $pl->name . ' (' . $pl->short_code . ')']))
        <table class="table">
          <thead><tr><th>{{ __('Route') }}</th><th>{{ __('Material') }}</th><th>{{ __('Truck type') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Min. MT') }}</th><th class="text-end">{{ __('Detention') }}</th></tr></thead>
          <tbody>
            @foreach ($c->lines as $l)
              <tr>
                <td class="font-medium">{{ $placeNames[$l->from_place_id] ?? '?' }} → {{ $placeNames[$l->to_place_id] ?? '?' }}</td>
                <td>{{ $materials->firstWhere('code', $l->material)?->name ?? __('Any') }}</td>
                <td>{{ $truckTypes->firstWhere('code', $l->truck_type)?->name ?? __('Any') }}</td>
                <td class="money text-end font-semibold">{{ \App\Support\Money::company($l->rate) }} <span class="text-xs font-normal text-ink-500">{{ __(\App\Modules\Parties\Models\RateContractLine::BASES[$l->basis] ?? $l->basis) }}</span></td>
                <td class="text-end">{{ $l->min_weight_mt !== null ? rtrim(rtrim((string) $l->min_weight_mt, '0'), '.') : '—' }}</td>
                <td class="nowrap text-end text-sm">{{ $l->detention_rate_per_day !== null ? \App\Support\Money::company($l->detention_rate_per_day) . ' / ' . __('day after :h h', ['h' => $l->detention_free_hours ?? 0]) : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
    @if ($editable)
      <div class="p-4"><button type="button" class="btn-ghost text-sm" data-add-rate-line><x-icon name="plus" /> {{ __('Add a row') }}</button></div>
    @endif
  </section>
  </fieldset>

  @if ($editable)
    <div class="flex justify-end"><button type="submit" class="btn-secondary"><x-icon name="check" /> {{ __('Save draft') }}</button></div>
  @endif
</form>

@if ($c->exists)
  <div class="flex flex-wrap justify-end gap-2">
    @if ($c->status === 'draft')
      <form method="POST" action="{{ route('parties.rates.approve', [$party, $c]) }}" data-confirm="{{ __('Approve these rates? New trips will use them.') }}">@csrf<button class="btn-primary"><x-icon name="check" /> {{ __('Approve') }}</button></form>
    @endif
    @if ($c->status === 'approved')
      <form method="POST" action="{{ route('parties.rates.version', [$party, $c]) }}" class="flex items-end gap-2">
        @csrf
        <div><label class="label" for="nv_from">{{ __('New version from') }}</label><input id="nv_from" name="valid_from" type="date" class="input" value="{{ now()->addDay()->toDateString() }}" required></div>
        <button class="btn-primary">{{ __('Make new version') }}</button>
      </form>
      @error('valid_from')<span class="error w-full text-end">{{ $message }}</span>@enderror
    @endif
    @if ($c->status !== 'cancelled')
      <form method="POST" action="{{ route('parties.rates.cancel', [$party, $c]) }}" data-confirm="{{ __('Cancel this contract? Trips will stop using it.') }}">@csrf<button class="btn-danger">{{ __('Cancel contract') }}</button></form>
    @endif
  </div>
@endif
@endsection
