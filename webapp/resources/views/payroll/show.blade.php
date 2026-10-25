@extends('layouts.app')
@section('title', $run->label)
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $draft = $run->status === 'draft';
  $canRun = auth()->user()->can('payroll.run');
  $tones = ['draft' => 'bg-ink-100 text-ink-700', 'approved' => 'bg-amber-50 text-amber-800', 'paid' => 'bg-emerald-50 text-emerald-800', 'cancelled' => 'bg-red-50 text-overdue'];
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('payroll.index') }}" class="text-sm text-brand-700 hover:underline">← {{ __('Pay runs') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ $run->label }}</h1>
    <p class="text-sm text-ink-500">{{ __(\App\Modules\Payroll\Models\PayRun::SCOPES[$run->scope] ?? $run->scope) }}@if ($run->notes) · {{ $run->notes }}@endif</p>
  </div>
  <span class="pill {{ $tones[$run->status] ?? '' }}">{{ __(\App\Modules\Payroll\Models\PayRun::STATUSES[$run->status] ?? $run->status) }}</span>
</div>

@foreach ((array) session('skipped') as $line)<p class="rounded-xl bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ $line }}</p>@endforeach
@if ($run->status === 'cancelled')<p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-overdue">{{ __('Cancelled: :reason', ['reason' => $run->cancel_reason]) }}</p>@endif

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  @foreach ([[__('People'), $run->people], [__('Gross pay'), $m($run->gross_total)], [__('Deductions'), $m($run->deductions_total)], [__('Net pay'), $m($run->net_total)]] as [$label, $value])
    <div class="stat"><div class="stat-label">{{ $label }}</div><div class="stat-value money text-2xl">{{ $value }}</div></div>
  @endforeach
</div>

@if ($canRun && in_array($run->status, ['draft', 'approved'], true))
  <div class="card flex flex-wrap items-center gap-3 p-4">
    @if ($draft)
      <form method="POST" action="{{ route('payroll.approve', $run) }}" data-confirm="{{ __('Approve this pay run? The pay is then owed to each person and the payslips no longer change.') }}">@csrf<button class="btn-primary"><x-icon name="check" /> {{ __('Approve') }}</button></form>
      <form method="POST" action="{{ route('payroll.recalculate', $run) }}">@csrf<button class="btn-secondary"><x-icon name="rotate" /> {{ __('Work out again') }}</button></form>
      <span class="text-sm text-ink-500">{{ __('After approval: mark each payslip paid as you pay it.') }}</span>
    @else
      <span class="text-sm text-ink-600">{{ __('Approved :when. Mark each payslip paid as you pay it.', ['when' => $run->approved_at?->diffForHumans()]) }}</span>
    @endif
    <details class="ms-auto">
      <summary class="btn-ghost cursor-pointer text-overdue">{{ __('Cancel the pay run') }}</summary>
      <form method="POST" action="{{ route('payroll.cancel', $run) }}" class="mt-2 flex gap-2">
        @csrf
        <input name="reason" class="input" placeholder="{{ __('Why (kept in the log)') }}" required minlength="3" maxlength="255" aria-label="{{ __('Reason') }}">
        <button class="btn-danger">{{ __('Cancel it') }}</button>
      </form>
    </details>
  </div>
@endif

@if ($withoutPay->isNotEmpty())
  <p class="rounded-xl bg-ink-50 px-4 py-3 text-sm text-ink-700">
    {{ __('Not in this run because no pay is set:') }}
    @foreach ($withoutPay as $p)@can('people.manage')<a href="{{ route('people.edit', $p->id) }}" class="font-medium text-brand-700 hover:underline">{{ $p->name }}</a>@else<span class="font-medium">{{ $p->name }}</span>@endcan @if (! $loop->last), @endif @endforeach
  </p>
@endif
@if ($errors->any())<ul class="card space-y-1 p-4">@foreach ($errors->all() as $e)<li class="error">{{ $e }}</li>@endforeach</ul>@endif

