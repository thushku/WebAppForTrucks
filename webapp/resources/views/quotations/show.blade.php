@extends('layouts.app')
@section('title', $quotation->label)
@section('content')
@php
  $q = $quotation;
  $m = fn ($v) => $v !== null ? \App\Support\Money::company($v) : '—';
  $bases = \App\Modules\Parties\Models\RateContractLine::BASES;
  $tones = ['draft' => 'bg-ink-100 text-ink-700', 'issued' => 'bg-brand-50 text-brand-700', 'accepted' => 'bg-emerald-50 text-paid', 'declined' => 'bg-red-50 text-overdue', 'cancelled' => 'bg-ink-100 text-ink-500'];
@endphp
<div class="flex flex-wrap items-start justify-between gap-3">
  <div>
    <a href="{{ route('quotations.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Quotations') }}</a>
    <h1 class="mt-1 text-2xl font-semibold"><span class="font-mono">{{ $q->label }}</span>
      <span class="pill ms-1 align-middle {{ $q->isExpired($today) ? 'bg-amber-50 text-partial' : ($tones[$q->status] ?? '') }}">{{ $q->statusLabel($today) }}</span></h1>
    <p class="mt-1 text-sm text-ink-700"><a href="{{ route('parties.show', $q->party) }}" class="font-medium hover:underline">{{ $q->party->name }}</a> · {{ $q->quote_date->format('d M Y') }}@if ($q->valid_until) · {{ __('valid until :date', ['date' => $q->valid_until->format('d M Y')]) }}@endif</p>
    @if ($q->subject)<p class="text-sm text-ink-500">{{ $q->subject }}</p>@endif
    @if ($q->decision_note)<p class="mt-1 text-sm text-ink-600">{{ __(':status — :note', ['status' => $q->statusLabel($today), 'note' => $q->decision_note]) }}</p>@endif
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('quotations.print', $q) }}" target="_blank" rel="noopener" class="btn-secondary"><x-icon name="printer" /> {{ __('Print') }}</a>
    <a href="{{ route('quotations.pdf', $q) }}" class="btn-secondary"><x-icon name="download" /> {{ __('PDF') }}</a>
    @can('invoice.create')
      @if ($q->isDraft())
        <a href="{{ route('quotations.edit', $q) }}" class="btn-secondary">{{ __('Edit') }}</a>
        <form method="POST" action="{{ route('quotations.issue', $q) }}">@csrf<button class="btn-primary"><x-icon name="send" /> {{ __('Give it a number and send') }}</button></form>
      @else
        <form method="POST" action="{{ route('quotations.copy', $q) }}">@csrf<button class="btn-secondary">{{ __('Copy as a new quotation') }}</button></form>
      @endif
    @endcan
  </div>
</div>
@error('lines')<p class="error">{{ $message }}</p>@enderror
@error('quotation')<p class="error">{{ $message }}</p>@enderror

