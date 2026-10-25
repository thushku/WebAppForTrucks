@extends('layouts.app')
@section('title', __('Bank reconciliation'))
@section('content')
@include('accounts._nav')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $canPost = auth()->user()->can('accounts.post');
@endphp

@if ($banks->isEmpty())
  <section class="card p-6 text-sm text-ink-600">{{ __('No bank ledger yet. Add the company’s bank account under Settings → Bank accounts; its ledger appears here.') }}</section>
@else
  <section class="card space-y-4 p-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <form method="GET" class="flex items-end gap-2">
        <div><label class="label" for="bk_ledger">{{ __('Bank') }}</label>
          <select id="bk_ledger" name="ledger" class="input">@foreach ($banks as $b)<option value="{{ $b->id }}" @selected($bank?->id === $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        <button class="btn-secondary">{{ __('Show') }}</button>
      </form>
      @if ($canPost)
        <form method="POST" action="{{ route('accounts.bank.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
          @csrf
          <input type="hidden" name="ledger_account_id" value="{{ $bank->id }}">
          <div><label class="label" for="bk_file">{{ __('Bank statement (CSV)') }}</label><input id="bk_file" name="file" type="file" accept=".csv,text/csv" class="input" required></div>
          <button class="btn-primary"><x-icon name="upload" class="size-4" /> {{ __('Bring it in') }}</button>
        </form>
      @endif
    </div>
    <p class="text-xs text-ink-500">{{ __('Download the statement from net banking as CSV (Excel: save as CSV). Lines brought in before are left out; lines with one clear entry of the same amount in the book within :d days are matched by themselves.', ['d' => \App\Modules\Accounts\Services\BankReconciliation::DAYS]) }}</p>
    @error('file')<p class="error">{{ $message }}</p>@enderror
  </section>

  @if ($summary)
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="stat"><div class="stat-label">{{ __('Book balance on :date', ['date' => $summary['as_of']->format('d M Y')]) }}</div><div class="stat-value money text-2xl">{{ $m($summary['book']) }}</div></div>
      <div class="stat"><div class="stat-label">{{ __('Bank balance (statement)') }}</div><div class="stat-value money text-2xl">{{ $summary['bank'] !== null ? $m($summary['bank']) : '—' }}</div></div>
      <div class="stat"><div class="stat-label">{{ __('On the statement, not in the book') }}</div><div class="stat-value text-2xl">{{ $summary['open'] }}</div><div class="mt-1 text-xs text-ink-500">{{ __('in :in · out :out', ['in' => $m($summary['open_in']), 'out' => $m($summary['open_out'])]) }}</div></div>
      <div class="stat"><div class="stat-label">{{ __('In the book, not on the statement') }}</div><div class="stat-value text-2xl">{{ $summary['not_on_statement']->count() }}</div><div class="mt-1 text-xs text-ink-500">{{ __('Cheques not yet cleared, entries to check') }}</div></div>
    </div>
    @if ($summary['bank'] !== null)
      @php
        $explained = round($summary['book'] + $summary['open_in'] - $summary['open_out'] - $summary['not_on_statement']->sum(fn ($l) => (float) $l->debit - (float) $l->credit), 2);
      @endphp
      <p class="rounded-xl px-4 py-3 text-sm {{ abs($explained - $summary['bank']) < 0.01 ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">
        {{ abs($explained - $summary['bank']) < 0.01 ? __('Reconciled: the book, with the open lines on both sides, comes to the bank’s balance.') : __('Still :amount apart after the open lines — look for an entry with the wrong amount or date.', ['amount' => $m(abs($explained - $summary['bank']))]) }}
      </p>
    @endif
  @endif

  <div class="flex flex-wrap items-center gap-2">
    @foreach (['open' => __('To match'), 'matched' => __('Matched'), 'ignored' => __('Left out'), 'all' => __('All')] as $key => $label)
      <a href="{{ route('accounts.bank', ['ledger' => $bank->id, 'show' => $key]) }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $show === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }}</a>
    @endforeach
    @if ($canPost)<form method="POST" action="{{ route('accounts.bank.auto') }}" class="ms-auto">@csrf<input type="hidden" name="ledger_account_id" value="{{ $bank->id }}"><button class="btn-secondary text-sm"><x-icon name="rotate" class="size-4" /> {{ __('Match again') }}</button></form>@endif
  </div>

  <section class="card overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Bank’s description') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th>{{ __('In the book') }}</th></tr></thead>
      <tbody>
        @forelse ($lines as $line)
          <tr>
            <td class="nowrap text-sm">{{ $line->line_date->format('d M Y') }}</td>
            <td class="text-sm">{{ $line->description }}@if ($line->reference)<div class="font-mono text-xs text-ink-500">{{ $line->reference }}</div>@endif</td>
            <td class="money text-end text-sm">{{ (float) $line->amount > 0 ? $m($line->amount) : '' }}</td>
            <td class="money text-end text-sm">{{ (float) $line->amount < 0 ? $m(abs($line->amount)) : '' }}</td>
            <td class="text-sm">
              @if ($line->status === 'matched')
                <a href="{{ route('accounts.vouchers.show', $line->voucherLine->voucher_id) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $line->voucherLine->voucher->number }}</a>
                <span class="text-xs text-ink-500">· {{ ['auto' => __('by itself'), 'hand' => __('by hand'), 'booked' => __('booked here')][$line->match_how] ?? '' }}</span>
                @if ($canPost)<form method="POST" action="{{ route('accounts.bank.match', $line) }}" class="inline">@csrf<input type="hidden" name="action" value="undo"><button class="text-xs text-ink-500 underline">{{ __('undo') }}</button></form>@endif
              @elseif ($line->status === 'ignored')
                <span class="text-xs text-ink-500">{{ __('Left out: :note', ['note' => $line->note]) }}</span>
                @if ($canPost)<form method="POST" action="{{ route('accounts.bank.match', $line) }}" class="inline">@csrf<input type="hidden" name="action" value="undo"><button class="text-xs text-ink-500 underline">{{ __('undo') }}</button></form>@endif
              @elseif ($canPost)
                <div class="flex flex-wrap items-center gap-2">
                  @if (($candidates[$line->id] ?? collect())->isNotEmpty())
                    <form method="POST" action="{{ route('accounts.bank.match', $line) }}" class="flex gap-1">@csrf<input type="hidden" name="action" value="match">
                      <select name="voucher_line_id" class="input py-1 text-xs" aria-label="{{ __('Entry') }}">@foreach ($candidates[$line->id] as $c)<option value="{{ $c->id }}">{{ $c->number }} · {{ \Carbon\CarbonImmutable::parse($c->voucher_date)->format('d M') }} · {{ \Illuminate\Support\Str::limit($c->narration, 40) }}</option>@endforeach</select>
                      <button class="btn-primary px-2 py-1 text-xs">{{ __('Match') }}</button></form>
                  @else
                    <span class="text-xs text-ink-500">{{ __('No entry of this amount') }}</span>
                  @endif
                  <form method="POST" action="{{ route('accounts.bank.match', $line) }}">@csrf<input type="hidden" name="action" value="book"><button class="btn-secondary px-2 py-1 text-xs">{{ (float) $line->amount > 0 ? __('Book as interest') : __('Book as bank charges') }}</button></form>
                  <details><summary class="cursor-pointer text-xs text-ink-500 underline">{{ __('leave out') }}</summary>
                    <form method="POST" action="{{ route('accounts.bank.match', $line) }}" class="mt-1 flex gap-1">@csrf<input type="hidden" name="action" value="ignore"><input name="note" class="input py-1 text-xs" placeholder="{{ __('Why') }}" maxlength="255" required aria-label="{{ __('Why') }}"><button class="btn-secondary px-2 py-1 text-xs">{{ __('OK') }}</button></form>
                  </details>
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-6 text-center text-sm text-ink-500">{{ $show === 'open' ? __('Nothing to match. Bring in the next statement when it comes.') : __('No lines here.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>

  @if ($summary && $summary['not_on_statement']->isNotEmpty())
    <section class="card overflow-x-auto">
      <h3 class="border-b border-ink-100 px-5 py-3 font-semibold">{{ __('In the book, not on the statement') }}</h3>
      <table class="table">
        <tbody>
          @foreach ($summary['not_on_statement'] as $l)
            <tr><td class="nowrap text-sm">{{ \Carbon\CarbonImmutable::parse($l->voucher_date)->format('d M Y') }}</td><td class="text-sm"><a href="{{ route('accounts.vouchers.show', $l->voucher_id) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $l->number }}</a> {{ $l->narration }}@if ($l->reference)<span class="font-mono text-xs text-ink-500"> · {{ $l->reference }}</span>@endif</td><td class="money text-end text-sm">{{ (float) $l->debit ? '+ ' . $m($l->debit) : '− ' . $m($l->credit) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </section>
  @endif
@endif
@endsection
