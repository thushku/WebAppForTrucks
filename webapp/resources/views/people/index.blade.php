@extends('layouts.app')
@section('title', __('People'))
@section('content')
@php
  $paperTones = ['green' => 'bg-emerald-50 text-paid', 'amber' => 'bg-amber-50 text-partial', 'red' => 'bg-red-50 text-overdue'];
  $chip = ['valid' => ['bg-emerald-50 text-paid', __('Licence on file')], 'expiring' => ['bg-amber-50 text-partial', null], 'expired' => ['bg-red-50 text-overdue', __('Licence expired')], 'missing' => ['bg-red-50 text-overdue', __('Licence missing')]];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Type') }}">
    <a href="{{ route('people.index') }}" role="tab" aria-selected="{{ $type === '' ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $type === '' ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __('All') }}</a>
    @foreach (\App\Modules\People\Models\Person::TYPES as $key => $label)
      <a href="{{ route('people.index', ['type' => $key]) }}" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $type === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __($label) }} @if ($counts[$key] ?? 0)<span class="text-xs text-ink-400">{{ $counts[$key] }}</span>@endif</a>
    @endforeach
  </div>
  <div class="flex flex-wrap gap-2">
    @canany(['leave.approve', 'people.manage'])<a href="{{ route('people.leave') }}" class="btn-secondary"><x-icon name="calendar" /> {{ __('Leave') }}@if ($leaveWaiting) <span class="rounded-full bg-amber-100 px-1.5 text-xs font-semibold text-partial">{{ $leaveWaiting }}</span>@endif</a>@endcanany
    @can('people.manage')<a href="{{ route('people.create', $type ? ['type' => $type] : []) }}" class="btn-primary"><x-icon name="plus" /> {{ __('Add person') }}</a>@endcan
  </div>
</div>

<section class="card overflow-hidden">
  <form method="GET" action="{{ route('people.index') }}" class="flex flex-wrap gap-2 border-b border-ink-100 p-4" role="search">
    @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
    <input name="q" value="{{ $search }}" class="input max-w-xs" placeholder="{{ __('Name, mobile, licence or code') }}" maxlength="60" aria-label="{{ __('Search people') }}">
    <select name="status" class="input w-36" aria-label="{{ __('Status') }}">
      <option value="working" @selected($status === 'working')>{{ __('Working') }}</option>
      <option value="left" @selected($status === 'left')>{{ __('Left') }}</option>
      <option value="all" @selected($status === 'all')>{{ __('All') }}</option>
    </select>
    <button class="btn-secondary px-3" aria-label="{{ __('Search') }}"><x-icon name="search" /></button>
  </form>
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Mobile') }}</th><th>{{ __('Licence') }}</th>@if ($papers)<th>{{ __('Papers') }}</th>@endif<th>{{ __('Truck') }}</th><th>{{ __('App') }}</th><th><span class="sr-only">{{ __('Share') }}</span></th></tr></thead>
      <tbody>
        @forelse ($people as $p)
          @php($state = $p->licenceState())
          <tr class="{{ $p->status === 'left' ? 'opacity-60' : '' }}">
            <td>
              <div class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-indigo-50 font-display text-xs font-bold text-indigo-700">{{ $p->initials() }}</span>
                <div class="min-w-0">
                  <a href="{{ route('people.show', $p) }}" class="font-medium text-ink-900 hover:text-brand-700">{{ $p->name }}</a>
                  <div class="text-xs text-ink-500">{{ __(\App\Modules\People\Models\Person::TYPES[$p->type]) }}{{ $p->employee_code ? ' · ' . $p->employee_code : '' }}{{ $p->status === 'on_leave' && ! isset($onLeave[$p->id]) ? ' · ' . __('On leave') : '' }}</div>
                  @if (isset($onLeave[$p->id]))<span class="pill mt-0.5 bg-amber-50 text-partial">{{ __('On leave till :date', ['date' => $onLeave[$p->id]->to_on->format('d M')]) }}</span>@endif
                </div>
              </div>
            </td>
            <td class="nowrap text-sm">@if ($p->mobile)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $p->mobile) }}" class="hover:text-brand-700">{{ $p->mobile }}</a>@endif</td>
            <td>
              @if (isset($chip[$state]))
                <span class="pill {{ $chip[$state][0] }}">{{ $chip[$state][1] ?? trans_choice('Expires in :count day|Expires in :count days', now()->startOfDay()->diffInDays($p->licence_expiry)) }}</span>
              @endif
            </td>
            @if ($papers)
              @php($h = $papers[$p->id] ?? null)
              <td>
                @if ($h)
                  <a href="{{ route('documents.owner', ['person', $p->id]) }}" class="pill {{ $paperTones[$h['state']] }}">{{ $h['state'] === 'green' ? __('All in order') : collect([
                    $h['expired'] ? trans_choice(':count expired|:count expired', $h['expired']) : null,
                    $h['missing'] ? trans_choice(':count missing|:count missing', $h['missing']) : null,
                    $h['due'] ? trans_choice(':count due soon|:count due soon', $h['due']) : null,
                  ])->filter()->join(' · ') }}</a>
                @endif
              </td>
            @endif
            <td class="docno font-mono text-xs">{{ $p->defaultTruck?->reg_no }}</td>
            <td>@if ($p->company_user_id)<span class="pill bg-brand-50 text-brand-700"><x-icon name="phone" class="size-3" /> {{ __('Linked') }}</span>@endif</td>
            <td class="text-end">@if ($p->mobile)<a href="https://wa.me/?text={{ rawurlencode($p->name . ' — ' . __(\App\Modules\People\Models\Person::TYPES[$p->type]) . ' — ' . $p->mobile) }}" class="btn-ghost px-2 py-1" target="_blank" rel="noopener noreferrer" title="{{ __('Share contact') }}" aria-label="{{ __('Share :name’s contact on WhatsApp', ['name' => $p->name]) }}"><x-icon name="send" class="size-4" /></a>@endif</td>
          </tr>
        @empty
          <tr><td colspan="{{ $papers ? 7 : 6 }}" class="py-12 text-center text-ink-500">{{ $search !== '' ? __('Nobody matches.') : __('No people yet — add your first driver.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($people->hasPages())<div class="p-4">{{ $people->links() }}</div>@endif
</section>
@endsection
