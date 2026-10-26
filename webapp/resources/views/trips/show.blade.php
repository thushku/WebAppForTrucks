@extends('layouts.app')
@section('title', $trip->trip_number ?? __('Draft trip'))
@section('content')
@php
  $t = $trip;
  $loads = $t->consignments;
  $live  = $loads->where('status', '!=', 'cancelled');
  $driver = $t->crew->firstWhere('role', 'driver');
  // Loads without an LR yet are called by their place in the list ("LR #2").
  $loadNo = $live->values()->pluck('id')->flip();
@endphp
<section class="card p-6">
  <div class="flex flex-wrap items-start gap-4">
    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-amber-50 text-amber-700"><x-icon name="route" class="size-6" /></span>
    <div class="min-w-0 flex-1">
      <h2 class="docno break-all font-mono text-xl font-semibold sm:text-2xl">{{ $t->trip_number ?? __('Draft trip') }}</h2>
      <p class="mt-0.5 text-sm text-ink-500">{{ $t->trip_date->format('d M Y') }} · {{ $t->from->name }} → {{ $t->to->name }}{{ $t->planned_km ? ' · ' . number_format($t->planned_km) . ' km' : '' }} · {{ __(\App\Modules\Trips\Models\Trip::LOAD_TYPES[$t->load_type]) }}{{ $t->trip_kind !== 'loaded' ? ' · ' . __(\App\Modules\Trips\Models\Trip::KINDS[$t->trip_kind]) : '' }}</p>
      <div class="mt-2 flex flex-wrap items-center gap-1.5">
        @include('trips._status', ['status' => $t->status])
        <span class="pill bg-blue-50 text-blue-700"><x-icon name="truck" class="size-3" /> {{ $t->vehicleLabel() }}</span>
        @if ($driver)<span class="pill bg-indigo-50 text-indigo-700">{{ $driver->person->name }}</span>@endif
        @if ($t->returnOf)<a href="{{ route('trips.show', $t->returnOf) }}" class="pill bg-ink-100 text-ink-700 hover:underline">{{ __('Return of :trip', ['trip' => $t->returnOf->label]) }}</a>@endif
        @if ($t->ended_early)<span class="pill bg-red-50 text-overdue">{{ __('Ended early') }}</span>@endif
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($t->isPlanning())
        @can('trip.create')<a href="{{ route('trips.edit', $t) }}" class="btn-secondary">{{ __('Edit') }}</a>@endcan
      @endif
      @can('trip.assign')
        @if (in_array($t->status, ['assigned', 'accepted'], true))
          <form method="POST" action="{{ route('trips.start', $t) }}" class="flex gap-2">@csrf
            <input name="start_odometer" type="number" class="input w-36 py-2" placeholder="{{ __('Start km') }}" aria-label="{{ __('Start odometer') }}">
            <button class="btn-primary">{{ __('Start trip') }}</button>
          </form>
        @elseif ($t->status === 'running')
          <form method="POST" action="{{ route('trips.end', $t) }}" class="flex flex-wrap items-center gap-2" data-confirm="{{ __('End the trip? Loads still on board are marked delivered.') }}">@csrf
            <input name="end_odometer" type="number" class="input w-36 py-2" placeholder="{{ __('End km') }}" aria-label="{{ __('End odometer') }}">
            <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="ended_early" value="1" class="size-4 rounded border-ink-300"> {{ __('Ended early') }}</label>
            <button class="btn-primary">{{ __('End trip') }}</button>
          </form>
        @endif
      @endcan
      @if ($t->status === 'settled')
        @can('trip.settle')
          <form method="POST" action="{{ route('trips.close', $t) }}" data-confirm="{{ __('Close the trip? The crew’s entries are locked; office entries can still be added.') }}">@csrf
            <button class="btn-primary"><x-icon name="lock" /> {{ __('Close trip') }}</button>
          </form>
        @endcan
      @endif
      @if (in_array($t->status, ['settled', 'closed'], true))
        @can('trip.reopen')
          <details class="relative">
            <summary class="btn-ghost cursor-pointer list-none">{{ __('Reopen') }}</summary>
            <form method="POST" action="{{ route('trips.reopen', $t) }}" class="card absolute end-0 z-10 mt-2 grid w-72 gap-2 p-4">@csrf
              <label class="label" for="reopen_reason">{{ __('Why is it reopened?') }}</label>
              <input id="reopen_reason" name="reason" class="input" maxlength="255" required>
              <p class="text-xs text-ink-500">{{ __('Its settlements are voided (kept) and done again. Logged.') }}</p>
              <button class="btn-danger">{{ __('Reopen trip') }}</button>
            </form>
          </details>
        @endcan
      @endif
    </div>
  </div>
  @foreach (['trip', 'start_odometer', 'end_odometer', 'override_reason', 'reason'] as $f)@error($f)<p class="error mt-2">{{ $message }}</p>@enderror @endforeach
  @if ($t->closed_at)<p class="mt-3 text-sm text-ink-500">{{ __('Closed :date. The crew’s entries are locked; office entries can still be added.', ['date' => $t->closed_at->format('d M Y')]) }}</p>@endif
  @if ($t->status === 'cancelled')<p class="mt-3 text-sm text-ink-500">{{ __('Cancelled :date: :reason', ['date' => $t->cancelled_at->format('d M Y'), 'reason' => $t->cancel_reason]) }}</p>@endif
  @if ($t->dispatch_override_reason)<p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-sm text-overdue">{{ __('Sent despite an expired paper: :reason', ['reason' => $t->dispatch_override_reason]) }}</p>@endif
