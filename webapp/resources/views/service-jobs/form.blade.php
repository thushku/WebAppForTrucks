@extends('layouts.app')
@section('title', $job->exists ? $job->number : __('New service job'))
@section('content')
@php
  $j = $job;
  $editable = ! $j->exists || $j->status === 'open';
  $items = old('items', $j->exists ? $j->items->map->only(['kind', 'description', 'qty', 'rate'])->all() : []);
  if ($editable) { $items = array_merge($items, [[], [], []]); }
@endphp
<div class="flex flex-wrap items-center gap-3">
  <a href="{{ route('trucks.show', [$truck, 'tab' => 'service']) }}" class="btn-ghost"><x-icon name="arrow-left" /> <span class="docno font-mono">{{ $truck->reg_no }}</span></a>
  @if ($j->exists)<span class="pill {{ $j->status === 'done' ? 'bg-emerald-50 text-paid' : ($j->status === 'open' ? 'bg-amber-50 text-partial' : 'bg-ink-100 text-ink-500') }}">{{ __(\App\Modules\Fleet\Models\ServiceJob::STATUSES[$j->status]) }}</span>@endif
  @error('job')<span class="error">{{ $message }}</span>@enderror
</div>

<form method="POST" action="{{ $j->exists ? route('trucks.service.update', [$truck, $j]) : route('trucks.service.store', $truck) }}" class="space-y-6" novalidate>
  @csrf
  @if ($j->exists) @method('PUT') @endif
  <fieldset @disabled(! $editable) class="space-y-6">
    <section class="card p-6">
      <div class="grid gap-4 sm:grid-cols-4">
        <div><label class="label" for="sj_date">{{ __('Date') }}</label><input id="sj_date" name="job_date" type="date" class="input" value="{{ old('job_date', $j->job_date instanceof \Carbon\CarbonInterface ? $j->job_date->toDateString() : $j->job_date) }}" required>@error('job_date')<span class="error">{{ $message }}</span>@enderror</div>
        <div><label class="label" for="sj_kind">{{ __('Kind') }}</label><select id="sj_kind" name="kind" class="input">@foreach (\App\Modules\Fleet\Models\ServiceJob::KINDS as $v => $label)<option value="{{ $v }}" @selected(old('kind', $j->kind) === $v)>{{ __($label) }}</option>@endforeach</select></div>
        <div><label class="label" for="sj_odo">{{ __('Odometer (km)') }}</label><input id="sj_odo" name="odometer_km" type="number" min="0" class="input" value="{{ old('odometer_km', $j->odometer_km) }}"></div>
        <div><label class="label" for="sj_sched">{{ __('For schedule') }}</label><select id="sj_sched" name="service_schedule_id" class="input"><option value="">—</option>@foreach ($schedules as $id => $name)<option value="{{ $id }}" @selected((int) old('service_schedule_id', $j->service_schedule_id) === $id)>{{ $name }}</option>@endforeach</select></div>
        <div class="sm:col-span-2"><label class="label" for="sj_vendor">{{ __('Workshop') }}</label><select id="sj_vendor" name="vendor_party_id" class="input"><option value="">{{ __('Own mechanic') }}</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}" @selected((int) old('vendor_party_id', $j->vendor_party_id) === $id)>{{ $name }}</option>@endforeach</select></div>
        <div><label class="label" for="sj_bill">{{ __('Their bill no.') }}</label><input id="sj_bill" name="bill_no" class="input" value="{{ old('bill_no', $j->bill_no) }}" maxlength="40"></div>
        <div class="sm:col-span-4"><label class="label" for="sj_desc">{{ __('Work done') }}</label><textarea id="sj_desc" name="description" rows="2" class="input" maxlength="3000">{{ old('description', $j->description) }}</textarea></div>
      </div>
    </section>

    <section class="card overflow-hidden">
      <div class="p-6 pb-3"><h3 class="font-semibold">{{ __('Parts and labour') }}</h3></div>
      @foreach ($errors->getMessages() as $key => $msgs)@if (str_starts_with($key, 'items.'))<p class="error px-6">{{ $msgs[0] }}</p>@endif @endforeach
      <div class="overflow-x-auto">
        <table class="table min-w-[640px]">
          <thead><tr><th>{{ __('Kind') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
          <tbody>
            @foreach ($items as $i => $item)
              <tr>
                <td><select name="items[{{ $i }}][kind]" class="input min-w-[7rem] py-2" aria-label="{{ __('Kind') }}"><option value="part" @selected(($item['kind'] ?? 'part') === 'part')>{{ __('Part') }}</option><option value="labour" @selected(($item['kind'] ?? '') === 'labour')>{{ __('Labour') }}</option></select></td>
                <td><input name="items[{{ $i }}][description]" class="input py-2" value="{{ $item['description'] ?? '' }}" maxlength="200" aria-label="{{ __('Description') }}"></td>
                <td><input name="items[{{ $i }}][qty]" type="number" min="0" step="0.001" class="input w-24 py-2 text-end" value="{{ isset($item['qty']) ? rtrim(rtrim((string) $item['qty'], '0'), '.') : '' }}" aria-label="{{ __('Quantity') }}"></td>
                <td><input name="items[{{ $i }}][rate]" type="number" min="0" step="0.01" class="input w-28 py-2 text-end" value="{{ $item['rate'] ?? '' }}" aria-label="{{ __('Rate') }}"></td>
                <td class="money text-end">{{ isset($item['rate']) && $item['rate'] !== '' ? \App\Support\Money::company((float) ($item['qty'] ?? 1) * (float) $item['rate']) : '' }}</td>
              </tr>
            @endforeach
          </tbody>
          @if ($j->exists)
            <tfoot>
              <tr><td colspan="4" class="text-end text-sm text-ink-500">{{ __('Parts') }}</td><td class="money text-end">{{ \App\Support\Money::company($j->parts_total) }}</td></tr>
              <tr><td colspan="4" class="text-end text-sm text-ink-500">{{ __('Labour') }}</td><td class="money text-end">{{ \App\Support\Money::company($j->labour_total) }}</td></tr>
              <tr><td colspan="4" class="text-end font-semibold">{{ __('Total') }}</td><td class="money text-end font-semibold">{{ \App\Support\Money::company($j->total) }}</td></tr>
            </tfoot>
          @endif
        </table>
      </div>
    </section>
  </fieldset>
  @if ($editable)<div class="flex justify-end"><button class="btn-secondary"><x-icon name="check" /> {{ __('Save') }}</button></div>@endif
</form>

@if ($j->exists && $j->status === 'open')
  <div class="flex justify-end gap-2">
    <form method="POST" action="{{ route('trucks.service.cancel', [$truck, $j]) }}" data-confirm="{{ __('Cancel this job? Its number stays used.') }}">@csrf<button class="btn-danger">{{ __('Cancel job') }}</button></form>
    <form method="POST" action="{{ route('trucks.service.complete', [$truck, $j]) }}" data-confirm="{{ __('Mark the job done? It cannot be changed after that.') }}">@csrf<button class="btn-primary"><x-icon name="check" /> {{ __('Mark done') }}</button></form>
  </div>
@endif
@endsection
