@extends('layouts.app')
@section('title', match ($mode) { 'renew' => __('Renew'), 'edit' => __('Correct'), default => __('Add a paper') } . ' — ' . $title)
@section('content')
@php
  $d = $document;
  $action = match ($mode) {
    'renew'  => route('documents.renew', $d),
    'edit'   => route('documents.update', $d),
    default  => route('documents.store', [$d->owner_type, $d->owner_id]),
  };
  // A renewal starts empty except for who issues it.
  $v = fn ($field) => $mode === 'renew' && ! in_array($field, ['authority', 'vendor_party_id', 'original_location'], true) ? old($field) : old($field, $d->$field instanceof \Carbon\CarbonInterface ? $d->$field->toDateString() : $d->$field);
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mx-auto max-w-3xl space-y-6" novalidate>
  @csrf
  @if ($mode === 'edit') @method('PUT') @endif
  <section class="card p-6">
    <p class="text-sm text-ink-500">{{ $title }}</p>
    <h2 class="text-xl font-semibold">
      @if ($mode === 'renew') {{ __('Renew :type', ['type' => $types[$d->document_type] ?? $d->document_type]) }}
      @elseif ($mode === 'edit') {{ __('Correct :type', ['type' => $types[$d->document_type] ?? $d->document_type]) }}
      @else {{ __('Add a paper') }} @endif
    </h2>
    @if ($mode === 'renew')<p class="mt-1 text-sm text-ink-500">{{ __('The current version (until :date) is kept read-only in the history.', ['date' => $d->expires_on?->format('d M Y')]) }}</p>@endif

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
      @if ($mode === 'create')
        <div class="sm:col-span-2">
          <label class="label" for="document_type">{{ __('Paper') }}</label>
          <select id="document_type" name="document_type" class="input" required>
            <option value="">—</option>
            @foreach ($types as $code => $name)<option value="{{ $code }}" @selected(old('document_type', $d->document_type) === $code)>{{ $name }}</option>@endforeach
          </select>
          @error('document_type')<span class="error">{{ $message }}</span>@enderror
        </div>
      @else
        <input type="hidden" name="document_type" value="{{ $d->document_type }}">
      @endif
      <div><label class="label" for="number">{{ __('Number') }}</label><input id="number" name="number" class="input font-mono" value="{{ $v('number') }}" maxlength="60"></div>
      <div><label class="label" for="authority">{{ __('Issued by') }}</label><input id="authority" name="authority" class="input" value="{{ $v('authority') }}" maxlength="120" placeholder="{{ __('RTO Ernakulam, New India Assurance…') }}"></div>
      <div><label class="label" for="issued_on">{{ __('Issued on') }}</label><input id="issued_on" name="issued_on" type="date" class="input" value="{{ $v('issued_on') }}">@error('issued_on')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="valid_from">{{ __('Valid from') }}</label><input id="valid_from" name="valid_from" type="date" class="input" value="{{ $v('valid_from') }}"></div>
      <div>
        <label class="label" for="expires_on">{{ __('Valid until') }}</label>
        <input id="expires_on" name="expires_on" type="date" class="input" value="{{ $v('expires_on') }}" @required($mode === 'renew')>
        <span class="hint">{{ __('Leave empty for papers that do not expire.') }}</span>
        @error('expires_on')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div><label class="label" for="amount">{{ __('Cost') }}</label><input id="amount" name="amount" type="number" min="0" step="0.01" class="input" value="{{ $v('amount') }}"></div>
      <div>
        <label class="label" for="vendor_party_id">{{ __('Insurer or agent') }}</label>
        <select id="vendor_party_id" name="vendor_party_id" class="input"><option value="">—</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}" @selected((int) $v('vendor_party_id') === $id)>{{ $name }}</option>@endforeach</select>
      </div>
      <div><label class="label" for="original_location">{{ __('Original kept at') }}</label><input id="original_location" name="original_location" class="input" value="{{ $v('original_location') }}" maxlength="120" placeholder="{{ __('Office file 3 / in the truck') }}"></div>
      <div class="sm:col-span-2"><label class="label" for="notes">{{ __('Notes') }}</label><textarea id="notes" name="notes" rows="2" class="input" maxlength="2000">{{ $v('notes') }}</textarea></div>
      <div class="sm:col-span-2">
        <label class="label" for="files">{{ $mode === 'edit' ? __('Add files') : __('Files') }}</label>
        <input id="files" name="files[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-sm text-ink-700 file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-700">
        <span class="hint">{{ __('Photos or scans (front, back, pages) or one PDF up to 2 MB. Photos are made smaller automatically. You can add the file later.') }}</span>
        @error('files')<span class="error">{{ $message }}</span>@enderror
        @error('files.*')<span class="error">{{ $message }}</span>@enderror
      </div>
    </div>
  </section>
  <div class="flex justify-end gap-2">
    <a href="{{ $d->exists ? route('documents.show', $d) : route('documents.owner', [$d->owner_type, $d->owner_id]) }}" class="btn-ghost">{{ __('Cancel') }}</a>
    <button type="submit" class="btn-primary"><x-icon name="check" /> {{ $mode === 'renew' ? __('Renew') : __('Save') }}</button>
  </div>
</form>
@endsection
