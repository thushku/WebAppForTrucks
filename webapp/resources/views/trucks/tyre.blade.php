@extends('layouts.app')
@section('title', $tyre->serial_no)
@section('content')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $tones = ['in_store' => 'bg-brand-50 text-brand-700', 'fitted' => 'bg-emerald-50 text-paid', 'retreading' => 'bg-amber-50 text-partial', 'scrapped' => 'bg-ink-100 text-ink-500'];
  $actual = $tyre->status === 'scrapped' && $tyre->km_run > 0 ? round(((float) $tyre->cost - (float) $tyre->scrap_value) / $tyre->km_run, 2) : null;
@endphp
<a href="{{ route('trucks.tyres') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Tyres') }}</a>
@if ($errors->any())<p class="mt-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-overdue" role="alert">{{ $errors->first() }}</p>@endif

<section class="card mt-3 p-6">
  <div class="flex flex-wrap items-start gap-4">
    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-ink-100 text-ink-700"><x-icon name="tyre" class="size-6" /></span>
    <div class="min-w-0 flex-1">
      <h1 class="docno break-all font-mono text-xl font-semibold sm:text-2xl">{{ $tyre->serial_no }}</h1>
      <p class="mt-0.5 text-sm text-ink-500">{{ collect([$tyre->brand, $tyre->model, $tyre->size, $tyre->kind === 'retread' ? trans_choice('retreaded once|retreaded :count times', $tyre->retreads) : __('New')])->filter()->join(' · ') }}</p>
      <div class="mt-2 flex flex-wrap gap-1.5">
        <span class="pill {{ $tones[$tyre->status] ?? '' }}">{{ __(\App\Modules\Fleet\Models\Tyre::STATUSES[$tyre->status]) }}</span>
        @if ($tyre->status === 'fitted' && $tyre->truck)<a href="{{ route('trucks.show', [$tyre->truck, 'tab' => 'tyres']) }}" class="pill bg-ink-100 text-ink-700 hover:bg-ink-200">{{ $tyre->truck->reg_no }} · {{ $tyre->position }} · {{ $service->positionName($tyre->position) }}</a>@endif
      </div>
    </div>
  </div>
  <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 xl:grid-cols-6">
    <div><dt class="text-ink-500">{{ __('Km run') }}</dt><dd class="text-lg font-semibold">{{ number_format($km) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Expected km') }}</dt><dd class="text-lg font-semibold">{{ $tyre->expected_km ? number_format($tyre->expected_km) : '—' }}</dd>@if ($used !== null)<dd class="text-xs {{ $used >= \App\Modules\Fleet\Services\Tyres::NEAR_END ? 'font-semibold text-partial' : 'text-ink-500' }}">{{ __(':p% used', ['p' => (int) round($used * 100)]) }}</dd>@endif</div>
    <div><dt class="text-ink-500">{{ __('Cost, with retreads') }}</dt><dd class="money text-lg font-semibold">{{ $m($tyre->cost) }}</dd></div>
    <div><dt class="text-ink-500">{{ __('Cost per km, expected') }}</dt><dd class="money text-lg font-semibold">{{ $tyre->costPerKm() !== null ? $m($tyre->costPerKm()) : '—' }}</dd></div>
    @if ($actual !== null)<div><dt class="text-ink-500">{{ __('Cost per km, actual') }}</dt><dd class="money text-lg font-semibold">{{ $m($actual) }}</dd>@if ($tyre->scrap_value)<dd class="text-xs text-ink-500">{{ __('after :amount scrap money', ['amount' => $m($tyre->scrap_value)]) }}</dd>@endif</div>@endif
    <div><dt class="text-ink-500">{{ __('Bought') }}</dt><dd>{{ $tyre->purchased_on?->format('d M Y') ?? '—' }}</dd>@if ($tyre->vendor)<dd class="text-xs text-ink-500">{{ $tyre->vendor->name }}</dd>@endif</div>
  </dl>
  @if ($tyre->notes)<p class="mt-3 text-sm text-ink-600">{{ $tyre->notes }}</p>@endif
</section>

@can('complaint.manage')
  <div class="mt-6 grid gap-6 lg:grid-cols-2">
    @if ($tyre->status === 'fitted')
      <section class="card p-6 text-sm">
        <h2 class="font-semibold">{{ __('On a truck') }}</h2>
        <p class="mt-1 text-ink-500">{{ __('Take it off, move or swap it on the truck’s Tyres tab.') }}</p>
        @if ($tyre->truck)<a href="{{ route('trucks.show', [$tyre->truck, 'tab' => 'tyres']) }}" class="btn-secondary mt-3">{{ $tyre->truck->reg_no }}</a>@endif
      </section>
    @endif
    @if ($tyre->status === 'in_store')
      <form method="POST" action="{{ route('trucks.tyres.retread', $tyre) }}" class="card grid gap-3 p-6 sm:grid-cols-2" novalidate>@csrf
        <h2 class="font-semibold sm:col-span-2">{{ __('Send for retreading') }}</h2>
        <div><label class="label" for="rt_on">{{ __('Sent on') }}</label><input id="rt_on" type="date" name="sent_on" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" class="input" required></div>
        <div><label class="label" for="rt_vendor">{{ __('Retreader') }}</label><select id="rt_vendor" name="vendor_party_id" class="input"><option value="">—</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
        <div class="text-end sm:col-span-2"><button class="btn-secondary">{{ __('Send') }}</button></div>
      </form>
    @endif
    @if ($tyre->status === 'retreading')
      <form method="POST" action="{{ route('trucks.tyres.returned', $tyre) }}" class="card grid gap-3 p-6 sm:grid-cols-2" novalidate>@csrf
        <h2 class="font-semibold sm:col-span-2">{{ __('Back from the retreader') }}</h2>
        <div><label class="label" for="rb_on">{{ __('Back on') }}</label><input id="rb_on" type="date" name="returned_on" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" class="input" required></div>
        <div><label class="label" for="rb_cost">{{ __('Retreading cost') }}</label><input id="rb_cost" type="number" step="0.01" min="0" name="cost" value="{{ old('cost') }}" class="input" required>@error('cost')<span class="error">{{ $message }}</span>@enderror</div>
        <div><label class="label" for="rb_km">{{ __('Good for another') }}</label><input id="rb_km" type="number" min="1000" name="expected_km" value="{{ old('expected_km') }}" class="input" placeholder="40000"><span class="hint">{{ __('km — adds to its expected km') }}</span></div>
        <div><label class="label" for="rb_vendor">{{ __('Retreader') }}</label><select id="rb_vendor" name="vendor_party_id" class="input"><option value="">—</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}" @selected($tyre->retreadings->first()?->vendor_party_id === $id)>{{ $name }}</option>@endforeach</select></div>
        <div class="text-end sm:col-span-2"><button class="btn-primary">{{ __('Back in the store') }}</button></div>
      </form>
    @endif
    @if (in_array($tyre->status, ['in_store', 'retreading'], true))
      <form method="POST" action="{{ route('trucks.tyres.scrap', $tyre) }}" class="card grid gap-3 p-6 sm:grid-cols-2" novalidate>@csrf
        <h2 class="font-semibold sm:col-span-2">{{ __('Scrap') }}</h2>
        <div><label class="label" for="sc_on">{{ __('Scrapped on') }}</label><input id="sc_on" type="date" name="scrapped_on" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" class="input" required></div>
        <div><label class="label" for="sc_value">{{ __('Scrap money') }}</label><input id="sc_value" type="number" step="0.01" min="0" name="scrap_value" class="input"></div>
        <div class="text-end sm:col-span-2"><button class="btn-secondary">{{ __('Scrap the tyre') }}</button></div>
      </form>
    @endif
  </div>
@endcan

<section class="card mt-6 overflow-hidden">
  <h2 class="px-6 pt-5 font-semibold">{{ __('Where it has run') }}</h2>
  <div class="relative mt-2 overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Truck') }}</th><th>{{ __('Position') }}</th><th>{{ __('Fitted') }}</th><th>{{ __('Taken off') }}</th><th class="text-end">{{ __('Km') }}</th><th>{{ __('Why') }}</th></tr></thead>
      <tbody>
        @forelse ($tyre->fitments as $f)
          <tr>
            <td class="text-sm"><a href="{{ route('trucks.show', [$f->truck_id, 'tab' => 'tyres']) }}" class="font-mono hover:text-brand-700">{{ $f->truck?->reg_no }}</a></td>
            <td class="whitespace-nowrap font-mono text-sm">{{ $f->position }}</td>
            <td class="whitespace-nowrap text-sm">{{ $f->fitted_on->format('d M Y') }}<div class="text-xs text-ink-500">{{ number_format($f->fitted_odo) }} km</div></td>
            <td class="whitespace-nowrap text-sm">@if ($f->removed_on){{ $f->removed_on->format('d M Y') }}<div class="text-xs text-ink-500">{{ number_format($f->removed_odo) }} km</div>@else<span class="pill bg-emerald-50 text-paid">{{ __('On the truck') }}</span>@endif</td>
            <td class="text-end text-sm">{{ $f->km !== null ? number_format($f->km) : '—' }}</td>
            <td class="text-sm">{{ $f->reason ? __(\App\Modules\Fleet\Models\TyreFitment::REASONS[$f->reason][0] ?? $f->reason) : '' }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-6 text-center text-sm text-ink-500">{{ __('Not fitted yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($tyre->retreadings->isNotEmpty())
    <h2 class="px-6 pt-5 font-semibold">{{ __('Retreads') }}</h2>
    <div class="relative mt-2 overflow-x-auto">
      <table class="table">
        <thead><tr><th>{{ __('Sent') }}</th><th>{{ __('Back') }}</th><th>{{ __('Retreader') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Good for') }}</th></tr></thead>
        <tbody>
          @foreach ($tyre->retreadings as $r)
            <tr><td class="text-sm">{{ $r->sent_on->format('d M Y') }}</td><td class="text-sm">{{ $r->returned_on?->format('d M Y') ?? '—' }}</td><td class="text-sm">{{ $r->vendor?->name ?? '—' }}</td>
              <td class="money text-end text-sm">{{ $r->cost !== null ? $m($r->cost) : '—' }}</td><td class="text-end text-sm">{{ $r->expected_km ? number_format($r->expected_km) . ' km' : '—' }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

@can('complaint.manage')
<details class="card mt-6 p-6" @if ($errors->hasAny(['serial_no', 'brand', 'size', 'cost', 'expected_km'])) open @endif>
  <summary class="cursor-pointer font-semibold">{{ __('Edit the details') }}</summary>
  <form method="POST" action="{{ route('trucks.tyres.update', $tyre) }}" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" novalidate>@csrf @method('PUT')
    <div><label class="label" for="te_serial">{{ __('Serial no.') }}</label><input id="te_serial" name="serial_no" value="{{ old('serial_no', $tyre->serial_no) }}" class="input font-mono" maxlength="40" required>@error('serial_no')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="te_brand">{{ __('Brand') }}</label><input id="te_brand" name="brand" value="{{ old('brand', $tyre->brand) }}" class="input" maxlength="40" required>@error('brand')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="te_model">{{ __('Model / pattern') }}</label><input id="te_model" name="model" value="{{ old('model', $tyre->model) }}" class="input" maxlength="40"></div>
    <div><label class="label" for="te_size">{{ __('Size') }}</label><input id="te_size" name="size" value="{{ old('size', $tyre->size) }}" class="input" maxlength="30" required>@error('size')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="te_kind">{{ __('New or retreaded') }}</label><select id="te_kind" name="kind" class="input">@foreach (\App\Modules\Fleet\Models\Tyre::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind', $tyre->kind) === $k)>{{ __($l) }}</option>@endforeach</select></div>
    <div><label class="label" for="te_cost">{{ __('Cost, with retreads') }}</label><input id="te_cost" name="cost" type="number" step="0.01" min="0" value="{{ old('cost', $tyre->cost) }}" class="input" required>@error('cost')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="te_km">{{ __('Expected km') }}</label><input id="te_km" name="expected_km" type="number" min="1000" value="{{ old('expected_km', $tyre->expected_km) }}" class="input">@error('expected_km')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="te_date">{{ __('Bought on') }}</label><input id="te_date" name="purchased_on" type="date" value="{{ old('purchased_on', $tyre->purchased_on?->toDateString()) }}" max="{{ $today->toDateString() }}" class="input"></div>
    <div><label class="label" for="te_vendor">{{ __('From') }}</label><select id="te_vendor" name="vendor_party_id" class="input"><option value="">—</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}" @selected((int) old('vendor_party_id', $tyre->vendor_party_id) === $id)>{{ $name }}</option>@endforeach</select></div>
    @if ($branches->count() > 1)<div><label class="label" for="te_branch">{{ __('Branch store') }}</label><select id="te_branch" name="branch_id" class="input"><option value="">{{ __('Shared') }}</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((int) old('branch_id', $tyre->branch_id) === $id)>{{ $name }}</option>@endforeach</select></div>@endif
    <div class="sm:col-span-2"><label class="label" for="te_notes">{{ __('Notes') }}</label><input id="te_notes" name="notes" value="{{ old('notes', $tyre->notes) }}" class="input" maxlength="255"></div>
    <div class="text-end sm:col-span-2 xl:col-span-4"><button class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button></div>
  </form>
</details>
@endcan
@endsection
