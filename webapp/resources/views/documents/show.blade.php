@extends('layouts.app')
@section('title', $compliance->typeName($document->document_type) . ' — ' . $title)
@section('content')
@php($d = $document)
<div class="flex flex-wrap items-center justify-between gap-3">
  <a href="{{ $ownerUrl }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ $title }}</a>
  @can('document.manage')
    <div class="flex gap-2">
      <a href="{{ route('documents.edit', $d) }}" class="btn-secondary">{{ __('Correct') }}</a>
      @if ($d->expires_on)<a href="{{ route('documents.renew.form', $d) }}" class="btn-primary"><x-icon name="rotate" /> {{ __('Renew') }}</a>@endif
    </div>
  @endcan
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold">{{ $compliance->typeName($d->document_type) }} <span class="text-sm font-normal text-ink-400">{{ __('version :v', ['v' => $d->version]) }}</span></h2>
          <p class="docno mt-1 font-mono text-sm">{{ $d->number ?: '—' }}</p>
        </div>
        @include('documents._state', ['document' => $d])
      </div>
      <dl class="mt-4 grid grid-cols-[9rem_1fr] gap-x-4 gap-y-2 text-sm">
        @if ($d->authority)<dt class="text-ink-500">{{ __('Issued by') }}</dt><dd>{{ $d->authority }}</dd>@endif
        @if ($d->issued_on)<dt class="text-ink-500">{{ __('Issued on') }}</dt><dd>{{ $d->issued_on->format('d M Y') }}</dd>@endif
        @if ($d->valid_from || $d->expires_on)<dt class="text-ink-500">{{ __('Valid') }}</dt><dd>{{ $d->valid_from?->format('d M Y') ?? '…' }} – {{ $d->expires_on?->format('d M Y') ?? __('no end') }}</dd>@endif
        @if ($d->amount)<dt class="text-ink-500">{{ __('Cost') }}</dt><dd class="money">{{ \App\Support\Money::company($d->amount) }}</dd>@endif
        @if ($d->vendor)<dt class="text-ink-500">{{ __('Insurer / agent') }}</dt><dd>{{ $d->vendor->name }}</dd>@endif
        @if ($d->original_location)<dt class="text-ink-500">{{ __('Original kept at') }}</dt><dd>{{ $d->original_location }}</dd>@endif
        @if ($d->snoozed_until && $d->snoozed_until->isFuture())<dt class="text-ink-500">{{ __('Reminders') }}</dt><dd>{{ __('Paused until :date — :reason', ['date' => $d->snoozed_until->format('d M Y'), 'reason' => $d->snooze_reason]) }}</dd>@endif
      </dl>
      @if ($d->notes)<p class="mt-4 whitespace-pre-line rounded-xl bg-ink-50 p-3 text-sm">{{ $d->notes }}</p>@endif
    </section>

    <section class="card p-6">
      <h3 class="font-semibold">{{ __('Files') }}</h3>
      @php($current = $d->currentFiles())
      @if ($current->isEmpty())
        <p class="mt-3 text-sm text-ink-500">{{ __('No file yet.') }} @can('document.manage')<a href="{{ route('documents.edit', $d) }}" class="font-semibold text-brand-700 hover:underline">{{ __('Add one') }}</a>@endcan</p>
      @else
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
          @foreach ($current as $f)
            <a href="{{ route('documents.file', $f) }}" target="_blank" rel="noopener" class="group block overflow-hidden rounded-xl border border-ink-100 hover:border-brand-300">
              @if ($f->isImage())
                <img src="{{ route('documents.file', $f) }}" alt="{{ $f->label }}" class="h-40 w-full bg-ink-50 object-contain" loading="lazy">
              @else
                <span class="grid h-40 place-items-center bg-ink-50 text-ink-500"><x-icon name="file-text" class="size-10" /></span>
              @endif
              <span class="block truncate px-3 py-2 text-xs text-ink-700">{{ $f->label }} · {{ number_format($f->bytes / 1024) }} KB</span>
            </a>
          @endforeach
        </div>
      @endif
    </section>

    @if ($d->versions->isNotEmpty())
      <section class="card p-6">
        <h3 class="font-semibold">{{ __('Earlier versions') }}</h3>
        <ul class="mt-3 divide-y divide-ink-100 text-sm">
          @foreach ($d->versions as $ver)
            <li class="py-2.5">
              <div class="flex flex-wrap items-center gap-2"><span class="font-medium">v{{ $ver->version }}</span><span class="docno font-mono text-xs">{{ $ver->number }}</span><span class="text-ink-500">{{ $ver->valid_from?->format('d M Y') ?? '…' }} – {{ $ver->expires_on?->format('d M Y') ?? '…' }}</span></div>
              <div class="text-xs text-ink-500">{{ __('Replaced :date by :who', ['date' => $ver->archived_at->format('d M Y'), 'who' => $ver->renewer?->name ?? __('someone')]) }}
                @foreach ($d->files->where('version', $ver->version) as $f) · <a href="{{ route('documents.file', $f) }}" target="_blank" rel="noopener" class="text-brand-700 hover:underline">{{ $f->label }}</a>@endforeach
              </div>
            </li>
          @endforeach
        </ul>
      </section>
    @endif
  </div>

  @can('document.manage')
    @if ($d->expires_on)
      <form method="POST" action="{{ route('documents.snooze', $d) }}" class="card h-fit space-y-3 p-6" novalidate>
        @csrf
        <h3 class="font-semibold">{{ __('Pause reminders') }}</h3>
        <p class="text-xs text-ink-500">{{ __('For example while the renewal is with the agent. Reminders start again on the date.') }}</p>
        <div><label class="label" for="sn_until">{{ __('Until') }}</label><input id="sn_until" name="until" type="date" class="input" value="{{ now()->addDays(7)->toDateString() }}">@error('until')<span class="error">{{ $message }}</span>@enderror</div>
        <div><label class="label" for="sn_reason">{{ __('Why') }}</label><input id="sn_reason" name="reason" class="input" maxlength="200" placeholder="{{ __('Renewal applied, waiting for the copy') }}">@error('reason')<span class="error">{{ $message }}</span>@enderror</div>
        <button class="btn-secondary w-full">{{ __('Pause') }}</button>
      </form>
    @endif
  @endcan
</div>
@endsection
