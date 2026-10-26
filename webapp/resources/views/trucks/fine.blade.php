@extends('layouts.app')
@section('title', $fine->challan_no ?: __('Fine'))
@section('content')
@php($m = fn ($v) => \App\Support\Money::company($v))
<a href="{{ route('trucks.fines') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Fines') }}</a>
<div class="mt-1 flex flex-wrap items-center gap-3">
  <h1 class="docno break-all font-mono text-xl font-semibold sm:text-2xl">{{ $fine->challan_no ?: __('Fine without a challan no.') }}</h1>
  @include('trucks.partials.fine-status')
</div>
<p class="text-sm text-ink-500">{{ $fine->offence }}{{ $fine->authority ? ' · ' . $fine->authority : '' }}</p>

<div class="mt-5 grid gap-4 lg:grid-cols-3">
  <section class="card p-6 lg:col-span-2">
    <dl class="grid gap-4 text-sm sm:grid-cols-2">
      <div><dt class="text-ink-500">{{ __('Amount') }}</dt><dd class="money text-lg font-semibold">{{ $m($fine->amount) }}</dd></div>
      <div><dt class="text-ink-500">{{ __('When') }}</dt><dd>{{ $fine->issued_at->setTimezone($tz)->format('d M Y, H:i') }}{{ $fine->place ? ' · ' . $fine->place : '' }}</dd></div>
      <div><dt class="text-ink-500">{{ __('Truck') }}</dt><dd>@if ($fine->truck)<a href="{{ route('trucks.show', $fine->truck) }}" class="font-mono text-brand-700 hover:underline">{{ $fine->truck->reg_no }}</a>@else — @endif
        @if ($fine->trip) · <a href="{{ route('trips.show', $fine->trip) }}" class="text-brand-700 hover:underline">{{ $fine->trip->trip_number }}</a>@endif</dd></div>
      <div><dt class="text-ink-500">{{ __('Driver') }}</dt><dd>{{ $fine->person?->name ?? '—' }}@if ($fine->person_id && $points)<span class="ms-1 {{ $points >= \App\Modules\Fleet\Models\Fine::POINTS_LIMIT ? 'font-semibold text-overdue' : 'text-ink-500' }}">· {{ __(':n of :limit points in 12 months', ['n' => $points, 'limit' => \App\Modules\Fleet\Models\Fine::POINTS_LIMIT]) }}</span>@endif</dd></div>
      <div><dt class="text-ink-500">{{ __('Who bears it') }}</dt><dd>@include('trucks.partials.fine-bears')</dd></div>
      <div><dt class="text-ink-500">{{ __('Black points') }}</dt><dd>{{ $fine->points ?: '—' }}</dd></div>
      @if ($fine->due_on)<div><dt class="text-ink-500">{{ __('Pay by') }}</dt><dd class="{{ $service->isLate($fine) ? 'font-semibold text-overdue' : '' }}">{{ $fine->due_on->format('d M Y') }}</dd></div>@endif
      @if ($fine->status === 'paid')
        <div><dt class="text-ink-500">{{ __('Paid') }}</dt><dd>{{ $fine->paid_on?->format('d M Y') }} · {{ __(\App\Modules\Fleet\Models\Fine::PAID_MODES[$fine->paid_mode] ?? $fine->paid_mode) }}{{ $fine->paid_reference ? ' · ' . $fine->paid_reference : '' }}
          @if ($fine->voucher) · @can('ledger.view')<a href="{{ route('accounts.vouchers.show', $fine->voucher) }}" class="font-mono text-brand-700 hover:underline">{{ $fine->voucher->number }}</a>@else<span class="font-mono">{{ $fine->voucher->number }}</span>@endcan @endif</dd></div>
      @endif
      @if ($fine->status_note)<div class="sm:col-span-2"><dt class="text-ink-500">{{ $fine->status === 'cancelled' ? __('Why cancelled') : __('Why disputed') }}</dt><dd>{{ $fine->status_note }}</dd></div>@endif
      @if ($fine->notes)<div class="sm:col-span-2"><dt class="text-ink-500">{{ __('Notes') }}</dt><dd>{{ $fine->notes }}</dd></div>@endif
    </dl>
  </section>

  <div class="space-y-4">
    @if ($fine->isOpen())
      @can('accounts.post')
        <form method="POST" action="{{ route('trucks.fines.pay', $fine) }}" class="card space-y-3 p-5" novalidate>@csrf
          <h2 class="font-semibold">{{ __('Mark paid') }}</h2>
          <div><label class="label" for="fp_on">{{ __('Paid on') }}</label><input id="fp_on" name="paid_on" type="date" value="{{ old('paid_on', $today->toDateString()) }}" max="{{ $today->toDateString() }}" class="input" required>@error('paid_on')<span class="error">{{ $message }}</span>@enderror</div>
          <div><label class="label" for="fp_mode">{{ __('How') }}</label><select id="fp_mode" name="mode" class="input">@foreach (\App\Modules\Fleet\Models\Fine::PAID_MODES as $k => $l)<option value="{{ $k }}" @selected(old('mode', 'bank') === $k)>{{ __($l) }}</option>@endforeach</select>
            <span class="hint">{{ __('Paid by the crew on the road, it is already in the trip’s expenses: nothing new is booked.') }}</span>@error('mode')<span class="error">{{ $message }}</span>@enderror</div>
          @if ($banks->count() > 1)<div><label class="label" for="fp_bank">{{ __('Bank account') }}</label><select id="fp_bank" name="bank_account_id" class="input">@foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->label }} {{ $b->masked() }}</option>@endforeach</select></div>@endif
          <div><label class="label" for="fp_ref">{{ __('Reference') }}</label><input id="fp_ref" name="reference" value="{{ old('reference') }}" class="input" maxlength="60" placeholder="{{ __('Receipt or UTR no.') }}"></div>
          @error('fine')<span class="error">{{ $message }}</span>@enderror
          <button class="btn-primary w-full justify-center"><x-icon name="check" /> {{ __('Mark paid') }}</button>
        </form>
      @endcan
    @endif
    @can('truck.manage')
      @if ($fine->status !== 'cancelled')
        <section class="card space-y-3 p-5">
          <h2 class="font-semibold">{{ __('Dispute or cancel') }}</h2>
          @if ($fine->status === 'disputed')
            <form method="POST" action="{{ route('trucks.fines.status', $fine) }}">@csrf<input type="hidden" name="action" value="reopen"><button class="btn-secondary w-full justify-center">{{ __('Dispute lost: back to unpaid') }}</button></form>
          @endif
          <form method="POST" action="{{ route('trucks.fines.status', $fine) }}" class="space-y-2" novalidate>@csrf
            <label class="label" for="fs_note">{{ __('Why') }}</label><input id="fs_note" name="note" class="input" maxlength="255" placeholder="{{ __('Not our truck at that time, cancelled by the court…') }}">
            @error('note')<span class="error">{{ $message }}</span>@enderror @error('reason')<span class="error">{{ $message }}</span>@enderror @error('fine')<span class="error">{{ $message }}</span>@enderror
            <div class="flex flex-wrap gap-2">
              @if ($fine->status === 'unpaid' && ! $fine->payslip_id)<button name="action" value="dispute" class="btn-secondary">{{ __('Disputed') }}</button>@endif
              <button name="action" value="cancel" class="btn-ghost text-overdue" onclick="return confirm(@js($fine->status === 'paid' ? __('Cancel this fine? Its payment is reversed in the books (the money came back).') : __('Cancel this fine?')))">{{ __('Cancel the fine') }}</button>
            </div>
          </form>
        </section>
      @endif
    @endcan
  </div>
</div>

@can('truck.manage')
  @if ($fine->isOpen() && ! $fine->payslip_id)
    <form method="POST" action="{{ route('trucks.fines.update', $fine) }}" class="card mt-6 grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4" novalidate>@csrf @method('PUT')
      <h2 class="text-lg font-semibold sm:col-span-2 xl:col-span-4">{{ __('Correct the fine') }}</h2>
      @include('trucks.partials.fine-form', ['now' => $today->setTimezone($tz)->endOfDay()])
      <div class="text-end sm:col-span-2 xl:col-span-4"><button class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button></div>
    </form>
  @endif
@endcan
@endsection
