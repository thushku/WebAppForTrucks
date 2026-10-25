@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
@php
  $inv = $invoice;
  $money = fn ($v) => \App\Support\Money::company($v);
@endphp
<section class="card p-6">
  <div class="flex flex-wrap items-start gap-4">
    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-700"><x-icon name="receipt" class="size-6" /></span>
    <div class="min-w-0 flex-1">
      <h2 class="docno font-mono text-2xl font-semibold">{{ $inv->number }}</h2>
      <p class="mt-0.5 text-sm text-ink-500">{{ $inv->kindLabel() }} · {{ $inv->party->name }} · {{ $inv->issue_date->format('d M Y') }}@unless ($inv->isCreditNote()) · {{ __('due :d', ['d' => $inv->due_date->format('d M Y')]) }}@endunless</p>
      <div class="mt-2 flex flex-wrap items-center gap-1.5">
        @include('invoices._status')
        @if ($inv->reverse_charge)<span class="pill bg-amber-50 text-partial">{{ __('Reverse charge') }}</span>@endif
        @if ($inv->pending_evidence)<span class="pill bg-red-50 text-overdue">{{ __('Pending evidence') }}</span>@endif
        @if ($inv->related)<a href="{{ route('invoices.show', $inv->related) }}" class="pill bg-ink-100 text-ink-700 hover:underline">{{ __('Against :n', ['n' => $inv->related->number]) }}</a>@endif
      </div>
    </div>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
      <dt class="text-ink-500">{{ __('Total') }}</dt><dd class="money text-end font-semibold">{{ $money($inv->total) }}</dd>
      @unless ($inv->isCreditNote())
        @if ((float) $inv->advance_less)<dt class="text-ink-500">{{ __('Advance') }}</dt><dd class="money text-end">− {{ $money($inv->advance_less) }}</dd>@endif
        <dt class="text-ink-500">{{ __('Received') }}</dt><dd class="money text-end">{{ $money($inv->amount_paid) }}</dd>
        @if ((float) $inv->amount_credited)<dt class="text-ink-500">{{ __('Credited') }}</dt><dd class="money text-end">{{ $money($inv->amount_credited) }}</dd>@endif
        <dt class="font-semibold">{{ __('Balance') }}</dt><dd class="money text-end font-semibold">{{ $money($inv->balance_due) }}</dd>
      @endunless
    </dl>
  </div>

  <div class="mt-5 flex flex-wrap gap-2">
    <a href="{{ route('invoices.print', $inv) }}" target="_blank" rel="noopener" class="btn-secondary">{{ __('Print') }}</a>
    <a href="{{ route('invoices.pdf', $inv) }}" class="btn-secondary"><x-icon name="download" /> {{ $canPdf ? __('PDF') : __('PDF (print to PDF)') }}</a>
    @if (! $inv->isVoid())
      @can('invoice.send')
        <form method="POST" action="{{ route('invoices.shared', $inv) }}" target="_blank">@csrf<input type="hidden" name="channel" value="whatsapp"><button class="btn-secondary">{{ __('WhatsApp') }}</button></form>
      @endcan
      @if ($inv->isOpen() && (float) $inv->balance_due > 0)
        @can('payment.record')<a href="{{ route('receipts.create', ['invoice' => $inv->id]) }}" class="btn-primary"><x-icon name="wallet" /> {{ __('Record payment') }}</a>@endcan
      @endif
      @can('invoice.create')
        <form method="POST" action="{{ route('invoices.against', $inv) }}" class="flex gap-2">@csrf
          @if ($inv->doc_type !== 'credit_note')<button name="type" value="credit_note" class="btn-ghost">{{ __('Credit note') }}</button>@endif
          @if ($inv->doc_type === 'invoice')<button name="type" value="debit_note" class="btn-ghost">{{ __('Debit note') }}</button><button name="type" value="supplementary" class="btn-ghost">{{ __('Supplementary bill') }}</button>@endif
        </form>
      @endcan
    @endif
  </div>
  @foreach (['invoice', 'reason'] as $f)@error($f)<p class="error mt-2">{{ $message }}</p>@enderror @endforeach
  @if ($publicUrl && ! $inv->isVoid())<p class="mt-3 text-xs text-ink-500">{{ __('Secure link for the client:') }} <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="break-all font-mono text-brand-700 hover:underline">{{ $publicUrl }}</a></p>@endif
  @if ($inv->isVoid())<p class="mt-3 rounded-xl bg-ink-100 px-3 py-2 text-sm">{{ __('Void since :date: :reason', ['date' => $inv->voided_at->format('d M Y'), 'reason' => $inv->void_reason]) }}</p>@endif
</section>

