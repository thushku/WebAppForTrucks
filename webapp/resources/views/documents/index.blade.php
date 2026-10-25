@extends('layouts.app')
@section('title', __('Documents'))
@section('content')
@php
  $tiles = [
    'expired' => [__('Expired'), 'bg-red-50 text-overdue', 'alert'],
    'd7'      => [__('Within 7 days'), 'bg-amber-50 text-partial', 'clock'],
    'd15'     => [__('Within 15 days'), 'bg-yellow-50 text-yellow-800', 'clock'],
    'd30'     => [__('Within 30 days'), 'bg-sky-50 text-sky-700', 'calendar'],
    'missing' => [__('Missing'), 'bg-rose-50 text-rose-700', 'file-text'],
  ];
@endphp
<div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
  @foreach ($tiles as $key => [$label, $tone, $icon])
    <a href="{{ route('documents.index', ['show' => $key]) }}" class="stat block hover:border-brand-300 {{ $filter === $key ? 'ring-2 ring-brand-300' : '' }}">
      <div class="flex items-center justify-between"><span class="stat-label">{{ $label }}</span><span class="nav-tile {{ $tone }}"><x-icon :name="$icon" class="size-4" /></span></div>
      <div class="stat-value">{{ $counts[$key] }}</div>
    </a>
  @endforeach
</div>

<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5">
    @foreach (['attention' => __('Needs attention'), 'all' => __('All documents')] as $key => $label)
      <a href="{{ route('documents.index', ['show' => $key, 'owner' => $owner ?: null]) }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $filter === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }}</a>
    @endforeach
    @if ($filter !== 'missing')
      <span class="mx-1 h-8 w-px bg-ink-100" aria-hidden="true"></span>
      @foreach (['' => __('Every owner')] + collect(\App\Modules\Documents\Models\Document::OWNER_TYPES)->map(fn ($l) => __($l))->all() as $key => $label)
        <a href="{{ route('documents.index', ['show' => $filter, 'owner' => $key ?: null]) }}" class="rounded-xl border px-3 py-2 text-sm {{ $owner === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }}</a>
      @endforeach
    @endif
  </div>
  <div class="flex gap-2">
    <a href="{{ route('documents.owner', 'company') }}" class="btn-secondary"><x-icon name="building" /> {{ __('Company papers') }}</a>
    <a href="{{ route('documents.required') }}" class="btn-secondary"><x-icon name="check" /> {{ __('Required papers') }}</a>
  </div>
</div>

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    @if ($filter === 'missing')
      <table class="table">
        <thead><tr><th>{{ __('Owner') }}</th><th>{{ __('Missing paper') }}</th><th></th></tr></thead>
        <tbody>
          @forelse ($missing as $m)
            <tr>
              <td><a href="{{ route('documents.owner', [$m['owner_type'], $m['owner_id']]) }}" class="font-medium hover:text-brand-700">{{ $m['owner'] }}</a> <span class="text-xs text-ink-400">{{ __(\App\Modules\Documents\Models\Document::OWNER_TYPES[$m['owner_type']]) }}</span></td>
              <td>{{ $compliance->typeName($m['type']) }}</td>
              <td class="text-end">@can('document.manage')<a href="{{ route('documents.create', [$m['owner_type'], $m['owner_id'], 'type' => $m['type']]) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Add') }}</a>@endcan</td>
            </tr>
          @empty
            <tr><td colspan="3" class="py-10 text-center text-ink-500">{{ __('Nothing missing. Set the papers every truck and person must have under “Required papers”.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    @else
      <table class="table">
        <thead><tr><th>{{ __('Owner') }}</th><th>{{ __('Paper') }}</th><th>{{ __('Number') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Files') }}</th></tr></thead>
        <tbody>
          @forelse ($documents as $d)
            <tr>
              <td><a href="{{ route('documents.owner', [$d->owner_type, $d->owner_id]) }}" class="font-medium hover:text-brand-700">{{ $labels[$d->owner_type . ':' . $d->owner_id] ?? '?' }}</a> <span class="text-xs text-ink-400">{{ __(\App\Modules\Documents\Models\Document::OWNER_TYPES[$d->owner_type]) }}</span></td>
              <td><a href="{{ route('documents.show', $d) }}" class="hover:text-brand-700">{{ $compliance->typeName($d->document_type) }}</a>@if ($d->snoozed_until && $d->snoozed_until->isFuture()) <span class="text-xs text-ink-400">({{ __('snoozed') }})</span>@endif</td>
              <td class="docno font-mono text-xs">{{ $d->number }}</td>
              <td>@include('documents._state', ['document' => $d])</td>
              <td>@if ($d->files_count)<span class="text-sm">{{ $d->files_count }}</span>@else<span class="pill bg-ink-100 text-ink-500">{{ __('File missing') }}</span>@endif</td>
            </tr>
          @empty
            <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ $filter === 'attention' ? __('Nothing expires in the next 30 days.') : __('No documents yet. Add them from a truck, a person, a party or the company.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    @endif
  </div>
  @if ($filter !== 'missing' && $documents->hasPages())<div class="p-4">{{ $documents->links() }}</div>@endif
</section>
@endsection
