<div class="grid gap-6 xl:grid-cols-2">
  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Details') }}</h3>
    <dl class="mt-3 grid grid-cols-[10rem_1fr] gap-x-4 gap-y-2 text-sm">
      <dt class="text-ink-500">{{ __('RC owner') }}</dt><dd>{{ $truck->rc_owner_name }}</dd>
      @if ($truck->registration_date)<dt class="text-ink-500">{{ __('Registered') }}</dt><dd>{{ $truck->registration_date->format('d M Y') }}{{ $truck->registered_rto ? ' · ' . $truck->registered_rto : '' }}</dd>@endif
      @if ($truck->vehicle_class)<dt class="text-ink-500">{{ __('Class') }}</dt><dd>{{ $truck->vehicle_class }}</dd>@endif
      <dt class="text-ink-500">{{ __('Body') }}</dt><dd>{{ collect([$bodies[$truck->body_type] ?? null, $truck->rate_card_label, $truck->deck_length_ft ? rtrim(rtrim((string) $truck->deck_length_ft, '0'), '.') . ' ft' : null])->filter()->join(' · ') ?: '—' }}</dd>
      <dt class="text-ink-500">{{ __('Fuel') }}</dt><dd>{{ __(\App\Modules\Fleet\Models\Truck::FUELS[$truck->fuel_type] ?? $truck->fuel_type) }}{{ $truck->emission_norm ? ' · ' . $truck->emission_norm : '' }}{{ $truck->fuel_tank_l ? ' · ' . $truck->fuel_tank_l . ' L' : '' }}</dd>
      @if ($truck->mileage_loaded_kmpl)<dt class="text-ink-500">{{ __('Normal mileage') }}</dt><dd>{{ __(':loaded km/l loaded, :empty empty', ['loaded' => $truck->mileage_loaded_kmpl, 'empty' => $truck->mileage_empty_kmpl ?? '—']) }}</dd>@endif
      @if ($truck->gvw_kg)<dt class="text-ink-500">{{ __('Weights') }}</dt><dd>{{ __('GVW :g kg · unladen :u kg', ['g' => number_format($truck->gvw_kg), 'u' => $truck->unladen_kg ? number_format($truck->unladen_kg) : '—']) }}</dd>@endif
      <dt class="text-ink-500">{{ __('Odometer') }}</dt><dd>{{ $truck->odometer_km !== null ? number_format($truck->odometer_km) . ' km' : '—' }}@if ($truck->odometer_at) <span class="text-xs text-ink-400">({{ $truck->odometer_at->diffForHumans() }})</span>@endif</dd>
      @if ($truck->chassis_no)<dt class="text-ink-500">{{ __('Chassis / engine') }}</dt><dd class="font-mono text-xs">{{ $truck->chassis_no }} · {{ $truck->engine_no }}</dd>@endif
      @if ($truck->fastag_no)<dt class="text-ink-500">{{ __('Toll tag') }}</dt><dd>{{ $truck->fastag_no }}</dd>@endif
      @if ($truck->financier)<dt class="text-ink-500">{{ __('Loan') }}</dt><dd>{{ $truck->financier }}{{ $truck->emi_amount ? ' · ' . \App\Support\Money::company($truck->emi_amount) . ' ' . __('on day :d', ['d' => $truck->emi_day]) : '' }}{{ $truck->loan_ends_on ? ' · ' . __('until :date', ['date' => $truck->loan_ends_on->format('M Y')]) : '' }}</dd>@endif
      @if ($truck->branch)<dt class="text-ink-500">{{ __('Branch') }}</dt><dd>{{ $truck->branch->name }}</dd>@endif
    </dl>
  </section>

  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold">{{ __('Open complaints') }}</h3>
        <a href="{{ route('trucks.show', [$truck, 'tab' => 'complaints']) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('All') }}</a>
      </div>
      <ul class="mt-3 divide-y divide-ink-100">
        @forelse ($complaints->filter->isOpen() as $c)
          <li class="py-2.5 text-sm"><a href="{{ route('complaints.show', $c) }}" class="font-medium hover:text-brand-700"><span class="docno font-mono text-xs text-ink-400">{{ $c->number }}</span> {{ $c->title }}</a>
            <div class="text-xs text-ink-500">{{ __(\App\Modules\Fleet\Models\Complaint::STATUSES[$c->status]) }} · {{ $c->created_at->format('d M') }}{{ $c->raisedBy ? ' · ' . $c->raisedBy->name : '' }}</div></li>
        @empty
          <li class="py-3 text-sm text-ink-500">{{ __('No open complaints.') }}</li>
        @endforelse
      </ul>
    </section>

    <section class="card p-6">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold">{{ __('Service due') }}</h3>
        <a href="{{ route('trucks.show', [$truck, 'tab' => 'service']) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Service') }}</a>
      </div>
      <ul class="mt-3 space-y-2 text-sm">
        @forelse ($schedules as $s)
          @php($state = $s->state($truck->odometer_km))
          <li class="flex items-center justify-between gap-3">
            <span>{{ $s->name }}</span>
            <span class="pill {{ ['overdue' => 'bg-red-50 text-overdue', 'soon' => 'bg-amber-50 text-partial', 'ok' => 'bg-emerald-50 text-paid'][$state] ?? 'bg-ink-100 text-ink-500' }}">
              {{ collect([$s->dueKm() ? number_format($s->dueKm()) . ' km' : null, $s->dueOn()?->format('d M Y')])->filter()->join(' ' . __('or') . ' ') ?: __('Set the last service') }}
            </span>
          </li>
        @empty
          <li class="text-ink-500">{{ __('No service schedule yet.') }}</li>
        @endforelse
      </ul>
    </section>

    {{-- Share the truck's details as text (Trucksarthi parity #18). --}}
    @php($shareFields = [
      ['k' => 'number', 'label' => __('Number'), 'value' => $truck->reg_no, 'on' => true],
      ['k' => 'model', 'label' => __('Make / model'), 'value' => trim($truck->make . ' ' . $truck->model), 'on' => true],
      ['k' => 'owner', 'label' => __('RC owner'), 'value' => $truck->rc_owner_name, 'on' => true],
      ['k' => 'chassis', 'label' => __('Chassis'), 'value' => \App\Modules\Fleet\Models\Truck::tail($truck->chassis_no), 'on' => false],
      ['k' => 'engine', 'label' => __('Engine'), 'value' => \App\Modules\Fleet\Models\Truck::tail($truck->engine_no), 'on' => false],
      ['k' => 'norm', 'label' => __('Emission norm'), 'value' => $truck->emission_norm, 'on' => false],
      ['k' => 'unladen', 'label' => __('Unladen weight'), 'value' => $truck->unladen_kg ? number_format($truck->unladen_kg) . ' kg' : null, 'on' => false],
      ['k' => 'age', 'label' => __('Age'), 'value' => $truck->age(), 'on' => false],
    ])
    <section class="card p-6" data-share-text data-footer="{{ app(\App\Platform\Tenancy\CompanyContext::class)->company()->name }}">
      <h3 class="font-semibold">{{ __('Share details') }}</h3>
      <div class="mt-3 flex flex-wrap gap-2">
        @foreach ($shareFields as $f)
          @continue(blank($f['value']))
          <label class="flex items-center gap-1.5 rounded-full border border-ink-100 px-3 py-1 text-xs has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50">
            <input type="checkbox" class="size-3.5 rounded border-ink-300" data-share-field data-label="{{ $f['label'] }}" data-value="{{ $f['value'] }}" @checked($f['on'])> {{ $f['label'] }}
          </label>
        @endforeach
      </div>
      <div class="mt-3 flex gap-2">
        <button type="button" class="btn-secondary text-sm" data-share-copy data-done="{{ __('Copied') }}">{{ __('Copy text') }}</button>
        <a href="#" class="btn-secondary text-sm" data-share-whatsapp target="_blank" rel="noopener noreferrer">{{ __('WhatsApp') }}</a>
      </div>
    </section>
  </div>
</div>
