@extends('layouts.app')
@section('title', __('Documents — :owner', ['owner' => $title]))
@section('content')
@php
  $back = match ($ownerType) {
    'truck' => route('trucks.show', $ownerId), 'person' => route('people.show', $ownerId), 'party' => route('parties.show', $ownerId), default => route('documents.index'),
  };
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <a href="{{ $back }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ $title }}</a>
  @if (session('share_link'))
    <div class="flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50 px-3 py-2 text-sm" role="status">
      <span>{{ __('Share link:') }}</span><input class="input w-80 py-1.5 font-mono text-xs" value="{{ session('share_link') }}" readonly aria-label="{{ __('Share link') }}" data-select-on-focus>
      <a class="btn-secondary px-3 py-1.5 text-xs" href="https://wa.me/?text={{ rawurlencode(session('share_link')) }}" target="_blank" rel="noopener noreferrer">{{ __('WhatsApp') }}</a>
    </div>
  @endif
</div>

@if ($missing)
  <section class="flex flex-wrap gap-2">
    @foreach ($missing as $code)
      <a href="{{ route('documents.create', [$ownerType, $ownerId, 'type' => $code]) }}" class="flex items-center gap-2 rounded-xl border border-dashed border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-700 hover:bg-rose-100">
        <x-icon name="alert" class="size-4" /> {{ __('Missing: :type', ['type' => $compliance->typeName($code)]) }}
      </a>
    @endforeach
  </section>
@endif

<form method="POST" action="{{ route('documents.shares.store') }}" class="card overflow-hidden" novalidate>
  @csrf
  <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
    <h2 class="text-lg font-semibold">{{ __('Papers of :owner', ['owner' => $title]) }}</h2>
    @can('document.manage')
      <div class="flex flex-wrap gap-2">
        <select class="input w-56 py-2" aria-label="{{ __('Add a paper') }}" data-go-select>
          <option value="">{{ __('Add a paper…') }}</option>
          @foreach ($types as $code => $t)<option value="{{ route('documents.create', [$ownerType, $ownerId, 'type' => $code]) }}">{{ $t['name'] }}</option>@endforeach
        </select>
      </div>
    @endcan
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr>@can('document.manage')<th class="w-10"><span class="sr-only">{{ __('Share') }}</span></th>@endcan<th>{{ __('Paper') }}</th><th>{{ __('Number') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Files') }}</th><th></th></tr></thead>
      <tbody>
        @forelse ($documents as $d)
          <tr>
            @can('document.manage')<td>@unless ($compliance->isSensitive($d->document_type))<input type="checkbox" name="documents[]" value="{{ $d->id }}" class="size-4 rounded border-ink-300" aria-label="{{ __('Share :type', ['type' => $compliance->typeName($d->document_type)]) }}">@endunless</td>@endcan
            <td><a href="{{ route('documents.show', $d) }}" class="font-medium hover:text-brand-700">{{ $compliance->typeName($d->document_type) }}</a> @if ($d->version > 1)<span class="text-xs text-ink-400">v{{ $d->version }}</span>@endif</td>
            <td class="docno font-mono text-xs">{{ $d->number }}</td>
            <td>@include('documents._state', ['document' => $d])</td>
            <td>{{ $d->currentFiles()->count() ?: '' }}@unless ($d->currentFiles()->count())<span class="pill bg-ink-100 text-ink-500">{{ __('File missing') }}</span>@endunless</td>
            <td class="text-end">@can('document.manage')@if ($d->expires_on)<a href="{{ route('documents.renew.form', $d) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Renew') }}</a>@endif @endcan</td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-10 text-center text-ink-500">{{ __('No papers yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @can('document.manage')
    @if ($documents->isNotEmpty())
      <div class="flex flex-wrap items-end gap-3 border-t border-ink-100 p-4">
        <div class="min-w-[14rem] flex-1"><label class="label" for="sh_purpose">{{ __('Share the ticked papers for') }}</label><input id="sh_purpose" name="purpose" class="input" maxlength="150" placeholder="{{ __('ACC plant gate entry') }}">@error('purpose')<span class="error">{{ $message }}</span>@enderror</div>
        <div><label class="label" for="sh_days">{{ __('Link works for') }}</label><select id="sh_days" name="days" class="input"><option value="1">{{ __('1 day') }}</option><option value="3">{{ __('3 days') }}</option><option value="7" selected>{{ __('7 days') }}</option><option value="30">{{ __('30 days') }}</option></select></div>
        <button class="btn-secondary"><x-icon name="send" /> {{ __('Make share link') }}</button>
        @error('documents')<span class="error w-full">{{ __('Tick the papers to share.') }}</span>@enderror
      </div>
    @endif
  @endcan
</form>

@if ($shares->isNotEmpty())
  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Links that still work') }}</h3>
    <ul class="mt-3 divide-y divide-ink-100 text-sm">
      @foreach ($shares as $s)
        <li class="flex flex-wrap items-center gap-3 py-2.5">
          <span class="flex-1">{{ $s->purpose }} <span class="text-xs text-ink-500">· {{ __('until :date', ['date' => $s->expires_at->format('d M, H:i')]) }} · {{ trans_choice('opened :count time|opened :count times', $s->opened_count) }}</span></span>
          @can('document.manage')<form method="POST" action="{{ route('documents.shares.revoke', $s) }}">@csrf<button class="text-xs font-semibold text-overdue hover:underline">{{ __('End link') }}</button></form>@endcan
        </li>
      @endforeach
    </ul>
  </section>
@endif
@endsection
