@extends('layouts.app')
@section('title', __('Leave'))
@section('content')
@php
  $tones = ['waiting' => 'bg-amber-50 text-partial', 'approved' => 'bg-emerald-50 text-paid', 'rejected' => 'bg-red-50 text-overdue', 'cancelled' => 'bg-ink-100 text-ink-500'];
  $tabs = ['' => __('All')] + array_map(fn ($l) => __($l), \App\Modules\People\Models\LeaveRequest::STATUSES);
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('people.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('People') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('Leave') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Leave asked for from the crew app or recorded here. Approved leave counts against the year’s days; unpaid leave comes off a monthly salary in the pay run; a trip cannot be sent to someone on leave without a reason.') }}</p>
  </div>
  <a href="#record" class="btn-primary"><x-icon name="plus" /> {{ __('Record leave') }}</a>
</div>

@if ($onLeave->isNotEmpty())
  <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    <span class="font-semibold">{{ __('On leave today:') }}</span>
    {{ $onLeave->map(fn ($r) => $people->firstWhere('id', $r->person_id)?->name . ' (' . __('till :date', ['date' => $r->to_on->format('d M')]) . ')')->join(', ') }}
  </section>
@endif

<div class="flex flex-wrap gap-2" role="tablist">
  @foreach ($tabs as $key => $label)
    <a href="{{ route('people.leave', ['status' => $key ?: 'all']) }}" role="tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $status === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }}@if ($key !== '') <span class="text-xs text-ink-400">{{ $counts[$key] ?? 0 }}</span>@endif</a>
  @endforeach
</div>

<section class="card overflow-hidden">
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Leave') }}</th><th>{{ __('Days') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th class="text-end"><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
      <tbody>
        @forelse ($requests as $r)
          <tr>
            <td class="text-sm"><a href="{{ route('people.show', $r->person_id) }}#leave" class="font-medium hover:text-brand-700">{{ $r->person?->name }}</a><div class="text-xs text-ink-500">{{ $r->asked_via === 'app' ? __('From the app') : __('Recorded here') }}</div></td>
            <td class="whitespace-nowrap text-sm">{{ $r->from_on->format('d M') }}{{ $r->to_on->isSameDay($r->from_on) ? '' : ' – ' . $r->to_on->format('d M') }} {{ $r->to_on->format('Y') }}<div class="text-xs text-ink-500">{{ $service->typeName($r->leave_type) }}</div></td>
            <td class="text-sm">{{ $r->days }}</td>
            <td class="text-sm">{{ $r->reason ?? '—' }}@if ($r->decision_note)<div class="text-xs text-ink-500">{{ $r->decision_note }}</div>@endif</td>
            <td class="text-sm"><span class="pill {{ $tones[$r->status] ?? '' }}">{{ __(\App\Modules\People\Models\LeaveRequest::STATUSES[$r->status]) }}</span>@if ($r->decider)<div class="text-xs text-ink-500">{{ $r->decider->name }}</div>@endif</td>
            <td class="text-end text-sm">
              @can('leave.approve')
                @if ($r->status === 'waiting')
                  <form method="POST" action="{{ route('people.leave.decide', $r) }}" class="flex flex-wrap justify-end gap-1.5">@csrf
                    <input name="note" class="input w-36 py-1.5 text-sm" maxlength="255" placeholder="{{ __('Note (optional)') }}" aria-label="{{ __('Note (optional)') }}">
                    <button name="decision" value="approve" class="btn-primary py-1.5 text-sm">{{ __('Approve') }}</button>
                    <button name="decision" value="reject" class="btn-secondary py-1.5 text-sm">{{ __('Turn down') }}</button>
                  </form>
                @endif
                @if ($r->status === 'waiting' || ($r->status === 'approved' && $r->to_on->toDateString() >= $today->toDateString()))
                  <form method="POST" action="{{ route('people.leave.cancel', $r) }}" class="mt-1" onsubmit="return confirm(@js(__('Cancel this leave?')))">@csrf<button class="text-xs text-ink-500 hover:text-overdue hover:underline">{{ __('Cancel') }}</button></form>
                @endif
              @endcan
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-10 text-center text-ink-500">{{ __('No leave here.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($requests->hasPages())<div class="border-t border-ink-100 px-6 py-3">{{ $requests->links() }}</div>@endif
</section>

<form id="record" method="POST" action="{{ route('people.leave.store') }}" class="card grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4" novalidate>@csrf
  <div class="sm:col-span-2 xl:col-span-4">
    <h2 class="text-lg font-semibold">{{ __('Record leave') }}</h2>
    <p class="text-sm text-ink-500">@can('leave.approve'){{ __('Recorded by you, it is approved at once.') }}@else{{ __('It waits for someone who approves leave.') }}@endcan</p>
  </div>
  <div><label class="label" for="lv_person">{{ __('Person') }}</label><select id="lv_person" name="person_id" class="input" required><option value="">{{ __('Choose') }}</option>@foreach ($people as $p)<option value="{{ $p->id }}" @selected((int) old('person_id', $chosen) === $p->id)>{{ $p->name }}</option>@endforeach</select>@error('person_id')<span class="error">{{ $message }}</span>@enderror @error('person')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="lv_type">{{ __('Leave type') }}</label><select id="lv_type" name="leave_type" class="input">@foreach ($types as $code => $t)<option value="{{ $code }}" @selected(old('leave_type') === $code)>{{ $t['name'] }}</option>@endforeach</select>@error('leave_type')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="lv_from">{{ __('From') }}</label><input id="lv_from" type="date" name="from_on" value="{{ old('from_on', $today->toDateString()) }}" class="input" required>@error('from_on')<span class="error">{{ $message }}</span>@enderror</div>
  <div><label class="label" for="lv_to">{{ __('To') }}</label><input id="lv_to" type="date" name="to_on" value="{{ old('to_on', $today->toDateString()) }}" class="input" required>@error('to_on')<span class="error">{{ $message }}</span>@enderror</div>
  <div class="sm:col-span-2 xl:col-span-3"><label class="label" for="lv_reason">{{ __('Reason') }}</label><input id="lv_reason" name="reason" value="{{ old('reason') }}" class="input" maxlength="255" placeholder="{{ __('Family function, fever…') }}"></div>
  <div class="flex items-end justify-end"><button class="btn-primary"><x-icon name="check" /> {{ __('Record') }}</button></div>
</form>
@endsection
