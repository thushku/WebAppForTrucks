@extends('layouts.app')
@section('title', __('Journal voucher'))
@section('content')
@include('accounts._nav')
<form method="POST" action="{{ route('accounts.journal.store') }}" class="space-y-4" novalidate>@csrf
  <section class="card grid gap-4 p-6 sm:grid-cols-4">
    <h2 class="text-lg font-semibold sm:col-span-4">{{ __('Journal voucher') }}</h2>
    <p class="-mt-2 text-sm text-ink-500 sm:col-span-4">{{ __('For what the operations cannot know: capital brought in, a loan, bank charges, depreciation, a correction. Trips, bills, receipts and approved road entries post by themselves.') }}</p>
    <div><label class="label" for="j_date">{{ __('Date') }}</label><input id="j_date" type="date" name="voucher_date" value="{{ old('voucher_date', $today->toDateString()) }}" class="input" required>@error('voucher_date')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="j_ref">{{ __('Reference') }}</label><input id="j_ref" name="reference" value="{{ old('reference') }}" class="input" maxlength="60"></div>
    <div class="sm:col-span-2"><label class="label" for="j_narr">{{ __('Narration') }}</label><input id="j_narr" name="narration" value="{{ old('narration') }}" class="input" maxlength="500" required placeholder="{{ __('e.g. Bank charges for October') }}">@error('narration')<span class="error">{{ $message }}</span>@enderror</div>
  </section>
  <section class="card overflow-hidden">
    <table class="table">
      <thead><tr><th>{{ __('Ledger') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th>{{ __('Truck') }}</th><th>{{ __('Note') }}</th><th></th></tr></thead>
      <tbody id="journal-lines">
        @foreach (old('lines', [[], []]) as $i => $row)
          @include('accounts._journal-line', ['i' => $i, 'row' => $row])
        @endforeach
      </tbody>
    </table>
    <template id="journal-line-template">@include('accounts._journal-line', ['i' => '__N__', 'row' => []])</template>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-100 p-4">
      <button type="button" class="btn-ghost text-sm" data-add-row="#journal-lines" data-template="#journal-line-template"><x-icon name="plus" class="size-4" /> {{ __('Add a line') }}</button>
      @error('lines')<span class="error">{{ $message }}</span>@enderror
      @foreach ($errors->get('lines.*') as $messages)<span class="error">{{ $messages[0] }}</span>@endforeach
    </div>
  </section>
  <div class="text-end"><button class="btn-primary"><x-icon name="check" /> {{ __('Post the voucher') }}</button></div>
</form>
@endsection
