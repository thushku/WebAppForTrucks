@extends('layouts.app')
@section('title', __('EMIs'))
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $mine = (int) old('form_truck');
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('trucks.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Trucks') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('Truck loans and EMIs') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Each EMI paid takes its principal off the truck’s loan and books the interest as the truck’s cost.') }}</p>
  </div>
</div>
@if ($without)
  <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ trans_choice(':count truck has a financier but no EMI or EMI day: edit it to follow its EMIs here.|:count trucks have a financier but no EMI or EMI day: edit them to follow their EMIs here.', $without) }}</p>
@endif

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Truck') }}</th><th>{{ __('Financier') }}</th><th class="text-end">{{ __('EMI') }}</th><th>{{ __('Next') }}</th><th class="text-end">{{ __('Still owed') }}</th><th>{{ __('Loan ends') }}</th></tr></thead>
      <tbody>
        @forelse ($rows as $r)
          @php($t = $r['truck'])
          <tr>
            <td><a href="{{ route('trucks.show', $t) }}" class="docno font-mono font-semibold text-brand-700 hover:underline">{{ $t->reg_no }}</a></td>
            <td class="text-sm">{{ $t->financier ?: '—' }}@if ($t->loan_rate)<div class="text-xs text-ink-500">{{ __(':r% a year', ['r' => rtrim(rtrim((string) $t->loan_rate, '0'), '.')]) }}</div>@endif</td>
            <td class="money text-end text-sm">{{ $m($t->emi_amount) }}<div class="text-xs text-ink-500">{{ __('on day :d', ['d' => $t->emi_day]) }}</div></td>
            <td class="text-sm">
              @if ($r['overdue'])
                <span class="pill bg-red-50 text-overdue">{{ trans_choice(':count overdue, since :date|:count overdue, since :date', count($r['overdue']), ['date' => $r['overdue'][0]->format('d M')]) }}</span>
              @elseif ($r['next'])
                <span class="pill {{ $r['next']->diffInDays($today) <= 7 ? 'bg-amber-50 text-partial' : 'bg-ink-100 text-ink-700' }}">{{ __('Due :date', ['date' => $r['next']->format('d M Y')]) }}</span>
              @else
                <span class="pill bg-emerald-50 text-paid">{{ __('Nothing due') }}</span>
              @endif
              @if ($r['last'])<div class="mt-1 text-xs text-ink-500">{{ __('Last paid: :month', ['month' => $r['last']->due_on->translatedFormat('M Y')]) }}</div>@endif
            </td>
            <td class="money text-end text-sm">{{ $r['outstanding'] !== null ? $m($r['outstanding']) : '—' }}</td>
            <td class="whitespace-nowrap text-sm">{{ $t->loan_ends_on?->format('M Y') ?? '—' }}</td>
          </tr>
          @can('accounts.post')
            @if ($r['open'])
              <tr class="bg-ink-50/60">
                <td colspan="6">
                  <form method="POST" action="{{ route('trucks.emis.pay', $t) }}" class="flex flex-wrap items-end gap-2" novalidate>
                    @csrf
                    <input type="hidden" name="form_truck" value="{{ $t->id }}">
                    <div><label class="label" for="emi_due_{{ $t->id }}">{{ __('EMI of') }}</label>
                      <select id="emi_due_{{ $t->id }}" name="due_on" class="input py-2">@foreach ($r['open'] as $d)<option value="{{ $d->toDateString() }}" @selected($mine === $t->id && old('due_on') === $d->toDateString())>{{ $d->translatedFormat('F Y') }}{{ $d->lessThan($today) ? ' · ' . __('overdue') : '' }}</option>@endforeach</select></div>
                    <div><label class="label" for="emi_paid_{{ $t->id }}">{{ __('Paid on') }}</label><input id="emi_paid_{{ $t->id }}" name="paid_on" type="date" class="input py-2" value="{{ $mine === $t->id ? old('paid_on') : $today->toDateString() }}" max="{{ $today->toDateString() }}"></div>
                    <div><label class="label" for="emi_amount_{{ $t->id }}">{{ __('Amount') }}</label><input id="emi_amount_{{ $t->id }}" name="amount" type="number" step="0.01" class="input w-32 py-2" value="{{ $mine === $t->id ? old('amount') : (float) $t->emi_amount }}"></div>
                    <div><label class="label" for="emi_int_{{ $t->id }}">{{ __('Of it, interest') }}</label><input id="emi_int_{{ $t->id }}" name="interest" type="number" step="0.01" class="input w-32 py-2" value="{{ $mine === $t->id ? old('interest') : $r['interest'] }}" placeholder="0"></div>
                    <div><label class="label" for="emi_mode_{{ $t->id }}">{{ __('From') }}</label>
                      <select id="emi_mode_{{ $t->id }}" name="mode" class="input py-2"><option value="bank">{{ __('Bank') }}</option><option value="cash" @selected($mine === $t->id && old('mode') === 'cash')>{{ __('Cash') }}</option></select></div>
                    @if ($banks->count() > 1)
                      <div><label class="label" for="emi_bank_{{ $t->id }}">{{ __('Bank account') }}</label><select id="emi_bank_{{ $t->id }}" name="bank_account_id" class="input py-2">@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->label }} {{ $b->masked() }}</option>@endforeach</select></div>
                    @endif
                    <div><label class="label" for="emi_ref_{{ $t->id }}">{{ __('UTR / reference') }}</label><input id="emi_ref_{{ $t->id }}" name="reference" class="input w-36 py-2" maxlength="60" value="{{ $mine === $t->id ? old('reference') : '' }}"></div>
                    <button class="btn-secondary py-2"><x-icon name="check" /> {{ __('EMI paid') }}</button>
                    @if ($mine === $t->id)
                      @foreach (['due_on', 'paid_on', 'amount', 'interest', 'mode', 'reference', 'truck'] as $f)@error($f)<span class="error w-full">{{ $message }}</span>@enderror @endforeach
                    @endif
                  </form>
                </td>
              </tr>
            @endif
          @endcan
        @empty
          <tr><td colspan="6" class="py-12 text-center text-ink-500">{{ __('No truck has an EMI yet. Edit a truck: financier, EMI, its day of the month and the loan’s end.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@if ($history->isNotEmpty())
  <section class="card overflow-x-auto">
    <h2 class="border-b border-ink-100 px-5 py-4 text-lg font-semibold">{{ __('EMIs paid') }}</h2>
    <table class="table">
      <thead><tr><th>{{ __('Truck') }}</th><th>{{ __('EMI of') }}</th><th>{{ __('Paid on') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Principal') }}</th><th class="text-end">{{ __('Interest') }}</th><th>{{ __('Voucher') }}</th></tr></thead>
      <tbody>
        @foreach ($history as $e)
          <tr>
            <td class="docno font-mono text-sm">{{ $e->truck?->reg_no }}</td>
            <td class="text-sm">{{ $e->due_on->translatedFormat('M Y') }}</td>
            <td class="whitespace-nowrap text-sm">{{ $e->paid_on->format('d M Y') }}</td>
            <td class="money text-end text-sm">{{ $m($e->amount) }}</td>
            <td class="money text-end text-sm">{{ $m($e->principal) }}</td>
            <td class="money text-end text-sm">{{ $m($e->interest) }}</td>
            <td>@if ($e->voucher)@can('accounts.view')<a href="{{ route('accounts.vouchers.show', $e->voucher) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $e->voucher->number }}</a>@else<span class="font-mono text-xs">{{ $e->voucher->number }}</span>@endcan @endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
@endif
@endsection
