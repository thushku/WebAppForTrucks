@extends('layouts.app')
@section('title', $quotation->exists ? $quotation->label : __('New quotation'))
@section('content')
@php
  $q = $quotation;
  $fields = array_merge(\App\Modules\Invoicing\Models\QuotationLine::CONTRACT_FIELDS, ['note']);
  $lines = old('lines', $q->exists ? $q->lines->map(fn ($l) => $l->only($fields))->all() : []);
  $lines[] = [];
  $date = fn ($v) => $v instanceof \Carbon\CarbonInterface ? $v->toDateString() : $v;
@endphp
<div class="flex flex-wrap items-center gap-3">
  <a href="{{ $q->exists ? route('quotations.show', $q) : route('quotations.index') }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ $q->exists ? $q->label : __('Quotations') }}</a>
  @error('quotation')<span class="error">{{ $message }}</span>@enderror
</div>

<form method="POST" action="{{ $q->exists ? route('quotations.update', $q) : route('quotations.store') }}" class="space-y-6" novalidate>
  @csrf
  @if ($q->exists) @method('PUT') @endif
  <section class="card p-6">
    <div class="grid gap-4 sm:grid-cols-4">
      <div class="sm:col-span-2"><label class="label" for="qt_party">{{ __('Client') }}</label>
        <select id="qt_party" name="party_id" class="input" required><option value="">—</option>@foreach ($clients as $p)<option value="{{ $p->id }}" @selected((int) old('party_id', $q->party_id) === $p->id)>{{ $p->name }}</option>@endforeach</select>
        @error('party_id')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="qt_date">{{ __('Date') }}</label><input id="qt_date" name="quote_date" type="date" class="input" value="{{ old('quote_date', $date($q->quote_date)) }}" required>@error('quote_date')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="qt_valid">{{ __('Valid until') }}</label><input id="qt_valid" name="valid_until" type="date" class="input" value="{{ old('valid_until', $date($q->valid_until)) }}">@error('valid_until')<span class="error">{{ $message }}</span>@enderror</div>
      <div class="sm:col-span-4"><label class="label" for="qt_subject">{{ __('Subject') }} <span class="text-ink-400">({{ __('optional') }})</span></label><input id="qt_subject" name="subject" class="input" value="{{ old('subject', $q->subject) }}" maxlength="150" placeholder="{{ __('Cement from Kochi to Mumbai and Pune, November') }}"></div>
      <div class="sm:col-span-2"><label class="label" for="qt_terms">{{ __('Terms (printed)') }}</label><textarea id="qt_terms" name="terms" rows="4" class="input" maxlength="3000">{{ old('terms', $q->terms) }}</textarea></div>
      <div class="sm:col-span-2"><label class="label" for="qt_notes">{{ __('Office note (not printed)') }}</label><textarea id="qt_notes" name="notes" rows="4" class="input" maxlength="3000">{{ old('notes', $q->notes) }}</textarea></div>
    </div>
  </section>

  <section class="card overflow-hidden">
    <div class="flex items-center justify-between p-6 pb-3">
      <h2 class="text-lg font-semibold">{{ __('Routes and rates') }}</h2>
      @if ($places->isEmpty())<a href="{{ route('settings.places') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Add places first') }}</a>@endif
    </div>
    @foreach ($errors->getMessages() as $key => $msgs)
      @if (str_starts_with($key, 'lines'))<p class="error px-6">{{ $msgs[0] }}</p>@endif
    @endforeach
    <div class="overflow-x-auto">
      <table class="table min-w-[1500px]" data-rate-lines>
        <thead><tr>
          <th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Material') }}</th><th>{{ __('Truck type') }}</th><th>{{ __('Basis') }}</th>
          <th>{{ __('Rate') }}</th><th>{{ __('Min. MT') }}</th><th>{{ __('Free hrs') }}</th><th>{{ __('Detention/day') }}</th><th>{{ __('Loading') }}</th><th>{{ __('Unloading') }}</th><th>{{ __('Note') }}</th>
        </tr></thead>
        <tbody>
          @foreach ($lines as $i => $line)
            @include('quotations._line', ['i' => $i, 'line' => $line])
          @endforeach
        </tbody>
      </table>
      <template data-rate-line-template>@include('quotations._line', ['i' => '__N__', 'line' => []])</template>
    </div>
    <div class="p-4"><button type="button" class="btn-ghost text-sm" data-add-rate-line><x-icon name="plus" /> {{ __('Add a row') }}</button></div>
  </section>

  <div class="flex justify-end"><button type="submit" class="btn-primary"><x-icon name="check" /> {{ __('Save draft') }}</button></div>
</form>
@endsection
