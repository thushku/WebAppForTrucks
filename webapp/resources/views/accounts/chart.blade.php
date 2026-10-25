@extends('layouts.app')
@section('title', __('Chart of accounts'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company(abs($v)) . ' ' . ($v > 0 ? __('Dr') : __('Cr')))
@include('accounts._nav')

<section class="card overflow-hidden">
  <h2 class="px-6 pt-6 text-lg font-semibold">{{ __('Chart of accounts') }}</h2>
  <p class="px-6 text-sm text-ink-500">{{ __('Ready-made for your country. Each client, supplier, vendor, crew member, bank account, expense category and tax gets its ledger by itself the first time it is used.') }}</p>
  <table class="table mt-3">
    <thead><tr><th>{{ __('Group · ledger') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Balance today') }}</th></tr></thead>
    @foreach ($tree as [$group, $depth])
      <tbody>
        <tr class="bg-ink-50/60"><td class="text-xs font-semibold uppercase tracking-wide text-ink-500" style="padding-inline-start: {{ 1 + $depth * 1.5 }}rem" colspan="2">{{ $group->name }} <span class="font-normal normal-case">· {{ __(\App\Modules\Accounts\Models\AccountGroup::NATURES[$group->nature]) }}</span></td>
          <td class="money text-end text-xs font-semibold">@php($sum = collect($ledgers[$group->id] ?? [])->sum(fn ($l) => $balances[$l->id]->balance ?? 0)){{ abs($sum) >= 0.005 ? $m($sum) : '' }}</td></tr>
        @foreach ($ledgers[$group->id] ?? [] as $l)
          <tr>
            <td class="text-sm" style="padding-inline-start: {{ 2.5 + $depth * 1.5 }}rem"><a href="{{ route('ledgers.show', $l) }}" class="hover:text-brand-700">{{ $l->name }}</a>@if ($l->code) <span class="font-mono text-xs text-ink-500">{{ $l->code }}</span>@endif</td>
            <td class="text-xs text-ink-500">{{ __(\App\Modules\Accounts\Models\LedgerAccount::KINDS[$l->kind] ?? $l->kind) }}</td>
            <td class="money text-end text-sm">{{ isset($balances[$l->id]) && abs($balances[$l->id]->balance) >= 0.005 ? $m($balances[$l->id]->balance) : '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    @endforeach
  </table>
</section>

@can('accounts.post')
<form method="POST" action="{{ route('accounts.chart.store') }}" class="card grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-5" novalidate>@csrf
  <h3 class="text-lg font-semibold sm:col-span-2 xl:col-span-5">{{ __('Add a ledger') }}</h3>
  <div class="xl:col-span-2"><label class="label" for="cl_name">{{ __('Name') }}</label><input id="cl_name" name="name" value="{{ old('name') }}" class="input" maxlength="150" placeholder="{{ __('e.g. Capital — Rajesh Sharma, Truck loan HDFC') }}">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="cl_group">{{ __('Group') }}</label><select id="cl_group" name="account_group_id" class="input">@foreach ($groups as $g)<option value="{{ $g->id }}" @selected(old('account_group_id') == $g->id)>{{ $g->name }}</option>@endforeach</select></div>
  <div><label class="label" for="cl_open">{{ __('Opening balance') }}</label><input id="cl_open" name="opening_balance" type="number" step="0.01" min="0" value="{{ old('opening_balance') }}" class="input">@error('opening_balance')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="cl_side">{{ __('Side') }}</label><select id="cl_side" name="opening_side" class="input"><option value="dr">{{ __('Debit') }}</option><option value="cr" @selected(old('opening_side') === 'cr')>{{ __('Credit') }}</option></select></div>
  <div class="text-end sm:col-span-2 xl:col-span-5"><button class="btn-primary"><x-icon name="plus" /> {{ __('Add') }}</button></div>
</form>
@endcan
@endsection
