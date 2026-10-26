{{-- The period of the person's Trips and Driving hours tabs (M41): this financial year unless chosen. --}}
<form method="GET" action="{{ route('people.show', $person) }}" class="flex flex-wrap items-end gap-2">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div><label class="label" for="pp_from">{{ __('From date') }}</label><input id="pp_from" type="date" name="from" value="{{ $from->toDateString() }}" class="input py-2"></div>
  <div><label class="label" for="pp_to">{{ __('To date') }}</label><input id="pp_to" type="date" name="to" value="{{ $to->toDateString() }}" class="input py-2"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
</form>