<section class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>{{ __('Route') }}</th><th>{{ __('Material') }}</th><th>{{ __('Truck') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Min. MT') }}</th><th>{{ __('Detention') }}</th><th class="text-end">{{ __('Loading / unloading') }}</th>@can('trip.create')<th></th>@endcan</tr></thead>
    <tbody>
      @forelse ($q->lines as $l)
        <tr>
          <td class="font-medium">{{ $l->from->name }} → {{ $l->to->name }}@if ($l->note)<div class="text-xs font-normal text-ink-500">{{ $l->note }}</div>@endif</td>
          <td>{{ $l->material ? ($names['materials'][$l->material] ?? $l->material) : __('Any') }}</td>
          <td>{{ $l->truck_type ? ($names['truckTypes'][$l->truck_type] ?? $l->truck_type) : __('Any') }}</td>
          <td class="money text-end font-semibold">{{ $m($l->rate) }} <span class="text-xs font-normal text-ink-500">{{ __($bases[$l->basis] ?? $l->basis) }}</span></td>
          <td class="text-end">{{ $l->min_weight_mt !== null ? rtrim(rtrim((string) $l->min_weight_mt, '0'), '.') : '—' }}</td>
          <td class="nowrap text-sm">{{ $l->detention_rate_per_day !== null ? $m($l->detention_rate_per_day) . ' / ' . __('day after :h h', ['h' => $l->detention_free_hours ?? 0]) : '—' }}</td>
          <td class="money text-end text-sm">{{ $l->loading_charge || $l->unloading_charge ? $m($l->loading_charge) . ' / ' . $m($l->unloading_charge) : '—' }}</td>
          @can('trip.create')<td class="text-end">@if (in_array($q->status, ['issued', 'accepted'], true))<a href="{{ route('trips.create', ['quotation_line' => $l->id]) }}" class="btn-ghost px-2 py-1 text-xs"><x-icon name="truck" class="size-4" /> {{ __('Start a trip') }}</a>@endif</td>@endcan
        </tr>
      @empty
        <tr><td colspan="8" class="py-6 text-center text-sm text-ink-500">{{ __('No routes yet — edit the draft to add them.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>

<div class="grid gap-6 lg:grid-cols-2">
  @can('invoice.create')
    @if ($q->status === 'issued')
      <section class="card space-y-3 p-6">
        <h2 class="font-semibold">{{ __('The client’s answer') }}</h2>
        <form method="POST" action="{{ route('quotations.decide', $q) }}" class="space-y-3">
          @csrf
          <div><label class="label" for="qd_note">{{ __('How they answered, or why not') }}</label><input id="qd_note" name="note" class="input" maxlength="255" placeholder="{{ __('PO 4512 of 3 Nov / rate too high for Pune') }}">@error('note')<span class="error">{{ $message }}</span>@enderror</div>
          <div class="flex flex-wrap gap-2">
            <button name="decision" value="accepted" class="btn-primary"><x-icon name="check" /> {{ __('Accepted') }}</button>
            <button name="decision" value="declined" class="btn-secondary">{{ __('Declined') }}</button>
            <button name="decision" value="cancelled" class="btn-ghost text-sm">{{ __('Cancel the quotation') }}</button>
          </div>
        </form>
      </section>
    @endif
  @endcan
  @if ($q->status === 'accepted')
    <section class="card space-y-3 p-6">
      <h2 class="font-semibold">{{ __('Rate contract') }}</h2>
      @if ($q->rateContract)
        <p class="text-sm">{{ __('Made from this quotation:') }} <a href="{{ route('parties.rates.edit', [$q->party_id, $q->rateContract]) }}" class="font-medium text-brand-700 hover:underline">{{ $q->rateContract->label }}</a> · {{ __(\App\Modules\Parties\Models\RateContract::STATUSES[$q->rateContract->status] ?? $q->rateContract->status) }}</p>
      @elseif (auth()->user()->can('party.manage'))
        <p class="text-sm text-ink-600">{{ __('Make these routes the client’s rate contract: once approved, their LRs fill in these rates by themselves.') }}</p>
        @error('quotation')<p class="error">{{ $message }}</p>@enderror
        <form method="POST" action="{{ route('quotations.contract', $q) }}">@csrf<button class="btn-primary">{{ __('Make it the rate contract') }}</button></form>
      @endif
    </section>
  @endif

  @if ($email && auth()->user()->can('invoice.send') && $q->status !== 'cancelled')
    <section class="card p-6">
      <details>
        <summary class="cursor-pointer font-semibold">{{ __('Send by email') }}</summary>
        <form method="POST" action="{{ route('quotations.email', $q) }}" class="mt-3 space-y-3">
          @csrf
          <div><label class="label" for="qe_to">{{ __('To') }}</label><input id="qe_to" name="to_email" type="email" class="input" value="{{ old('to_email', $email['to']) }}" required>@error('to_email')<span class="error">{{ $message }}</span>@enderror</div>
          <div><label class="label" for="qe_subject">{{ __('Subject') }}</label><input id="qe_subject" name="subject" class="input" value="{{ old('subject', $email['subject']) }}" maxlength="200" required></div>
          <div><label class="label" for="qe_body">{{ __('Message') }}</label><textarea id="qe_body" name="body" rows="6" class="input" maxlength="5000" required>{{ old('body', $email['body']) }}</textarea></div>
          <div class="flex flex-wrap gap-2"><button class="btn-primary"><x-icon name="mail" /> {{ __('Send') }}</button>
            <a href="{{ route('quotations.whatsapp', $q) }}" target="_blank" rel="noopener" class="btn-secondary">{{ __('WhatsApp') }}</a></div>
        </form>
      </details>
    </section>
  @endif

  @if ($trips->isNotEmpty())
    <section class="card overflow-x-auto">
      <h2 class="border-b border-ink-100 px-5 py-3 font-semibold">{{ __('Trips at these rates') }}</h2>
      <table class="table"><tbody>
        @foreach ($trips as $c)
          <tr><td><a href="{{ route('trips.show', $c->trip_id) }}" class="font-mono text-sm text-brand-700 hover:underline">{{ $c->trip?->label }}</a></td><td class="text-sm">{{ $c->lr_number ?? '—' }}</td><td class="money text-end text-sm">{{ $m($c->freight) }}</td></tr>
        @endforeach
      </tbody></table>
    </section>
  @endif
</div>

@if ($q->terms)
  <section class="card p-6"><h2 class="font-semibold">{{ __('Terms') }}</h2><p class="mt-2 whitespace-pre-line text-sm text-ink-700">{{ $q->terms }}</p></section>
@endif
@if ($q->notes)
  <section class="card p-6"><h2 class="font-semibold">{{ __('Office note') }}</h2><p class="mt-2 whitespace-pre-line text-sm text-ink-700">{{ $q->notes }}</p></section>
@endif
@endsection