</section>

{{-- Send to the crew: the dispatch check (blueprint 03 §4). --}}
@if ($t->isDraft())
  @can('trip.assign')
    <section class="card p-6">
      <h3 class="font-semibold">{{ __('Send to the crew') }}</h3>
      @if ($check['busy'])
        <ul class="mt-3 space-y-1 text-sm text-overdue">@foreach ($check['busy'] as $b)<li class="flex gap-2"><x-icon name="alert" class="size-4 shrink-0" /> {{ $b }}</li>@endforeach</ul>
      @endif
      @if ($check['papers'])
        <ul class="mt-3 space-y-1 text-sm text-overdue">
          @foreach ($check['papers'] as $p)<li class="flex gap-2"><x-icon name="alert" class="size-4 shrink-0" /> {{ $p['who'] }}: {{ $p['name'] }} — {{ $p['reason'] === 'missing' ? __('missing') : __('expired') }}</li>@endforeach
        </ul>
      @endif
      @if (! $check['busy'] && ! $check['papers'])<p class="mt-2 text-sm text-paid">{{ __('Truck and crew are free and their papers are in order.') }}</p>@endif
      @if (! $check['busy'])
        <form method="POST" action="{{ route('trips.assign', $t) }}" class="mt-4 flex flex-wrap items-end gap-3">
          @csrf
          @if ($check['papers'])
            <div class="min-w-[18rem] flex-1">
              <label class="label" for="override_reason">{{ __('Why it must go anyway (kept on the trip and in the audit log)') }}</label>
              <input id="override_reason" name="override_reason" class="input" maxlength="255" placeholder="{{ __('Renewal paid today, copy coming by evening') }}">
            </div>
          @endif
          <button class="btn-primary"><x-icon name="send" /> {{ __('Send to crew') }}</button>
        </form>
      @endif
    </section>
  @endcan
@endif