<div class="space-y-3">
  @forelse ($slips as $slip)
    <article class="card p-5" id="slip-{{ $slip->id }}">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold">{{ $slip->person->name }} <span class="text-sm font-normal text-ink-500">{{ __(\App\Modules\People\Models\Person::TYPES[$slip->person->type] ?? $slip->person->type) }}</span></h2>
          <p class="text-sm text-ink-600">{{ $slip->basisLabel($m) }} = <span class="money">{{ $m($slip->base_pay) }}</span></p>
        </div>
        <div class="text-end">
          <div class="money font-display text-2xl font-semibold">{{ $m($slip->net) }}</div>
          <div class="text-xs text-ink-500">
            @if ($slip->status === 'paid'){{ __('Paid :amount on :date (:mode)', ['amount' => $m($slip->paid_amount), 'date' => $slip->paid_at->format('d M Y'), 'mode' => __(\App\Modules\Payroll\Models\Payslip::PAY_MODES[$slip->paid_mode] ?? $slip->paid_mode)]) }}@else{{ __('Net pay') }}@endif
          </div>
        </div>
      </div>

      @if ($slip->lines)
        <ul class="mt-3 divide-y divide-ink-100 rounded-xl border border-ink-100 text-sm">
          @foreach ($slip->lines as $i => $line)
            <li class="flex items-center justify-between gap-3 px-3 py-2">
              <span>{{ $line['label'] }} <span class="text-xs text-ink-500">· {{ __($line['kind'] === 'addition' ? (\App\Modules\Payroll\Models\Payslip::ADDITIONS[$line['type']] ?? '') : (\App\Modules\Payroll\Models\Payslip::DEDUCTIONS[$line['type']] ?? '')) }}</span></span>
              <span class="flex items-center gap-2">
                <span class="money {{ $line['kind'] === 'deduction' ? 'text-overdue' : '' }}">{{ $line['kind'] === 'deduction' ? '−' : '+' }} {{ $m($line['amount']) }}</span>
                @if ($draft && $canRun)
                  <form method="POST" action="{{ route('payroll.lines.destroy', [$run, $slip->id, $i]) }}">@csrf @method('DELETE')<button class="btn-ghost px-1.5 py-1" aria-label="{{ __('Remove this line') }}"><x-icon name="x" class="size-4" /></button></form>
                @endif
              </span>
            </li>
          @endforeach
        </ul>
      @endif

      <div class="mt-3 flex flex-wrap items-center gap-3">
        <a href="{{ route('payroll.slip', [$run, $slip->id]) }}" class="btn-secondary px-3 py-1.5" target="_blank"><x-icon name="printer" class="size-4" /> {{ __('Payslip') }}</a>
        @if ($draft && $canRun)
          <details>
            <summary class="btn-ghost cursor-pointer px-3 py-1.5"><x-icon name="plus" class="size-4" /> {{ __('Add a bonus or a deduction') }}</summary>
            <form method="POST" action="{{ route('payroll.lines.store', [$run, $slip->id]) }}" class="mt-2 flex flex-wrap items-end gap-2">
              @csrf
              <select name="kind" class="input w-36" aria-label="{{ __('Kind') }}"><option value="addition">{{ __('Addition') }}</option><option value="deduction">{{ __('Deduction') }}</option></select>
              <select name="type" class="input w-56" aria-label="{{ __('What') }}">
                <optgroup label="{{ __('Addition') }}">@foreach (\App\Modules\Payroll\Models\Payslip::ADDITIONS as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</optgroup>
                <optgroup label="{{ __('Deduction') }}">@foreach (\App\Modules\Payroll\Models\Payslip::DEDUCTIONS as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</optgroup>
              </select>
              <input name="label" class="input w-48" placeholder="{{ __('Description') }}" maxlength="80" aria-label="{{ __('Description') }}">
              <input name="amount" type="number" step="0.01" min="0.01" class="input w-32" placeholder="{{ __('Amount') }}" required aria-label="{{ __('Amount') }}">
              <button class="btn-primary">{{ __('Add') }}</button>
            </form>
          </details>
          <form method="POST" action="{{ route('payroll.slips.destroy', [$run, $slip->id]) }}" class="ms-auto" data-confirm="{{ __('Leave :name out of this pay run?', ['name' => $slip->person->name]) }}">@csrf @method('DELETE')<button class="btn-ghost px-3 py-1.5 text-sm text-ink-500">{{ __('Leave out') }}</button></form>
        @elseif ($run->status === 'approved' && $slip->status === 'pending' && $canRun && (float) $slip->net > 0)
          <form method="POST" action="{{ route('payroll.pay', [$run, $slip->id]) }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <select name="mode" class="input w-36" aria-label="{{ __('Paid by') }}">@foreach (\App\Modules\Payroll\Models\Payslip::PAY_MODES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select>
            <input name="paid_on" type="date" class="input w-40" value="{{ $today }}" aria-label="{{ __('Paid on') }}">
            <input name="reference" class="input w-40" placeholder="{{ __('UTR / note') }}" maxlength="60" aria-label="{{ __('Reference') }}">
            @if (($owed[$slip->id] ?? 0) > (float) $slip->net + 0.004)
              <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="with_dues" value="1" class="size-4"> {{ __('Pay all owed: :amount (with bata)', ['amount' => $m($owed[$slip->id])]) }}</label>
            @endif
            <button class="btn-primary">{{ __('Mark paid') }}</button>
          </form>
        @endif
      </div>
    </article>
  @empty
    <p class="card p-6 text-ink-500">{{ __('Nobody in this pay run.') }}</p>
  @endforelse
</div>
@endsection
