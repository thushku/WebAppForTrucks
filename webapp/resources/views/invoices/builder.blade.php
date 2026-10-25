@extends('layouts.app')
@section('title', __('Draft :doc', ['doc' => mb_strtolower($invoice->kindLabel())]))
@section('content')
@php
  $inv = $invoice;
  $trace = $inv->tax_trace ?? [];
  $money = fn ($v) => \App\Support\Money::company($v);
@endphp
<form method="POST" action="{{ route('invoices.update', $inv) }}" class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_24rem]" novalidate>
  @csrf @method('PUT')
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">{{ __('Draft :doc', ['doc' => mb_strtolower($inv->kindLabel())]) }} · {{ $inv->party->name }}</h2>
          @if ($inv->related)<p class="text-sm text-ink-500">{{ __('Against :number of :date', ['number' => $inv->related->number, 'date' => $inv->related->issue_date->format('d M Y')]) }}</p>@endif
        </div>
        <a href="{{ route('invoices.print', $inv) }}" target="_blank" rel="noopener" class="btn-ghost text-sm">{{ __('Preview the print') }}</a>
      </div>

      <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div><label class="label" for="b_issue">{{ __('Date') }}</label><input id="b_issue" type="date" name="issue_date" value="{{ old('issue_date', $inv->issue_date->toDateString()) }}" class="input"></div>
        <div><label class="label" for="b_due">{{ __('Due') }}</label><input id="b_due" type="date" name="due_date" value="{{ old('due_date', $inv->due_date->toDateString()) }}" class="input" @disabled($inv->isCreditNote())></div>
        <div><label class="label" for="b_ref">{{ __('Client ref. (PO / indent)') }}</label><input id="b_ref" name="reference" value="{{ old('reference', $inv->reference) }}" class="input" maxlength="60"></div>
        <div><label class="label" for="b_reg">{{ __('Issued under') }}</label>
          <select id="b_reg" name="tax_registration_id" class="input"><option value="">{{ __('Not registered (no tax)') }}</option>@foreach ($registrations as $r)<option value="{{ $r->id }}" @selected($inv->tax_registration_id === $r->id)>{{ $r->tax_id }}</option>@endforeach</select></div>
        <div><label class="label" for="b_btax">{{ __('Client registration billed') }}</label>
          <select id="b_btax" name="buyer_tax_id" class="input"><option value="">{{ __('None (unregistered)') }}</option>@foreach ($inv->party->taxIds->where('active', true) as $t)<option value="{{ $t->tax_id }}" @selected($inv->buyer_tax_id === $t->tax_id)>{{ $t->tax_id }}{{ $t->state_code ? ' · ' . ($regions[$t->state_code] ?? $t->state_code) : '' }}</option>@endforeach</select></div>
        @if ($india)
          <div><label class="label" for="b_load">{{ __('Loading state') }}</label>
            <select id="b_load" name="loading_state" class="input"><option value="">—</option>@foreach ($regions as $code => $name)<option value="{{ $code }}" @selected($inv->loading_state === $code)>{{ $code }} · {{ $name }}</option>@endforeach</select></div>
        @else
          <div><label class="label" for="b_type">{{ __('Transport') }}</label>
            <select id="b_type" name="transport_type" class="input"><option value="">—</option>@foreach (['domestic' => __('Domestic'), 'export' => __('Export'), 'import' => __('Import'), 'international' => __('International')] as $v => $n)<option value="{{ $v }}" @selected($inv->transport_type === $v)>{{ $n }}</option>@endforeach</select></div>
          <div><label class="label" for="b_role">{{ __('We are') }}</label>
            <select id="b_role" name="carrier_role" class="input"><option value="main" @selected($inv->carrier_role !== 'sub')>{{ __('The main carrier') }}</option><option value="sub" @selected($inv->carrier_role === 'sub')>{{ __('A subcontractor') }}</option></select></div>
        @endif
        <div><label class="label" for="b_dtype">{{ __('Discount') }}</label>
          <div class="flex gap-2"><select id="b_dtype" name="discount_type" class="input w-28">@foreach (['none' => __('None'), 'percent' => '%', 'amount' => __('Amount')] as $v => $n)<option value="{{ $v }}" @selected($inv->discount_type === $v)>{{ $n }}</option>@endforeach</select>
          <input name="discount_value" type="number" min="0" step="0.01" value="{{ (float) $inv->discount_value ?: '' }}" class="input" aria-label="{{ __('Discount') }}"></div></div>
        <div><label class="label" for="b_adv">{{ __('Less advance received') }}</label><input id="b_adv" name="advance_less" type="number" min="0" step="0.01" value="{{ (float) $inv->advance_less ?: '' }}" class="input"></div>
      </div>
      @unless ($india)
        <fieldset class="mt-4"><legend class="label">{{ __('Papers on file (zero-rated international transport)') }}</legend>
          <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm">@foreach (\App\Modules\Tax\Models\TaxCode::EVIDENCE as $e => $name)<label class="flex items-center gap-1.5"><input type="checkbox" name="evidence[]" value="{{ $e }}" class="size-4 rounded border-ink-300" @checked(in_array($e, $inv->evidence ?? [], true))> {{ __($name) }}</label>@endforeach</div>
        </fieldset>
      @endunless
      <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="inclusive" value="1" class="size-4 rounded border-ink-300" @checked($inv->inclusive)> {{ __('Rates include tax') }}</label>
    </section>

    <section class="card overflow-hidden">
      <div class="p-6 pb-3"><h3 class="font-semibold">{{ __('Lines') }}</h3></div>
      <div class="overflow-x-auto">
        <table class="table" data-lines>
          <thead><tr><th>{{ __('Charge') }}</th><th>{{ __('Description') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Tax') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
          <tbody id="bill-lines">
            @foreach ($inv->lines as $n => $line)@include('invoices._line', ['i' => $n, 'line' => $line])@endforeach
          </tbody>
        </table>
      </div>
      <template id="bill-line-template">@include('invoices._line', ['i' => '__N__', 'line' => null])</template>
      <div class="flex flex-wrap items-center gap-3 border-t border-ink-100 p-4">
        <button type="button" class="btn-ghost text-sm" data-add-row="#bill-lines" data-template="#bill-line-template"><x-icon name="plus" /> {{ __('Add a line') }}</button>
        @if ($addable->isNotEmpty())
          <details class="text-sm">
            <summary class="cursor-pointer font-medium text-brand-700">{{ trans_choice('Add :count more LR of this client|Add from :count more LRs of this client', $addable->count()) }}</summary>
            <div class="mt-2 grid gap-1 sm:grid-cols-2">@foreach ($addable as $c)<label class="flex items-center gap-2"><input type="checkbox" name="add_loads[]" value="{{ $c->id }}" class="size-4 rounded border-ink-300"> <span class="font-mono text-xs">{{ $c->lr_number }}</span> <span class="text-xs text-ink-500">{{ $c->lr_date?->format('d M') }} · {{ $money($c->freight) }}</span></label>@endforeach</div>
          </details>
        @endif
      </div>
      @foreach (['lines', 'loads'] as $f)@error($f)<p class="error px-6 pb-4">{{ $message }}</p>@enderror @endforeach
    </section>

    <section class="card grid gap-4 p-6 sm:grid-cols-2">
      @if ($inv->related_invoice_id)
        <div class="sm:col-span-2"><label class="label" for="b_reason">{{ __('Reason (printed)') }}</label><input id="b_reason" name="reason" value="{{ old('reason', $inv->reason) }}" class="input" maxlength="500" placeholder="{{ $inv->isCreditNote() ? __('e.g. shortage of 4 bags, rate corrected') : __('e.g. detention 2 days at Mumbai') }}">@error('reason')<span class="error">{{ $message }}</span>@enderror</div>
      @endif
      <div><label class="label" for="b_notes">{{ __('Note on the bill') }}</label><textarea id="b_notes" name="notes" rows="3" class="input" maxlength="2000">{{ old('notes', $inv->notes) }}</textarea></div>
      <div><label class="label" for="b_terms">{{ __('Terms') }}</label><textarea id="b_terms" name="terms" rows="3" class="input" maxlength="2000" placeholder="{{ __('Payment within :n days. Interest at 18% a year after the due date. Subject to Kochi jurisdiction.', ['n' => $inv->party->credit_days ?? 30]) }}">{{ old('terms', $inv->terms) }}</textarea></div>
    </section>
  </div>

  {{-- Tax and totals, as last saved --}}
  <aside class="space-y-6">
    <section class="card p-6 2xl:sticky 2xl:top-20">
      <h3 class="font-semibold">{{ __('Tax and total') }}</h3>
      @if (! empty($trace['error']))
        <p class="mt-3 flex gap-2 rounded-xl bg-red-50 px-3 py-2 text-sm text-overdue"><x-icon name="alert" class="size-4 shrink-0" /> {{ $trace['error'] }}</p>
      @endif
      @error('tax')<p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-overdue">{{ $message }}</p>@enderror
      <dl class="mt-3 space-y-1.5 text-sm">
        <div class="flex justify-between"><dt class="text-ink-500">{{ __('Taxable value') }}</dt><dd class="money">{{ $money($inv->taxable_total) }}</dd></div>
        @foreach ($inv->taxes as $t)
          <div class="flex justify-between {{ $t->charged ? '' : 'text-ink-500' }}"><dt>{{ $t->label() }}{{ $t->charged ? '' : ' · ' . __('reverse charge') }}</dt><dd class="money">{{ $money($t->amount) }}</dd></div>
        @endforeach
        @if ((float) $inv->round_off)<div class="flex justify-between text-ink-500"><dt>{{ __('Round off') }}</dt><dd class="money">{{ $money($inv->round_off) }}</dd></div>@endif
        <div class="flex justify-between border-t border-ink-100 pt-1.5 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd class="money">{{ $money($inv->total) }}</dd></div>
        @if ((float) $inv->advance_less)<div class="flex justify-between"><dt class="text-ink-500">{{ __('Less advance') }}</dt><dd class="money">− {{ $money($inv->advance_less) }}</dd></div>@endif
        @if ((float) $inv->tds_expected)<div class="flex justify-between text-ink-500"><dt>{{ __('TDS the client deducts') }}</dt><dd class="money">{{ $money($inv->tds_expected) }}</dd></div>@endif
      </dl>
      @if ($inv->place_of_supply)<p class="mt-3 text-xs text-ink-500">{{ __('Place of supply: :p', ['p' => $inv->place_of_supply_name]) }}{{ $inv->reverse_charge ? ' · ' . __('reverse charge') : '' }}</p>@endif
      @if (! empty($trace['steps']))
        <details class="mt-3 text-xs text-ink-700"><summary class="cursor-pointer font-medium">{{ __('Why this tax') }}</summary><ol class="mt-2 list-decimal space-y-1 ps-4">@foreach ($trace['steps'] as $step)<li>{{ $step }}</li>@endforeach</ol></details>
      @endif
      @foreach ($trace['warnings'] ?? [] as $w)<p class="mt-2 text-xs text-overdue">{{ $w }}</p>@endforeach
      <div class="mt-5 grid gap-2">
        <button class="btn-secondary"><x-icon name="check" /> {{ __('Save and work out the tax') }}</button>
        @can('invoice.finalise')<button name="then" value="finalise" class="btn-primary" data-confirm-click="{{ __('Issue it? It gets its number and can then no longer be changed.') }}"><x-icon name="send" /> {{ __('Save and issue') }}</button>@endcan
      </div>
    </section>
  </aside>
</form>
<form method="POST" action="{{ route('invoices.void', $inv) }}" class="mt-2 text-end" data-confirm="{{ __('Delete this draft?') }}">@csrf<button class="btn-ghost text-sm text-overdue">{{ __('Delete draft') }}</button></form>
@endsection