<div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_22rem]">
  <div class="min-w-0 space-y-6">
    {{-- Stops and how full the truck is between them. --}}
    <section class="card p-6">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold">{{ __('Route') }}</h3>
      </div>
      <ol class="mt-3 space-y-2">
        @foreach ($t->stops as $s)
          <li class="flex flex-wrap items-center gap-3 text-sm">
            <span class="grid size-7 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ $s->seq }}</span>
            <span class="font-medium">{{ $s->place->name }}</span>
            <span class="text-xs text-ink-500">{{ __(\App\Modules\Trips\Models\TripStop::KINDS[$s->kind]) }}</span>
            @php($here = $live->filter(fn ($c) => $c->pickup_stop_id === $s->id || $c->drop_stop_id === $s->id))
            @foreach ($here as $c)
              <span class="pill {{ $c->pickup_stop_id === $s->id ? 'bg-emerald-50 text-paid' : 'bg-amber-50 text-partial' }}">{{ $c->pickup_stop_id === $s->id ? __('Load') : __('Unload') }} {{ $c->lr_number ?? __('LR #:n', ['n' => ($loadNo[$c->id] ?? 0) + 1]) }}</span>
            @endforeach
            @if ($t->isPlanning() && ! $loop->first && ! $loop->last)
              @can('trip.create')<form method="POST" action="{{ route('trips.stops.destroy', [$t, $s]) }}" class="ms-auto">@csrf @method('DELETE')<button class="btn-ghost px-2 py-1" aria-label="{{ __('Remove stop') }}"><x-icon name="trash" class="size-4" /></button></form>@endcan
            @endif
          </li>
        @endforeach
      </ol>
      @error('stop')<p class="error mt-2">{{ $message }}</p>@enderror
      @if ($legs)
        <div class="mt-4 space-y-2">
          @foreach ($legs as $leg)
            <div>
              <div class="flex justify-between text-xs text-ink-500"><span>{{ $leg['from'] }} → {{ $leg['to'] }}</span><span>{{ rtrim(rtrim(number_format($leg['weight'], 3), '0'), '.') }}{{ $leg['payload'] ? ' / ' . rtrim(rtrim(number_format($leg['payload'], 3), '0'), '.') : '' }} MT{{ $leg['percent'] !== null ? ' · ' . $leg['percent'] . '%' : '' }}</span></div>
              @if ($leg['percent'] !== null)
                <div class="mt-1 h-2 overflow-hidden rounded-full bg-ink-100"><div class="h-full rounded-full {{ $leg['percent'] > 100 ? 'bg-overdue' : ($leg['percent'] >= 85 ? 'bg-paid' : 'bg-brand-400') }} fl-bar" data-width="{{ min(100, $leg['percent']) }}"></div></div>
              @endif
            </div>
          @endforeach
          @if (collect($legs)->contains(fn ($l) => $l['percent'] !== null && $l['percent'] > 100))<p class="text-sm font-medium text-overdue">{{ __('Over the truck’s payload on at least one leg. Check before sending.') }}</p>@endif
        </div>
      @endif
      @if ($t->isPlanning())
        @can('trip.create')
          <form method="POST" action="{{ route('trips.stops.store', $t) }}" class="mt-4 flex flex-wrap items-end gap-2">
            @csrf
            <select name="place_id" class="input w-48 py-2" aria-label="{{ __('Place') }}">@foreach ($places as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->short_code }})</option>@endforeach</select>
            <select name="kind" class="input w-40 py-2" aria-label="{{ __('Kind') }}">@foreach (\App\Modules\Trips\Models\TripStop::KINDS as $v => $label)<option value="{{ $v }}">{{ __($label) }}</option>@endforeach</select>
            <select name="after_seq" class="input w-40 py-2" aria-label="{{ __('After stop') }}">@foreach ($t->stops->slice(0, -1) as $s)<option value="{{ $s->seq }}" @selected($loop->last)>{{ __('after :place', ['place' => $s->place->name]) }}</option>@endforeach</select>
            <button class="btn-secondary"><x-icon name="plus" /> {{ __('Add stop') }}</button>
          </form>
        @endcan
      @endif
    </section>

    @if (session('track'))
      @php($track = session('track'))
      <section class="card p-6" data-track-link>
        <h3 class="font-semibold">{{ __('Tracking link for LR :lr', ['lr' => $track['lr']]) }}</h3>
        <p class="mt-1 text-sm text-ink-500">{{ __('The client sees where the load is and when it is delivered — never the freight or the crew. It ends 30 days after delivery.') }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <input class="input min-w-0 flex-1 font-mono text-xs" value="{{ $track['url'] }}" readonly aria-label="{{ __('Tracking link') }}" onclick="this.select()">
          <a href="https://wa.me/?text={{ rawurlencode($track['message']) }}" class="btn-secondary" target="_blank" rel="noopener noreferrer"><x-icon name="send" /> {{ __('WhatsApp') }}</a>
          <a href="{{ $track['url'] }}" class="btn-secondary" target="_blank" rel="noopener">{{ __('Open') }}</a>
        </div>
      </section>
    @endif
    <section id="loads" class="card overflow-hidden">
      <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
        <h3 class="font-semibold">{{ __('Loads (LRs)') }}</h3>
        @if (! in_array($t->status, ['ended', 'settled', 'closed', 'cancelled'], true))
          @can('trip.create')<a href="{{ route('trips.loads.create', $t) }}" class="btn-secondary"><x-icon name="plus" /> {{ __('Add a load') }}</a>@endcan
        @endif
      </div>
      <div class="overflow-x-auto">
        <table class="table">
          <thead><tr><th>{{ __('LR') }}</th><th>{{ __('Party') }}</th><th>{{ __('Goods') }}</th><th>{{ __('Pick → drop') }}</th>@if ($canMoney)<th class="text-end">{{ __('Freight') }}</th>@endif<th>{{ __('Status') }}</th></tr></thead>
          <tbody>
            @forelse ($loads as $c)
              <tr class="{{ $c->status === 'cancelled' ? 'opacity-50' : '' }}">
                <td>
                  @can('trip.create')<a href="{{ route('trips.loads.edit', [$t, $c]) }}" class="docno font-mono text-sm font-semibold text-brand-700 hover:underline">{{ $c->lr_number ?? __('(on sending)') }}</a>@else<span class="docno font-mono text-sm">{{ $c->lr_number }}</span>@endcan
                  <div class="text-xs text-ink-500">{{ __(\App\Modules\Trips\Models\Consignment::PAYMENT_TYPES[$c->payment_type]) }}</div>
                </td>
                <td class="text-sm">{{ $c->billingParty->name }}@if ($c->consignee)<div class="text-xs text-ink-500">→ {{ $c->consignee->name }}</div>@endif</td>
                <td class="text-sm">{{ $materials[$c->material] ?? $c->description ?? '—' }}<div class="text-xs text-ink-500">{{ collect([$c->packages ? $c->packages . ' ' . __($c->package_type ?? 'pcs') : null, $c->weight() ? rtrim(rtrim(number_format($c->weight(), 3), '0'), '.') . ' MT' : null])->filter()->join(' · ') }}</div></td>
                <td class="text-sm">{{ $c->pickupStop?->place?->name }} → {{ $c->dropStop?->place?->name }}</td>
                @if ($canMoney)<td class="money text-end">{{ \App\Support\Money::company($c->totalCharges()) }}<div class="text-xs text-ink-500">{{ $c->rate !== null ? \App\Support\Money::company($c->rate) . ' ' . __(\App\Modules\Trips\Models\Consignment::BASES[$c->freight_basis]) : '' }}</div></td>@endif
                <td>
                  <span class="pill bg-ink-100 text-ink-700">{{ __(\App\Modules\Trips\Models\Consignment::STATUSES[$c->status]) }}</span>@if ($c->hazardous)<span class="pill ms-1 bg-red-50 text-overdue">{{ __('Hazardous') }}</span>@endif
                  @if ($c->lr_number && $c->status !== 'cancelled')
                    <div class="mt-1 flex flex-wrap gap-2 text-xs">
                      <a href="{{ route('trips.loads.lr', [$t, $c]) }}" target="_blank" rel="noopener" class="font-medium text-brand-700 hover:underline">{{ __('Print LR') }}</a>
                      @if ($c->invoice_id)
                        @can('invoice.view')<a href="{{ route('invoices.show', $c->invoice_id) }}" class="font-medium text-paid hover:underline">{{ __('Billed') }}</a>@endcan
                      @else
                        @can('invoice.create')<a href="{{ route('invoices.create', ['party' => $c->billing_party_id, 'loads' => [$c->id]]) }}" class="font-medium text-brand-700 hover:underline">{{ __('Bill it') }}</a>@endcan
                      @endif
                      {{-- The client's tracking link (M45). --}}
                      <form method="POST" action="{{ route('trips.track', [$t, $c]) }}" class="inline">@csrf<button class="font-medium text-brand-700 hover:underline">{{ __('Tracking link') }}</button></form>
                    </div>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="py-8 text-center text-ink-500">{{ $t->trip_kind === 'empty' ? __('Empty run — no load.') : __('No load yet.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    @if ($t->truck_source === 'hired')
      @include('trips.partials.hire-summary')
    @endif

    @if (in_array($t->status, ['running', 'ended', 'settled', 'closed'], true))
      @include('trips.partials.route')
    @endif

    @include('trips.partials.road')

    @if (in_array($t->status, ['ended', 'settled', 'closed'], true))
      @include('trips.partials.settlement')
    @endif

    @if (! in_array($t->status, ['draft', 'cancelled'], true))
      @include('trips.partials.office')
    @endif
  </div>

  {{-- Side cards: a column on wide screens, two across below the loads otherwise. --}}
  <div class="grid content-start gap-6 md:grid-cols-2 2xl:grid-cols-1">
    <section class="card p-6">
      <h3 class="font-semibold">{{ __('Crew') }}</h3>
      <ul class="mt-3 space-y-2 text-sm">
        @forelse ($t->crew as $m)
          <li>
            <div class="flex items-center justify-between gap-2"><a href="{{ route('people.show', $m->person) }}" class="font-medium hover:text-brand-700">{{ $m->person->name }}</a><span class="text-xs text-ink-500">{{ __(\App\Modules\Trips\Models\TripCrew::ROLES[$m->role]) }}{{ $m->accepted_at ? ' · ' . __('accepted') : '' }}</span></div>
            @if ($m->rejected_at)<p class="text-xs text-overdue">{{ __('Cannot take this trip: :reason', ['reason' => $m->reject_reason]) }}</p>@endif
          </li>
        @empty
          <li class="text-ink-500">{{ $t->truck_source === 'hired' ? __('The supplier’s driver.') : __('No crew.') }}</li>
        @endforelse
      </ul>
      @if ($driving->isNotEmpty())
        <h4 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ __('Driving') }}</h4>
        <ul class="mt-2 space-y-1 text-sm">
          @foreach ($driving as $d)
            <li class="flex justify-between gap-2"><span>{{ $d['name'] }}@if ($d['now']) <span class="pill bg-brand-50 text-brand-700">{{ __('driving now') }}</span>@endif</span><span class="font-mono text-xs">{{ intdiv($d['minutes'], 60) }} h {{ str_pad((string) ($d['minutes'] % 60), 2, '0', STR_PAD_LEFT) }} min</span></li>
          @endforeach
        </ul>
      @endif
    </section>

    @if ($canMoney)
      @php($p = $profit)
      <section class="card p-6">
        <h3 class="font-semibold">{{ __('Money') }}</h3>
        <dl class="mt-3 space-y-1.5 text-sm">
          <div class="flex justify-between"><dt class="text-ink-500">{{ __('Freight and charges') }}</dt><dd class="money">{{ \App\Support\Money::company($p['loads']) }}</dd></div>
          @if ($p['other_charges'] > 0)<div class="flex justify-between"><dt class="text-ink-500">{{ __('Other charges (billed)') }}</dt><dd class="money">{{ \App\Support\Money::company($p['other_charges']) }}</dd></div>@endif
          @if ($p['office_income'] > 0)<div class="flex justify-between"><dt class="text-ink-500">{{ __('Office income') }}</dt><dd class="money">{{ \App\Support\Money::company($p['office_income']) }}</dd></div>@endif
          @if ($p['commission_earned'] > 0)<div class="flex justify-between"><dt class="text-ink-500">{{ __('Commission earned') }}</dt><dd class="money">{{ \App\Support\Money::company($p['commission_earned']) }}</dd></div>@endif
          <div class="flex justify-between border-t border-ink-100 pt-1.5 font-semibold"><dt>{{ __('Trip income') }}</dt><dd class="money">{{ \App\Support\Money::company($p['income']) }}</dd></div>
          @foreach (['road' => __('Road spending (approved)'), 'hire' => __('Lorry hire'), 'commission_paid' => __('Commission paid'), 'office_expense' => __('Office expenses'), 'bata' => __('Bata')] as $key => $label)
            @if ($p[$key] > 0)<div class="flex justify-between"><dt class="text-ink-500">{{ $label }}</dt><dd class="money">− {{ \App\Support\Money::company($p[$key]) }}</dd></div>@endif
          @endforeach
          <div class="flex justify-between border-t border-ink-100 pt-1.5 font-semibold {{ $p['profit'] >= 0 ? 'text-paid' : 'text-overdue' }}"><dt>{{ __('Trip profit (direct)') }}</dt><dd class="money">{{ \App\Support\Money::company($p['profit']) }}</dd></div>
          @if ($p['per_km'])<div class="flex justify-between text-xs text-ink-500"><dt>{{ __('per km') }}</dt><dd class="money">{{ \App\Support\Money::company($p['per_km']) }}</dd></div>@endif
          @php($truckCosts = round(($fixed['share'] ?? 0) + ($tyreWear['amount'] ?? 0), 2))
          @if ($truckCosts > 0)
            {{-- The truck's costs whether it runs or not for the trip's days (M37) and its tyres' wear for the trip's km (M38) — estimates. --}}
            @if ($fixed && $fixed['share'] > 0)<div class="flex justify-between gap-3 pt-1.5"><dt class="text-ink-500">{{ trans_choice('Truck’s fixed costs, :count day (estimate)|Truck’s fixed costs, :count days (estimate)', $fixed['days']) }}</dt><dd class="money">− {{ \App\Support\Money::company($fixed['share']) }}</dd></div>@endif
            @if ($tyreWear)<div class="flex justify-between gap-3"><dt class="text-ink-500">{{ __('Tyre wear, :km km (estimate)', ['km' => number_format($tyreWear['km'])]) }}</dt><dd class="money">− {{ \App\Support\Money::company($tyreWear['amount']) }}</dd></div>@endif
            <div class="flex justify-between border-t border-ink-100 pt-1.5 font-semibold {{ $p['profit'] - $truckCosts >= 0 ? 'text-paid' : 'text-overdue' }}"><dt>{{ __('Profit after the truck’s costs') }}</dt><dd class="money">{{ \App\Support\Money::company(round($p['profit'] - $truckCosts, 2)) }}</dd></div>
            @if ($fixed && $fixed['share'] > 0)<p class="text-xs text-ink-500">{{ __(':amount a day: what the truck’s papers, usual driver’s salary and its own bills cost — see the truck.', ['amount' => \App\Support\Money::company($fixed['per_day'])]) }}</p>@endif
          @endif
        </dl>
        @if ($trip->ended_at && ($trip->end_odometer || $trip->gps_km !== null))
          {{-- Odometer against GPS (M34). --}}
          @php($gap = \App\Modules\Road\Services\GpsDistance::gap($trip))
          <div class="mt-3 rounded-xl bg-ink-50 p-3 text-sm" data-km-check="{{ $trip->km_check }}">
            <div class="flex flex-wrap justify-between gap-2"><span class="text-ink-500">{{ __('Distance') }}</span>
              <span>{{ $trip->start_odometer && $trip->end_odometer ? __('Odometer :km km', ['km' => number_format($trip->end_odometer - $trip->start_odometer)]) : __('No odometer reading') }}
                · {{ $trip->gps_km !== null ? __('GPS :km km (:p% of the trip covered)', ['km' => number_format((float) $trip->gps_km), 'p' => $trip->gps_coverage]) : __('No GPS') }}</span></div>
            @if ($trip->km_check === 'differs')
              <p class="mt-1.5 font-medium text-partial">{{ __('The odometer and the GPS differ by :p% — check the readings or ask the driver about a run not entered.', ['p' => abs($gap)]) }}</p>
            @elseif ($trip->km_check === 'thin')
              <p class="mt-1.5 text-xs text-ink-500">{{ __('Too little GPS (or no odometer) to compare the two.') }}</p>
            @endif
          </div>
        @endif
        @if ($p['waiting'] > 0)<p class="mt-3 text-xs text-partial">{{ __(':amount of crew entries still wait for approval and are not counted yet.', ['amount' => \App\Support\Money::company($p['waiting'])]) }}</p>@endif
        @can('accounts.view')<p class="mt-3 text-xs"><a href="{{ route('accounts.profit-loss', ['trip_id' => $trip->id, 'from' => '2000-01-01']) }}" class="text-brand-700 hover:underline">{{ __('This trip in the books') }}</a></p>@endcan
      </section>
    @endif

    <section class="card p-6">
      <h3 class="font-semibold">{{ __('Advances to the crew') }}</h3>
      <ul class="mt-3 space-y-1.5 text-sm">
        @forelse ($t->advances as $a)
          <li class="flex justify-between gap-2"><span>{{ $a->person->name }} <span class="text-xs text-ink-500">· {{ $a->given_on->format('d M') }} · {{ __(\App\Modules\Trips\Models\TripAdvance::MODES[$a->mode]) }}</span></span><span class="money">{{ \App\Support\Money::company($a->amount) }}</span></li>
        @empty
          <li class="text-ink-500">{{ __('None yet.') }}</li>
        @endforelse
      </ul>
      @can('trip.settle')
        @if ($t->crew->isNotEmpty() && ! in_array($t->status, ['settled', 'closed', 'cancelled'], true))
          <form method="POST" action="{{ route('trips.advances.store', $t) }}" class="mt-4 grid gap-2" novalidate>
            @csrf
            <select name="person_id" class="input py-2" aria-label="{{ __('To') }}">@foreach ($t->crew as $m)<option value="{{ $m->person_id }}">{{ $m->person->name }}</option>@endforeach</select>
            <div class="grid grid-cols-2 gap-2">
              <input name="amount" type="number" min="1" step="0.01" class="input py-2" placeholder="{{ __('Amount') }}" aria-label="{{ __('Amount') }}">
              <select name="mode" class="input py-2" aria-label="{{ __('Mode') }}">@foreach (\App\Modules\Trips\Models\TripAdvance::MODES as $v => $label)<option value="{{ $v }}">{{ __($label) }}</option>@endforeach</select>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <input name="given_on" type="date" class="input py-2" value="{{ now()->toDateString() }}" aria-label="{{ __('Date') }}">
              <input name="reference" class="input py-2" placeholder="{{ __('UTR / UPI ref') }}" maxlength="60" aria-label="{{ __('Reference') }}">
            </div>
            @foreach (['person_id', 'amount', 'reference', 'given_on', 'advance'] as $f)@error($f)<span class="error">{{ $message }}</span>@enderror @endforeach
            <button class="btn-secondary"><x-icon name="wallet" /> {{ __('Give advance') }}</button>
          </form>
        @endif
      @endcan
    </section>

    @include('trips.partials.commissions')

    @if ($t->instructions || $t->office_note)
      <section class="card space-y-3 p-6 text-sm">
        @if ($t->instructions)<div><h3 class="font-semibold">{{ __('Instructions to the crew') }}</h3><p class="mt-1 whitespace-pre-line">{{ $t->instructions }}</p></div>@endif
        @if ($t->office_note)<div><h3 class="font-semibold">{{ __('Office note') }}</h3><p class="mt-1 whitespace-pre-line text-ink-700">{{ $t->office_note }}</p></div>@endif
      </section>
    @endif

    @if ($t->isCancellable())
      @can('trip.cancel')
        <form method="POST" action="{{ route('trips.cancel', $t) }}" class="card space-y-2 p-6" data-confirm="{{ __('Cancel this trip? LR numbers stay used.') }}">
          @csrf
          <label class="label" for="cancel_reason">{{ __('Cancel the trip') }}</label>
          <input id="cancel_reason" name="reason" class="input" maxlength="255" placeholder="{{ __('Why') }}">
          @error('reason')<span class="error">{{ $message }}</span>@enderror
          <button class="btn-danger w-full">{{ __('Cancel trip') }}</button>
        </form>
      @endcan
    @endif
  </div>
</div>
@endsection
