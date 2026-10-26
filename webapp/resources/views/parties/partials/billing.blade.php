{{-- The client's billing view (M24, 13-parity #20): what to bill, what is due, what came in. $b from PartyController::billing(). --}}
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $qTones = ['draft' => 'bg-ink-100 text-ink-700', 'issued' => 'bg-brand-50 text-brand-700', 'accepted' => 'bg-emerald-50 text-paid', 'declined' => 'bg-red-50 text-overdue', 'cancelled' => 'bg-ink-100 text-ink-500'];
@endphp
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <div class="stat"><div class="stat-label">{{ __('Not billed yet') }}</div><div class="stat-value money text-2xl">{{ $m($b['unbilled_total']) }}</div><div class="mt-1 text-xs text-ink-500">{{ trans_choice(':count LR waiting|:count LRs waiting', $b['unbilled']->count()) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('Billed, not paid') }}</div><div class="stat-value money text-2xl">{{ $m($b['due']) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('Overdue') }}</div><div class="stat-value money text-2xl {{ $b['overdue'] > 0 ? 'text-overdue' : '' }}">{{ $m($b['overdue']) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('Received this year') }}</div><div class="stat-value money text-2xl">{{ $m($b['received']) }}</div><div class="mt-1 text-xs text-ink-500">{{ $b['year']->start->format('d M Y') }} – {{ $b['year']->end->format('d M Y') }}</div></div>
</div>

@can('invoice.send')
  {{-- The client's own page (M47): one link with their loads and tracking, and their open bills. --}}
  @php($page = session('client_page') ?? $b['client_page'])
  <section class="card p-5" data-client-page>
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h3 class="font-semibold">{{ __('Client page') }}</h3>
        <p class="mt-1 text-sm text-ink-500">{{ __('One link for the client: their loads of the last :days days with tracking, and their open bills. No sign-in; never another client’s.', ['days' => \App\Modules\Parties\Services\ClientPage::DAYS]) }}</p>
      </div>
      @unless ($page)
        <form method="POST" action="{{ route('parties.client-page.store', $party) }}">@csrf<button class="btn-secondary"><x-icon name="send" /> {{ __('Make the link') }}</button></form>
      @endunless
    </div>
    @if ($page)
      <div class="mt-3 flex flex-wrap items-center gap-2">
        <input class="input min-w-0 flex-1 font-mono text-xs" value="{{ $page['url'] }}" readonly aria-label="{{ __('Client page link') }}" onclick="this.select()">
        <a href="https://wa.me/?text={{ rawurlencode($page['message']) }}" class="btn-secondary" target="_blank" rel="noopener noreferrer"><x-icon name="send" /> {{ __('WhatsApp') }}</a>
        <a href="{{ $page['url'] }}" class="btn-secondary" target="_blank" rel="noopener">{{ __('Open') }}</a>
        <form method="POST" action="{{ route('parties.client-page.destroy', $party) }}" onsubmit="return confirm(@js(__('Stop this link? It will no longer open. You can make a new one.')))">@csrf @method('DELETE')<button class="btn-ghost text-overdue">{{ __('Stop the link') }}</button></form>
      </div>
    @endif
  </section>
@endcan

<section class="card overflow-x-auto">
  <div class="flex flex-wrap items-center justify-between gap-2 border-b border-ink-100 px-5 py-3">
    <h3 class="font-semibold">{{ __('LRs to bill') }}</h3>
  </div>
  @if ($b['unbilled']->isEmpty())
    <p class="px-5 py-6 text-sm text-ink-500">{{ __('Nothing waiting: every LR of this client is on a bill.') }}</p>
  @else
    <form method="POST" action="{{ route('invoices.store') }}">
      @csrf
      <input type="hidden" name="party_id" value="{{ $party->id }}">
      <table class="table">
        <thead><tr>@can('invoice.create')<th class="w-8"><span class="sr-only">{{ __('Bill it') }}</span></th>@endcan<th>{{ __('LR') }}</th><th>{{ __('Date') }}</th><th>{{ __('Trip') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
        <tbody>
          @foreach ($b['unbilled'] as $c)
            <tr>
              @can('invoice.create')<td><input type="checkbox" name="loads[]" value="{{ $c->id }}" class="size-4 rounded border-ink-300" checked aria-label="{{ __('Bill LR :lr', ['lr' => $c->lr_number]) }}"></td>@endcan
              <td class="font-mono text-sm">{{ $c->lr_number }}</td>
              <td class="nowrap text-sm">{{ $c->lr_date?->format('d M Y') }}</td>
              <td><a href="{{ route('trips.show', $c->trip_id) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $c->trip?->label }}</a></td>
              <td class="text-sm">{{ $c->pod_received_at ? __('POD received') : __(ucfirst(str_replace('_', ' ', $c->status))) }}</td>
              <td class="money text-end">{{ $m((float) $c->freight + (float) $c->loading_charge + (float) $c->unloading_charge + (float) $c->other_charges) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      @can('invoice.create')<div class="flex justify-end border-t border-ink-100 p-4"><button class="btn-primary"><x-icon name="plus" /> {{ __('Make the bill for the ticked LRs') }}</button></div>@endcan
    </form>
  @endif
</section>

<section class="card overflow-x-auto">
  <div class="flex items-center justify-between border-b border-ink-100 px-5 py-3"><h3 class="font-semibold">{{ __('Bills') }}</h3>
    <a href="{{ route('invoices.index', ['party' => $party->id, 'tab' => 'all']) }}" class="text-sm text-brand-700 hover:underline">{{ __('All bills') }}</a></div>
  <table class="table">
    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Balance') }}</th><th></th></tr></thead>
    <tbody>
      @forelse ($b['invoices'] as $invoice)
        <tr class="{{ $invoice->isVoid() ? 'opacity-60' : '' }}">
          <td><a href="{{ route('invoices.show', $invoice) }}" class="font-mono text-sm font-semibold text-brand-700 hover:underline">{{ $invoice->label }}</a></td>
          <td class="nowrap text-sm">{{ $invoice->issue_date?->format('d M Y') }}</td>
          <td class="nowrap text-sm">{{ $invoice->due_date?->format('d M Y') }}</td>
          <td>@include('invoices._status', ['invoice' => $invoice])</td>
          <td class="money text-end">{{ $m($invoice->total) }}</td>
          <td class="money text-end">{{ (float) $invoice->balance_due ? $m($invoice->balance_due) : '—' }}</td>
          <td class="nowrap text-end">
            @if ($invoice->isFinal())<a href="{{ route('invoices.pdf', $invoice) }}" class="btn-ghost px-2 py-1 text-xs">{{ __('PDF') }}</a>@endif
            @if (in_array($invoice->status, \App\Modules\Invoicing\Models\Invoice::OPEN_STATUSES, true) && (float) $invoice->balance_due > 0 && ! $invoice->isCreditNote())
              @can('payment.record')<a href="{{ route('receipts.create', ['invoice' => $invoice->id]) }}" class="btn-secondary px-2 py-1 text-xs">{{ __('Settle') }}</a>@endcan
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="py-6 text-center text-sm text-ink-500">{{ __('No bills yet.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="card overflow-x-auto">
    <div class="flex items-center justify-between border-b border-ink-100 px-5 py-3"><h3 class="font-semibold">{{ __('Payments') }}</h3>
      @can('payment.record')<a href="{{ route('receipts.create', ['party' => $party->id]) }}" class="btn-secondary px-2 py-1 text-xs"><x-icon name="plus" class="size-4" /> {{ __('Add payment') }}</a>@endcan</div>
    <table class="table">
      <tbody>
        @forelse ($b['receipts'] as $r)
          <tr class="{{ $r->isVoid() ? 'opacity-60' : '' }}">
            <td class="nowrap text-sm">{{ $r->receipt_date->format('d M Y') }}<div><a href="{{ route('receipts.show', $r) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $r->number }}</a></div></td>
            <td class="text-sm">{{ __(\App\Modules\Invoicing\Models\Receipt::MODES[$r->mode] ?? $r->mode) }}@if ($r->reference) · <span class="font-mono text-xs">{{ $r->reference }}</span>@endif
              <div class="text-xs text-ink-500">{{ $r->allocations->map(fn ($a) => $a->invoice?->number)->filter()->join(', ') ?: __('Kept as advance') }}</div></td>
            <td class="money text-end text-sm">{{ $m($r->amount) }}</td>
          </tr>
        @empty
          <tr><td class="py-6 text-center text-sm text-ink-500">{{ __('No money received yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>

  <section class="card overflow-x-auto">
    <div class="flex items-center justify-between border-b border-ink-100 px-5 py-3"><h3 class="font-semibold">{{ __('Quotations') }}</h3>
      @can('invoice.create')<a href="{{ route('quotations.create', ['party' => $party->id]) }}" class="btn-secondary px-2 py-1 text-xs"><x-icon name="plus" class="size-4" /> {{ __('New quotation') }}</a>@endcan</div>
    <table class="table">
      <tbody>
        @forelse ($b['quotations'] as $q)
          <tr>
            <td><a href="{{ route('quotations.show', $q) }}" class="font-mono text-sm text-brand-700 hover:underline">{{ $q->label }}</a></td>
            <td class="nowrap text-sm">{{ $q->quote_date->format('d M Y') }}</td>
            <td><span class="pill {{ $q->isExpired() ? 'bg-amber-50 text-partial' : ($qTones[$q->status] ?? '') }}">{{ $q->statusLabel() }}</span></td>
          </tr>
        @empty
          <tr><td class="py-6 text-center text-sm text-ink-500">{{ __('No quotations yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </section>
</div>
