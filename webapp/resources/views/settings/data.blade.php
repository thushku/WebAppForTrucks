@extends('layouts.settings')
@section('title', __('Your data'))
@section('settings')
@php
  $tz = $company->timezone ?: 'UTC';
@endphp
@can('data.export')
  <section class="card space-y-3 p-6">
    <h2 class="text-lg font-semibold"><x-icon name="download" class="inline size-5 text-brand-700" /> {{ __('Download all your data') }}</h2>
    <p class="text-sm text-ink-600">{{ __('One ZIP file with everything: a spreadsheet file (CSV) for each kind of record — trips, entries, bills, receipts, ledgers, people, trucks, documents and the rest — and every uploaded file. Bank and ID numbers are readable in it, so keep it safe. Making it can take a minute for a large company.') }}</p>
    <a href="{{ route('settings.data.export') }}" class="btn-primary"><x-icon name="download" /> {{ __('Download the ZIP') }}</a>
    <p class="text-xs text-ink-500">{{ __('Every download is in the activity log. At most three an hour.') }}</p>
  </section>
@endcan

@can('company.delete')
  <section class="card space-y-4 border-red-200 p-6">
    <h2 class="text-lg font-semibold text-overdue"><x-icon name="trash" class="inline size-5" /> {{ __('Delete this company') }}</h2>
    @if ($due)
      <div class="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-900" role="status">
        <p class="font-semibold">{{ __(':company will be deleted on :when.', ['company' => $company->name, 'when' => $due->timezone($tz)->translatedFormat('j F Y, H:i')]) }}</p>
        <p class="mt-1">{{ __('Asked by :name: “:reason”. Until then everything works as usual.', ['name' => $requestedBy ?? '—', 'reason' => $company->deletion_reason]) }}</p>
      </div>
      <form method="POST" action="{{ route('company.deletion.cancel') }}">@csrf<button class="btn-primary">{{ __('Cancel the deletion') }}</button></form>
    @else
      <p class="text-sm text-ink-600">{{ __('Everything of :company is removed 72 hours after you ask: trips, bills, ledgers, people, documents and photos, and nobody can sign in to it any more. Until then you, or anyone allowed, can cancel. One encrypted copy is kept for the years tax law requires, then removed too. Download your data first if you want it.', ['company' => $company->name]) }}</p>
      <details>
        <summary class="btn-danger cursor-pointer">{{ __('I want to delete this company') }}</summary>
        <form method="POST" action="{{ route('company.deletion.request') }}" class="mt-4 grid gap-4 sm:max-w-md">
          @csrf
          <div><label class="label" for="confirm">{{ __('Type the company’s name: :name', ['name' => $company->name]) }}</label><input id="confirm" name="confirm" class="input" autocomplete="off" required>@error('confirm')<span class="error">{{ $message }}</span>@enderror</div>
          <div><label class="label" for="password">{{ __('Your password') }}</label><input id="password" name="password" type="password" class="input" autocomplete="current-password" required>@error('password')<span class="error">{{ $message }}</span>@enderror</div>
          <div><label class="label" for="reason">{{ __('Why are you leaving?') }}</label><input id="reason" name="reason" class="input" maxlength="255" required>@error('reason')<span class="error">{{ $message }}</span>@enderror</div>
          <button class="btn-danger">{{ __('Delete in 72 hours') }}</button>
        </form>
      </details>
    @endif
  </section>
@endcan
@endsection
