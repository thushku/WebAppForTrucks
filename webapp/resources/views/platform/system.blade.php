@extends('platform.layout')
@section('title', 'System')
@section('content')
<h1 class="font-display text-2xl font-semibold">{{ __('Storage, data and announcement') }}</h1>

<div class="grid gap-6 lg:grid-cols-2">
  <form method="POST" action="{{ route('platform.system.announce') }}" class="card space-y-3 p-6">
    @csrf @method('PUT')
    <h2 class="font-display text-lg font-semibold">{{ __('Announcement') }}</h2>
    <p class="text-sm text-ink-500">{{ __('Shown at the top of every company’s screens until removed — for planned maintenance or a new feature. Plain text.') }}</p>
    <textarea name="announcement" rows="3" maxlength="300" class="input" aria-label="{{ __('Announcement') }}">{{ old('announcement', $announcement) }}</textarea>
    <div class="flex gap-2"><button class="btn-primary">{{ __('Save') }}</button>@if ($announcement)<button name="announcement" value="" class="btn-secondary">{{ __('Remove') }}</button>@endif</div>
  </form>

  <div class="card space-y-4 p-6">
    <h2 class="font-display text-lg font-semibold">{{ __('Database export') }}</h2>
    <p class="text-sm text-ink-500">{{ __('The whole database (:db) as a compressed SQL file, made on the spot and never kept on the server. Restore it with the mysql command on another server. Every export is in the log.', ['db' => $database]) }}</p>
    @if ($dump)
      <form method="POST" action="{{ route('platform.system.export') }}" data-confirm="{{ __('Download every company’s data? Keep the file safe — it holds bank details and personal data.') }}">@csrf<button class="btn-primary"><x-icon name="download" /> {{ __('Download the database') }}</button></form>
    @else
      <p class="error">{{ __('mysqldump is not installed on this server.') }}</p>
    @endif
    <h2 class="pt-2 font-display text-lg font-semibold">{{ __('Files no row points to') }}</h2>
    <p class="text-sm text-ink-500">{{ trans_choice(':count file on disk belongs to no document, photo or logo (left by a failed upload or a deleted company).|:count files on disk belong to no document, photo or logo (left by failed uploads or deleted companies).', $orphans) }}</p>
    <form method="POST" action="{{ route('platform.system.prune') }}" data-confirm="{{ __('Remove these files for good?') }}">@csrf<button class="btn-secondary" @disabled(! $orphans)>{{ __('Remove them') }}</button></form>
  </div>
</div>

@if ($retained->isNotEmpty())
  <section class="card overflow-x-auto">
    <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Copies kept for the law') }}</h2>
    <p class="px-5 pt-3 text-sm text-ink-500">{{ __('Deleted companies: one encrypted ZIP each in storage/app/retention, removed by itself on the date shown. Its password comes from the app key and the company (CompanyDeletion::password).') }}</p>
    <table class="table">
      <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Deleted') }}</th><th>{{ __('Kept until') }}</th></tr></thead>
      <tbody>@foreach ($retained as $c)<tr><td><a href="{{ route('platform.companies.show', $c) }}" class="text-brand-700 hover:underline">{{ $c->name }}</a></td><td>{{ $c->deleted_at?->format('d M Y') }}</td><td>{{ $c->retention_until?->format('d M Y') }}</td></tr>@endforeach</tbody>
    </table>
  </section>
@endif

<section class="card overflow-x-auto">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Storage by company') }}</h2>
  <table class="table">
    <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Files') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end">{{ __('Allowed') }}</th></tr></thead>
    <tbody>
      @forelse ($companies as $row)
        @php($over = $row->company->plan?->storage_mb && $row->mb > $row->company->plan->storage_mb)
        <tr>
          <td class="font-medium"><a href="{{ route('platform.companies.show', $row->company) }}" class="text-brand-700 hover:underline">{{ $row->company->name }}</a></td>
          <td>{{ $row->company->plan?->name ?? __('Trial') }}</td>
          <td class="text-end">{{ number_format($row->files) }}</td>
          <td class="text-end {{ $over ? 'font-semibold text-overdue' : '' }}">{{ number_format($row->mb, 1) }} MB @if ($over)<span class="sr-only">{{ __('over the plan') }}</span>@endif</td>
          <td class="text-end">{{ $row->company->plan?->storage_mb ? number_format($row->company->plan->storage_mb) . ' MB' : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-ink-500">{{ __('No companies yet.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
@endsection
