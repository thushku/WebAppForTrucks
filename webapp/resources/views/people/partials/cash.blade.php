{{-- The person's khata in short and quick cash (M27, 13-parity #23). $khata from PersonController::khata(). --}}
@php
  $m = fn ($v) => \App\Support\Money::company(abs($v));
  $b = $khata['buckets'];
  $direction = old('direction', 'received');
  $tripId = (string) old('trip_id', '');
  $today = \Carbon\CarbonImmutable::now(app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: 'UTC')->toDateString();
@endphp
<section id="cash" class="card p-6">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h3 class="font-semibold">{{ __('Khata') }}</h3>
    @if ($khata['ledger'])
      @canany(['ledger.view', 'accounts.view'])<a href="{{ route('ledgers.show', $khata['ledger']) }}" class="text-sm text-brand-700 hover:underline">{{ __('Full statement') }}</a>@endcanany
    @endif
  </div>
  <div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
    <div class="text-sm">
      <dl class="divide-y divide-ink-100">
        <div class="flex items-start justify-between gap-4 py-2"><dt>{{ __('Trip cash') }}<div class="text-xs text-ink-500">{{ $b['trip_cash'] >= 0 ? __('Company money with them on trips') : __('Spent from their own pocket on trips') }}</div></dt><dd class="money shrink-0">{{ $m($b['trip_cash']) }}</dd></div>
        <div class="flex items-start justify-between gap-4 py-2"><dt>{{ __('Earnings') }}<div class="text-xs text-ink-500">{{ $b['earnings'] <= 0 ? __('Pay, bata and spending we owe them') : __('Paid more than they earned') }}</div></dt><dd class="money shrink-0">{{ $m($b['earnings']) }}</dd></div>
        @if ($b['loan'])
          <div class="flex items-start justify-between gap-4 py-2"><dt>{{ __('Advance against pay') }}<div class="text-xs text-ink-500">{{ __('Recovered from the payslip') }}</div></dt><dd class="money shrink-0">{{ $m($b['loan']) }}</dd></div>
        @endif
      </dl>
      <p class="mt-2 rounded-xl bg-ink-50 px-3 py-2 font-semibold">{{ \App\Http\Controllers\Accounts\LedgerController::words('person', $person->name, $b['net']) }}</p>
    </div>

    @if ($khata['gives'])
      <form method="POST" action="{{ route('people.cash', $person) }}" class="grid content-start gap-3" data-show-if-form novalidate>
        @csrf
        <fieldset class="grid grid-cols-2 gap-2">
          <legend class="sr-only">{{ __('Which way') }}</legend>
          @foreach (\App\Modules\Accounts\Services\CrewCash::DIRECTIONS as $v => $label)
            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-ink-200 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
              <input type="radio" name="direction" value="{{ $v }}" class="size-4" @checked($direction === $v)> {{ __($label) }}
            </label>
          @endforeach
        </fieldset>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="label" for="cash_amount">{{ __('Amount') }}</label><input id="cash_amount" name="amount" type="number" min="1" step="0.01" class="input" value="{{ old('amount') }}" required></div>
          <div><label class="label" for="cash_on">{{ __('Date') }}</label><input id="cash_on" name="on" type="date" class="input" value="{{ old('on', $today) }}" max="{{ $today }}" required></div>
        </div>
        <div><label class="label" for="cash_remark">{{ __('What for') }}</label><input id="cash_remark" name="remark" class="input" maxlength="200" value="{{ old('remark') }}" placeholder="{{ __('Diesel top-up at Panvel, tyre puncture…') }}" required></div>
        <div><label class="label" for="cash_trip">{{ __('Trip') }}</label>
          <select id="cash_trip" name="trip_id" class="input">
            <option value="">{{ __('No trip — their personal account') }}</option>
            @foreach ($khata['trips'] as $trip)<option value="{{ $trip->id }}" @selected($tripId === (string) $trip->id)>{{ $trip->label }} · {{ $trip->trip_date?->format('d M') }} · {{ __(\App\Modules\Trips\Models\Trip::STATUSES[$trip->status]) }}</option>@endforeach
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2 {{ $direction === 'received' ? '' : 'hidden' }}" data-show-if="direction=received">
          <div><label class="label" for="cash_mode">{{ __('Given as') }}</label>
            <select id="cash_mode" name="mode" class="input">@foreach (\App\Modules\Accounts\Services\CrewCash::MODES as $v => $label)<option value="{{ $v }}" @selected(old('mode', 'cash') === $v)>{{ __($label) }}</option>@endforeach</select></div>
          <div class="{{ $tripId === '' ? '' : 'hidden' }}" data-show-if="direction=received trip_id="><label class="label" for="cash_purpose">{{ __('It is') }}</label>
            <select id="cash_purpose" name="purpose" class="input">@foreach (\App\Modules\Accounts\Services\CrewCash::PURPOSES as $v => $label)<option value="{{ $v }}" @selected(old('purpose', 'advance') === $v)>{{ __($label) }}</option>@endforeach</select></div>
        </div>
        <div class="{{ $direction === 'spent' ? '' : 'hidden' }}" data-show-if="direction=spent"><label class="label" for="cash_category">{{ __('Spent on') }}</label>
          <select id="cash_category" name="category" class="input"><option value="">{{ __('Choose') }}</option>@foreach ($khata['categories'] as $code => $name)<option value="{{ $code }}" @selected(old('category') === $code)>{{ $name }}</option>@endforeach</select>
          <p class="mt-1 text-xs text-ink-500">{{ __('We owe it to them with their pay. Spending on the road that they entered in the crew app is approved there instead.') }}</p>
        </div>
        <div><label class="label" for="cash_reference">{{ __('Reference (optional for cash)') }}</label><input id="cash_reference" name="reference" class="input" maxlength="60" value="{{ old('reference') }}" placeholder="{{ __('UTR / UPI ref') }}"></div>
        @foreach (['direction', 'amount', 'on', 'remark', 'trip_id', 'mode', 'purpose', 'category', 'reference'] as $f)@error($f)<span class="error">{{ $message }}</span>@enderror @endforeach
        <div><button class="btn-primary"><x-icon name="wallet" /> {{ __('Record in the khata') }}</button></div>
      </form>
    @endif
  </div>
</section>