<div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_24rem]">
  <section class="card overflow-hidden">
    <iframe src="{{ route('invoices.print', $inv) }}" title="{{ __('The printed bill') }}" class="h-[1100px] w-full border-0 bg-ink-50"></iframe>
  </section>

  <div class="grid content-start gap-6">
    <section class="card p-6">
      <h3 class="font-semibold">{{ __('Payments') }}</h3>
      <ul class="mt-3 space-y-1.5 text-sm">
        @forelse ($inv->allocations as $a)
          <li class="flex justify-between gap-2 {{ $a->receipt->status === 'void' ? 'line-through opacity-60' : '' }}"><a href="{{ route('receipts.show', $a->receipt) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $a->receipt->number }}</a><span class="text-xs text-ink-500">{{ $a->receipt->receipt_date->format('d M') }}{{ (float) $a->tds ? ' · TDS ' . $money($a->tds) : '' }}</span><span class="money">{{ $money($a->total()) }}</span></li>
        @empty
          <li class="text-ink-500">{{ __('Nothing received yet.') }}</li>
        @endforelse
      </ul>
      @if ($inv->withholding && (float) ($inv->withholding['amount'] ?? 0) > 0)<p class="mt-3 text-xs text-ink-500">{{ __('Expected TDS from the client: :amount (:section).', ['amount' => $money($inv->withholding['amount']), 'section' => $inv->withholding['label'] ?? '']) }}</p>@endif
    </section>

    @if ($inv->notesAgainst->isNotEmpty())
      <section class="card p-6">
        <h3 class="font-semibold">{{ __('Notes and supplementary bills') }}</h3>
        <ul class="mt-3 space-y-1.5 text-sm">@foreach ($inv->notesAgainst as $n)<li class="flex justify-between gap-2"><a href="{{ $n->isDraft() ? route('invoices.edit', $n) : route('invoices.show', $n) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $n->numberOrDraft() }}</a><span class="text-xs">{{ $n->kindLabel() }}</span><span class="money">{{ $money($n->total) }}</span></li>@endforeach</ul>
      </section>
    @endif

    @can('invoice.send')
      @unless ($inv->isVoid() || $inv->isCreditNote())
        <form method="POST" action="{{ route('invoices.submit', $inv) }}" class="card grid gap-2 p-6" novalidate>@csrf
          <h3 class="font-semibold">{{ __('Bill submission') }}</h3>
          @if ($inv->submitted_on)<p class="text-sm text-ink-700">{{ __('Submitted :date by :via', ['date' => $inv->submitted_on->format('d M Y'), 'via' => $inv->submitted_via]) }}{{ $inv->submission_ref ? ' · ' . $inv->submission_ref : '' }}</p>@endif
          <div class="grid grid-cols-2 gap-2">
            <select name="submitted_via" class="input py-2" aria-label="{{ __('How') }}">@foreach (['hand' => __('By hand'), 'courier' => __('Courier'), 'email' => __('Email'), 'portal' => __('Client portal')] as $v => $n)<option value="{{ $v }}" @selected($inv->submitted_via === $v)>{{ $n }}</option>@endforeach</select>
            <input type="date" name="submitted_on" value="{{ $inv->submitted_on?->toDateString() ?? now()->toDateString() }}" class="input py-2" aria-label="{{ __('On') }}">
          </div>
          <input name="submission_ref" class="input py-2" maxlength="60" value="{{ $inv->submission_ref }}" placeholder="{{ __('Docket / acknowledgement no.') }}" aria-label="{{ __('Reference') }}">
          <button class="btn-secondary">{{ __('Save') }}</button>
        </form>
      @endunless
    @endcan

    @if ($deliveries->isNotEmpty())
      <section class="card p-6">
        <h3 class="font-semibold">{{ __('Shared and opened') }}</h3>
        <ul class="mt-3 space-y-1 text-xs text-ink-700">@foreach ($deliveries as $d)<li>{{ $d->sent_at?->format('d M H:i') }} · {{ $d->channel }} · {{ $d->status }}{{ $d->recipient ? ' · ' . $d->recipient : '' }}</li>@endforeach</ul>
      </section>
    @endif

    @if (! $inv->isVoid())
      @can('invoice.void')
        <form method="POST" action="{{ route('invoices.void', $inv) }}" class="card space-y-2 p-6" data-confirm="{{ __('Void this bill? Its number stays used and its LRs can be billed again.') }}">@csrf
          <label class="label" for="void_reason">{{ __('Void the bill') }}</label>
          <input id="void_reason" name="reason" class="input" maxlength="255" placeholder="{{ __('Why') }}">
          <button class="btn-danger w-full">{{ __('Void') }}</button>
        </form>
      @endcan
    @endif
  </div>
</div>
@endsection
