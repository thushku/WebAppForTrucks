@extends('layouts.app')
@section('title', $party->name)
@section('content')
@php
  $taxLabel = \App\Modules\Parties\Services\TaxIds::label($party->country);
  $dial = $party->mobile ? preg_replace('/[^0-9+]/', '', $party->mobile) : null;
  $wa   = $dial ? 'https://wa.me/' . ltrim($dial, '+') . '?text=' . rawurlencode(__('Hello :name', ['name' => $party->name])) : null;
  $isSupplier = $party->hasRole('supplier') || $party->hasRole('broker');
  $tabs = ['overview' => __('Overview')] + ($party->hasRole('client') && auth()->user()->can('invoice.view') ? ['billing' => __('Billing')] : [])
    + ($party->hasRole('client') || $isSupplier ? ['rates' => __('Rates')] : []) + ($isSupplier ? ['vehicles' => __('Trucks')] : []);
@endphp
<section class="card p-6">
  <div class="flex flex-wrap items-start gap-4">
    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-lime-50 text-lime-700"><x-icon name="handshake" class="size-6" /></span>
    <div class="min-w-0 flex-1">
      <h2 class="text-2xl font-semibold">{{ $party->name }}
        @if ($party->short_code)<span class="docno ms-1 align-middle font-mono text-sm text-ink-400">{{ $party->short_code }}</span>@endif
      </h2>
      <p class="mt-0.5 text-sm text-ink-500">
        {{ collect($party->roleCodes())->map(fn ($r) => __(\App\Modules\Parties\Models\Party::ROLES[$r][0]))->join(' · ') }}
        @if ($party->legal_name && $party->legal_name !== $party->name) · {{ $party->legal_name }}@endif
        @if ($party->group) · {{ __('Part of :group', ['group' => $party->group->name]) }}@endif
      </p>
      <div class="mt-2 flex flex-wrap gap-1.5">
        @unless ($party->active)<span class="pill bg-ink-100 text-ink-500">{{ __('Switched off') }}</span>@endunless
        @if ($party->blacklisted)<span class="pill bg-red-50 text-overdue" title="{{ $party->blacklist_reason }}"><x-icon name="alert" class="size-3" /> {{ __('Blacklisted') }}: {{ $party->blacklist_reason }}</span>@endif
        @if ($party->udyam_type)<span class="pill bg-amber-50 text-partial">{{ __('MSME :type — pay within 45 days', ['type' => __(ucfirst($party->udyam_type))]) }}</span>@endif
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($dial)
        <a href="tel:{{ $dial }}" class="btn-secondary"><x-icon name="phone" /> {{ __('Call') }}</a>
        <a href="{{ $wa }}" class="btn-secondary" target="_blank" rel="noopener noreferrer">{{ __('WhatsApp') }}</a>
      @endif
      @canany(['ledger.view', 'accounts.view'])
        @if ($ledgerId = \App\Modules\Accounts\Models\LedgerAccount::where('system_key', 'party:' . $party->id)->value('id'))<a href="{{ route('ledgers.show', $ledgerId) }}" class="btn-secondary"><x-icon name="ledger" /> {{ __('Ledger') }}</a>@endif
      @endcanany
      @can('party.manage')<a href="{{ route('parties.edit', $party) }}" class="btn-primary">{{ __('Edit') }}</a>@endcan
    </div>
  </div>
  <nav class="mt-5 flex gap-1 border-b border-ink-100" aria-label="{{ __('Party sections') }}">
    @foreach ($tabs as $key => $label)
      <a href="{{ route('parties.show', [$party, 'tab' => $key]) }}" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-900' }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    @can('document.view')<a href="{{ route('documents.owner', ['party', $party->id]) }}" class="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-500 hover:text-ink-900">{{ __('Documents') }}</a>@endcan
  </nav>
</section>

@if ($tab === 'overview')
  @include('parties.partials.overview')
@elseif ($tab === 'billing' && $billing)
  @include('parties.partials.billing', ['b' => $billing])
@elseif ($tab === 'rates')
  @include('parties.partials.rates')
@else
  @include('parties.partials.vehicles')
@endif
@endsection
