@extends('layouts.app')
@section('title', __('New bill'))
@section('content')
<section class="card p-6">
  <h2 class="text-lg font-semibold">{{ __('Bill a client') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('Choose the client, tick their LRs (one LR, or several for one consolidated bill with an LR annexure), and make the draft.') }}</p>
  <form method="GET" action="{{ route('invoices.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
    <div>
      <label class="label" for="n_party">{{ __('Client') }}</label>
      <select id="n_party" name="party" class="input w-72">
        <option value="">{{ __('Choose…') }}</option>
        @foreach ($parties as $p)<option value="{{ $p->id }}" @selected($party?->id === $p->id)>{{ $p->name }}{{ isset($counts[$p->id]) ? ' · ' . trans_choice(':count LR waiting|:count LRs waiting', $counts[$p->id]) : '' }}</option>@endforeach
      </select>
    </div>
    <button class="btn-secondary">{{ __('Show their LRs') }}</button>
  </form>
</section>

@if ($party)
  <form method="POST" action="{{ route('invoices.store') }}" class="card overflow-hidden" novalidate>
    @csrf
    <input type="hidden" name="party_id" value="{{ $party->id }}">
    <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
      <h3 class="font-semibold">{{ __('LRs of :name waiting to be billed', ['name' => $party->name]) }}</h3>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" data-check-all="loads" class="size-4 rounded border-ink-300"> {{ __('Tick all') }}</label>
    </div>
    <div class="overflow-x-auto">
      <table class="table">
        <thead><tr><th class="w-8"></th><th>{{ __('LR') }}</th><th>{{ __('Date') }}</th><th>{{ __('Trip / vehicle') }}</th><th>{{ __('From → to') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Freight and charges') }}</th></tr></thead>
        <tbody>
          @forelse ($loads as $c)
            <tr>
              <td><input type="checkbox" name="loads[]" value="{{ $c->id }}" data-check-item="loads" class="size-4 rounded border-ink-300" @checked(in_array($c->id, $ticked, true)) aria-label="{{ $c->lr_number }}"></td>
              <td class="docno font-mono text-sm font-semibold">{{ $c->lr_number }}</td>
              <td class="text-sm">{{ $c->lr_date?->format('d M Y') }}</td>
              <td class="text-sm"><span class="docno font-mono text-xs">{{ $c->trip?->trip_number }}</span><div class="text-xs text-ink-500">{{ $c->trip?->vehicleLabel() }}</div></td>
              <td class="text-sm">{{ $c->pickupStop?->place?->name }} → {{ $c->dropStop?->place?->name }}</td>
              <td><span class="pill bg-ink-100 text-ink-700">{{ __(\App\Modules\Trips\Models\Consignment::STATUSES[$c->status]) }}</span>@if ($c->pod_status !== 'none')<span class="pill ms-1 bg-emerald-50 text-paid">POD</span>@endif</td>
              <td class="money text-end">{{ \App\Support\Money::company($c->totalCharges()) }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="py-8 text-center text-ink-500">{{ __('No LRs waiting for :name.', ['name' => $party->name]) }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @error('loads')<p class="error px-6">{{ $message }}</p>@enderror
    <div class="flex flex-wrap justify-end gap-2 border-t border-ink-100 p-6">
      <button name="blank" value="1" class="btn-ghost" formnovalidate>{{ __('Blank bill instead') }}</button>
      <button class="btn-primary"><x-icon name="check" /> {{ __('Make the draft') }}</button>
    </div>
  </form>
@endif
@endsection
