{{-- The period of the truck's Trips, Fuel and Costs tabs (M39): this financial year unless chosen. --}}
<form method="GET" action="{{ route('trucks.show', $truck) }}" class="flex flex-wrap items-end gap-2">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div><label class="label" for="pd_from">{{ __('From date') }}</label><input id="pd_from" type="date" name="from" value="{{ $from->toDateString() }}" class="input py-2"></div>
  <div><label class="label" for="pd_to">{{ __('To date') }}</label><input id="pd_to" type="date" name="to" value="{{ $to->toDateString() }}" class="input py-2"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
</form>
