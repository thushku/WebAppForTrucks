@extends('layouts.app')
@section('title', __('Opening balances'))
@section('content')
@include('accounts._nav')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $amount = fn ($v) => abs((float) $v) >= 0.0005 ? rtrim(rtrim(number_format(abs((float) $v), 2, '.', ''), '0'), '.') : '';
@endphp
<section class="card space-y-3 p-6">
  <h2 class="text-lg font-semibold">{{ __('Opening balances') }}</h2>
  <p class="text-sm text-ink-600">{{ __('What each ledger held on the first day of this financial year, when you start keeping the books here. Debit: they owe you, or you hold it (clients, cash, bank, advances). Credit: you owe them (suppliers, loans, capital). What does not balance is kept in “Difference in opening balances” until it is explained.') }}</p>
  <p class="text-sm font-medium {{ abs($difference) >= 0.005 ? 'text-overdue' : 'text-emerald-700' }}">
    {{ abs($difference) >= 0.005 ? __('Not balanced by :amount (:side).', ['amount' => $m(abs($difference)), 'side' => $difference > 0 ? __('more debit') : __('more credit')]) : __('The opening balances balance.') }}
  </p>
  <form method="POST" action="{{ route('accounts.opening.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 border-t border-ink-100 pt-4">
    @csrf
    <div><label class="label" for="ob_file">{{ __('Bring them from Tally or Excel (CSV)') }}</label><input id="ob_file" name="file" type="file" accept=".csv,text/csv" class="input" required>@error('file')<span class="error">{{ $message }}</span>@enderror</div>
    <button class="btn-secondary"><x-icon name="upload" class="size-4" /> {{ __('Import') }}</button>
    <span class="hint w-full">{{ __('Columns: Name, Debit, Credit (or Name, Amount, Type Dr/Cr). Names are matched to ledgers and parties; a party without a ledger gets one.') }}</span>
  </form>
  @if (session('unknown'))<p class="rounded-xl bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ __('Not found, left out:') }} {{ implode(', ', session('unknown')) }}</p>@endif
  @error('opening')<p class="error">{{ $message }}</p>@enderror
</section>

<form method="POST" action="{{ route('accounts.opening.update') }}" class="space-y-4">
  @csrf @method('PUT')
  @foreach ($ledgers as $group => $rows)
    <section class="card overflow-x-auto">
      <h3 class="border-b border-ink-100 px-5 py-3 font-semibold">{{ $group }}</h3>
      <table class="table">
        <thead><tr><th>{{ __('Ledger') }}</th><th class="w-40 text-end">{{ __('Debit') }}</th><th class="w-40 text-end">{{ __('Credit') }}</th></tr></thead>
        <tbody>
          @foreach ($rows as $l)
            <tr>
              <td class="text-sm">{{ $l->name }}</td>
              <td><input name="dr[{{ $l->id }}]" type="number" step="0.01" min="0" class="input py-1.5 text-end" value="{{ old('dr.' . $l->id, (float) $l->opening_balance > 0 ? $amount($l->opening_balance) : '') }}" aria-label="{{ __('Debit') }} {{ $l->name }}"></td>
              <td><input name="cr[{{ $l->id }}]" type="number" step="0.01" min="0" class="input py-1.5 text-end" value="{{ old('cr.' . $l->id, (float) $l->opening_balance < 0 ? $amount($l->opening_balance) : '') }}" aria-label="{{ __('Credit') }} {{ $l->name }}"></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
  @endforeach
  <button class="btn-primary">{{ __('Save opening balances') }}</button>
</form>
@endsection
