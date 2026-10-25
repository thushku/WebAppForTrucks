@extends('layouts.app')
@section('title', __('New receipt'))
@section('content')
@php($money = fn ($v) => \App\Support\Money::company($v))
@unless ($party)
  <form method="GET" action="{{ route('receipts.create') }}" class="card flex flex-wrap items-end gap-3 p-6">
    <div><label class="label" for="rc_party">{{ __('Received from') }}</label><select id="rc_party" name="party" class="input w-72">@foreach ($parties as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
    <button class="btn-primary">{{ __('Next') }}</button>
  </form>
@else
<form method="POST" action="{{ route('receipts.store') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]" novalidate>
  @csrf
  <input type="hidden" name="party_id" value="{{ $party->id }}">
  <section class="card grid content-start gap-4 p-6 sm:grid-cols-2">
    <h2 class="text-lg font-semibold sm:col-span-2">{{ __('Money received from :name', ['name' => $party->name]) }}</h2>
    <div><label class="label" for="rc_date">{{ __('Date') }}</label><input id="rc_date" type="date" name="receipt_date" value="{{ old('receipt_date', now()->toDateString()) }}" class="input">@error('receipt_date')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="rc_amount">{{ __('Amount received') }}</label><input id="rc_amount" name="amount" type="number" min="0" step="0.01" value="{{ old('amount', $invoice ? (float) $invoice->balance_due - (float) $invoice->tds_expected : '') }}" class="input">@error('amount')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="rc_tds">{{ __('TDS deducted by the client') }}</label><input id="rc_tds" name="tds_amount" type="number" min="0" step="0.01" value="{{ old('tds_amount', $invoice && (float) $invoice->tds_expected ? (float) $invoice->tds_expected : '') }}" class="input">@error('tds_amount')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="rc_tdss">{{ __('TDS section') }}</label><input id="rc_tdss" name="tds_section" value="{{ old('tds_section', $invoice->withholding['section'] ?? '') }}" class="input" maxlength="10"></div>
    <div><label class="label" for="rc_ded">{{ __('Other deductions (shortage, damage)') }}</label><input id="rc_ded" name="deduction_amount" type="number" min="0" step="0.01" value="{{ old('deduction_amount') }}" class="input"></div>
    <div><label class="label" for="rc_dedn">{{ __('What was deducted') }}</label><input id="rc_dedn" name="deduction_note" value="{{ old('deduction_note') }}" class="input" maxlength="255">@error('deduction_note')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="rc_mode">{{ __('Mode') }}</label><select id="rc_mode" name="mode" class="input">@foreach (\App\Modules\Invoicing\Models\Receipt::MODES as $v => $n)<option value="{{ $v }}" @selected(old('mode', 'neft') === $v)>{{ __($n) }}</option>@endforeach</select></div>
    <div><label class="label" for="rc_ref">{{ __('UTR / cheque / UPI ref.') }}</label><input id="rc_ref" name="reference" value="{{ old('reference') }}" class="input" maxlength="60">@error('reference')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="rc_cdate">{{ __('Cheque date') }}</label><input id="rc_cdate" type="date" name="cheque_date" value="{{ old('cheque_date') }}" class="input">@error('cheque_date')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="rc_bank">{{ __('Into') }}</label><select id="rc_bank" name="bank_account_id" class="input"><option value="">{{ __('Cash / not stated') }}</option>@foreach ($banks as $b)<option value="{{ $b->id }}" @selected($b->is_default)>{{ $b->label }} {{ $b->masked() }}</option>@endforeach</select></div>
    <div class="sm:col-span-2"><label class="label" for="rc_narr">{{ __('Narration') }}</label><input id="rc_narr" name="narration" value="{{ old('narration', $invoice ? __('Settlement for :doc :number', ['doc' => mb_strtolower($invoice->kindLabel()), 'number' => $invoice->number]) : '') }}" class="input" maxlength="255">@error('narration')<span class="error">{{ $message }}</span>@enderror</div>
  </section>

  <section class="card overflow-hidden">
    <div class="p-6 pb-3">
      <h3 class="font-semibold">{{ __('Apply to bills') }}</h3>
      <div class="mt-2 flex flex-wrap gap-4 text-sm">
        <label class="flex items-center gap-2"><input type="radio" name="allocation" value="oldest" @checked(old('allocation', $invoice ? 'manual' : 'oldest') === 'oldest')> {{ __('Oldest first') }}</label>
        <label class="flex items-center gap-2"><input type="radio" name="allocation" value="manual" @checked(old('allocation', $invoice ? 'manual' : 'oldest') === 'manual')> {{ __('As typed below') }}</label>
      </div>
    </div>
    <table class="table">
      <thead><tr><th>{{ __('Bill') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('Apply (incl. TDS)') }}</th></tr></thead>
      <tbody>
        @forelse ($open as $bill)
          <tr>
            <td class="docno font-mono text-sm">{{ $bill->number }}</td><td class="text-sm {{ $bill->isOverdue() ? 'text-overdue' : '' }}">{{ $bill->due_date->format('d M Y') }}</td>
            <td class="money text-end">{{ $money($bill->balance_due) }}</td>
            <td class="text-end"><input name="apply[{{ $bill->id }}]" type="number" min="0" step="0.01" max="{{ (float) $bill->balance_due }}" value="{{ old('apply.' . $bill->id, $invoice?->id === $bill->id ? (float) $bill->balance_due : '') }}" class="input w-32 py-1.5 text-end" aria-label="{{ __('Apply to :n', ['n' => $bill->number]) }}"></td>
          </tr>
        @empty
          <tr><td colspan="4" class="py-6 text-center text-ink-500">{{ __('No open bills: the money is kept as advance.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
    @error('allocations')<p class="error px-6">{{ $message }}</p>@enderror
    <div class="flex justify-end border-t border-ink-100 p-6"><button class="btn-primary"><x-icon name="check" /> {{ __('Record the receipt') }}</button></div>
  </section>
</form>
@endunless
@endsection
