@extends('layouts.app')
@section('title', $complaint->number)
@section('content')
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
  <div class="space-y-6">
    <section class="card p-6">
      <div class="flex flex-wrap items-start gap-3">
        <div class="min-w-0 flex-1">
          <p class="text-sm text-ink-500"><a href="{{ route('trucks.show', [$complaint->truck, 'tab' => 'complaints']) }}" class="docno font-mono font-semibold text-brand-700 hover:underline">{{ $complaint->truck->reg_no }}</a> · {{ $complaint->number }}</p>
          <h2 class="mt-1 text-xl font-semibold">{{ $complaint->title }}</h2>
          <p class="mt-1 text-sm text-ink-500">{{ __('Raised :when by :who', ['when' => $complaint->created_at->format('d M Y, H:i'), 'who' => $complaint->raisedBy?->name ?? __('the crew')]) }}{{ $complaint->category ? ' · ' . ($categories[$complaint->category] ?? $complaint->category) : '' }}{{ $complaint->odometer_km ? ' · ' . number_format($complaint->odometer_km) . ' km' : '' }}</p>
        </div>
        <span class="pill bg-ink-100 text-ink-700">{{ __(\App\Modules\Fleet\Models\Complaint::STATUSES[$complaint->status]) }}</span>
        <span class="pill {{ ['urgent' => 'bg-red-50 text-overdue', 'high' => 'bg-amber-50 text-partial'][$complaint->priority] ?? 'bg-ink-100 text-ink-700' }}">{{ __(\App\Modules\Fleet\Models\Complaint::PRIORITIES[$complaint->priority]) }}</span>
      </div>
      @if ($complaint->description)<p class="mt-4 whitespace-pre-line text-sm">{{ $complaint->description }}</p>@endif
    </section>

    <section class="card p-6">
      <h3 class="font-semibold">{{ __('History') }}</h3>
      <ol class="mt-4 space-y-4 border-s border-ink-100 ps-5">
        @foreach ($complaint->updates as $u)
          <li class="relative">
            <span class="absolute -start-[1.6rem] top-1 size-2.5 rounded-full bg-brand-400" aria-hidden="true"></span>
            <div class="text-xs text-ink-500">{{ $u->created_at->format('d M Y, H:i') }} · {{ $u->account?->name ?? __('System') }}</div>
            @if ($u->status_to)
              <div class="text-sm font-medium">{{ $u->status_from ? __(':from → :to', ['from' => __(\App\Modules\Fleet\Models\Complaint::STATUSES[$u->status_from] ?? $u->status_from), 'to' => __(\App\Modules\Fleet\Models\Complaint::STATUSES[$u->status_to] ?? $u->status_to)]) : __('Raised') }}</div>
            @endif
            @if ($u->note)<p class="mt-0.5 whitespace-pre-line text-sm text-ink-700">{{ $u->note }}</p>@endif
          </li>
        @endforeach
      </ol>
    </section>
  </div>

  @can('complaint.manage')
  <form method="POST" action="{{ route('complaints.update', $complaint) }}" class="card h-fit space-y-4 p-6" novalidate>
    @csrf @method('PUT')
    <h3 class="font-semibold">{{ __('Work on it') }}</h3>
    <div>
      <label class="label" for="cs_status">{{ __('Status') }}</label>
      <select id="cs_status" name="status" class="input">
        <option value="{{ $complaint->status }}">{{ __(\App\Modules\Fleet\Models\Complaint::STATUSES[$complaint->status]) }} ({{ __('now') }})</option>
        @foreach (\App\Modules\Fleet\Models\Complaint::NEXT[$complaint->status] as $next)<option value="{{ $next }}">{{ __(\App\Modules\Fleet\Models\Complaint::STATUSES[$next]) }}</option>@endforeach
      </select>
      @error('status')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="cs_assign">{{ __('Who is working on it') }}</label>
      <select id="cs_assign" name="assigned_to" class="input"><option value="">—</option>@foreach ($staff as $id => $name)<option value="{{ $id }}" @selected($complaint->assigned_to === $id)>{{ $name }}</option>@endforeach</select>
    </div>
    <div>
      <label class="label" for="cs_vendor">{{ __('Workshop') }}</label>
      <select id="cs_vendor" name="vendor_party_id" class="input"><option value="">—</option>@foreach ($vendors as $id => $name)<option value="{{ $id }}" @selected($complaint->vendor_party_id === $id)>{{ $name }}</option>@endforeach</select>
    </div>
    <div>
      <label class="label" for="cs_note">{{ __('Note') }}</label>
      <textarea id="cs_note" name="note" rows="3" class="input" maxlength="3000"></textarea>
      @error('note')<span class="error">{{ $message }}</span>@enderror
    </div>
    <button class="btn-primary w-full"><x-icon name="check" /> {{ __('Save') }}</button>
    <a href="{{ route('trucks.service.create', $complaint->truck) }}" class="btn-ghost w-full text-sm">{{ __('Open a service job for this truck') }}</a>
  </form>
  @endcan
</div>
@endsection
