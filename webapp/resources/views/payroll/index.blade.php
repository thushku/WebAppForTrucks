@extends('layouts.app')
@section('title', __('Payroll'))
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $tones = ['draft' => 'bg-ink-100 text-ink-700', 'approved' => 'bg-amber-50 text-amber-800', 'paid' => 'bg-emerald-50 text-emerald-800', 'cancelled' => 'bg-red-50 text-overdue'];
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-semibold">{{ __('Pay runs') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Salary for monthly staff, pay per trip or per km for the crew — for any period. Bata from settled trips is already in each person’s ledger.') }}</p>
  </div>
</div>

@can('payroll.run')
  <form method="POST" action="{{ route('payroll.store') }}" class="card flex flex-wrap items-end gap-4 p-5">
    @csrf
    <div><label class="label" for="period_from">{{ __('From') }}</label><input id="period_from" name="period_from" type="date" class="input" value="{{ old('period_from', $suggest['from']) }}" required></div>
    <div><label class="label" for="period_to">{{ __('To') }}</label><input id="period_to" name="period_to" type="date" class="input" value="{{ old('period_to', $suggest['to']) }}" required>@error('period_to')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="scope">{{ __('Who') }}</label>
      <select id="scope" name="scope" class="input">@foreach (\App\Modules\Payroll\Models\PayRun::SCOPES as $key => $label)<option value="{{ $key }}" @selected(old('scope') === $key)>{{ __($label) }}</option>@endforeach</select></div>
    <div class="min-w-48 flex-1"><label class="label" for="notes">{{ __('Note') }} <span class="text-ink-400">({{ __('optional') }})</span></label><input id="notes" name="notes" class="input" value="{{ old('notes') }}" maxlength="255"></div>
    <button class="btn-primary"><x-icon name="plus" /> {{ __('Make the pay run') }}</button>
  </form>
@endcan

<section class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Who') }}</th><th class="text-end">{{ __('People') }}</th><th class="text-end">{{ __('Net pay') }}</th><th class="text-end">{{ __('Paid') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
      @forelse ($runs as $run)
        <tr>
          <td class="font-medium"><a href="{{ route('payroll.show', $run) }}" class="text-brand-700 hover:underline">{{ $run->periodLabel() }}</a>@if ($run->notes)<div class="text-xs text-ink-500">{{ $run->notes }}</div>@endif</td>
          <td>{{ __(\App\Modules\Payroll\Models\PayRun::SCOPES[$run->scope] ?? $run->scope) }}</td>
          <td class="text-end">{{ $run->people }}</td>
          <td class="money text-end">{{ $m($run->net_total) }}</td>
          <td class="money text-end">{{ $m($run->paid_total) }}</td>
          <td><span class="pill {{ $tones[$run->status] ?? '' }}">{{ __(\App\Modules\Payroll\Models\PayRun::STATUSES[$run->status] ?? $run->status) }}</span></td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-ink-500">{{ __('No pay run yet. Set each person’s pay basis and rate on their page, then make the first run above.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
{{ $runs->links() }}
@endsection
