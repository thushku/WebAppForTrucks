@extends('layouts.app')
@section('title', __('Parties'))
@section('content')
@php
  $tabs = ['' => __('All')] + collect(\App\Modules\Parties\Models\Party::ROLES)->map(fn ($r) => __($r[0]))->all();
  $tones = ['client' => 'bg-brand-50 text-brand-700', 'consignor' => 'bg-sky-50 text-sky-700', 'consignee' => 'bg-cyan-50 text-cyan-700',
            'supplier' => 'bg-amber-50 text-amber-800', 'vendor' => 'bg-violet-50 text-violet-700', 'broker' => 'bg-rose-50 text-rose-700'];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Roles') }}">
    @foreach ($tabs as $key => $label)
      <a href="{{ route('parties.index', array_filter(['role' => $key, 'q' => $search, 'status' => $status === 'active' ? null : $status])) }}" role="tab" aria-selected="{{ $role === $key ? 'true' : 'false' }}"
         class="rounded-xl border px-3 py-2 text-sm font-medium {{ $role === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700 hover:border-brand-200' }}">
        {{ $label }}@if ($key !== '' && ($counts[$key] ?? 0))<span class="ms-1 text-xs text-ink-400">{{ $counts[$key] }}</span>@endif
      </a>
    @endforeach
  </div>
  @can('party.manage')
    <a href="{{ route('parties.create', $role ? ['role' => $role] : []) }}" class="btn-primary"><x-icon name="plus" /> {{ __('Add party') }}</a>
  @endcan
</div>
<p class="text-xs text-ink-500">{{ __('Supplier = owner or broker of a hired truck. Vendor = sells you goods or services (fuel, repairs, tyres…).') }}</p>

<section class="card overflow-hidden">
  <form method="GET" action="{{ route('parties.index') }}" class="flex flex-wrap items-center gap-2 border-b border-ink-100 p-4" role="search">
    @if ($role)<input type="hidden" name="role" value="{{ $role }}">@endif
    <input name="q" value="{{ $search }}" class="input max-w-xs" placeholder="{{ __('Name, code, mobile or GSTIN') }}" maxlength="100" aria-label="{{ __('Search parties') }}">
    <select name="status" class="input w-40" aria-label="{{ __('Status') }}">
      <option value="active" @selected($status === 'active')>{{ __('In use') }}</option>
      <option value="inactive" @selected($status === 'inactive')>{{ __('Switched off') }}</option>
      <option value="all" @selected($status === 'all')>{{ __('All') }}</option>
    </select>
    <button class="btn-secondary px-3" aria-label="{{ __('Search') }}"><x-icon name="search" /></button>
  </form>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Party') }}</th><th>{{ __('Roles') }}</th><th>{{ __('City') }}</th><th>{{ __('Tax number') }}</th><th>{{ __('Mobile') }}</th></tr></thead>
      <tbody>
        @forelse ($parties as $party)
          <tr class="{{ $party->active ? '' : 'opacity-60' }}">
            <td>
              <a href="{{ route('parties.show', $party) }}" class="font-medium text-ink-900 hover:text-brand-700">{{ $party->name }}</a>
              @if ($party->short_code)<span class="docno ms-1 font-mono text-xs text-ink-400">{{ $party->short_code }}</span>@endif
              @if ($party->blacklisted)<span class="pill ms-1 bg-red-50 text-overdue">{{ __('Blacklisted') }}</span>@endif
            </td>
            <td class="space-x-1">
              @foreach ($party->roles as $r)
                <span class="pill {{ $tones[$r->role] ?? 'bg-ink-100 text-ink-700' }}">{{ __(\App\Modules\Parties\Models\Party::ROLES[$r->role][0] ?? $r->role) }}</span>
              @endforeach
            </td>
            <td class="text-ink-700">{{ $party->city }}</td>
            <td class="docno font-mono text-xs">{{ $party->primaryTaxId()?->tax_id }}@if ($party->taxIds->count() > 1) <span class="text-ink-400">+{{ $party->taxIds->count() - 1 }}</span>@endif</td>
            <td class="nowrap text-sm">{{ $party->mobile }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-12 text-center text-ink-500">
            {{ $search !== '' ? __('No party matches.') : __('No parties yet — add your first client.') }}
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($parties->hasPages())<div class="p-4">{{ $parties->links() }}</div>@endif
</section>
@endsection
