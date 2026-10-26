{{-- The person's leave this year by type, and the latest requests (M40). --}}
@php($tones = ['waiting' => 'bg-amber-50 text-partial', 'approved' => 'bg-emerald-50 text-paid', 'rejected' => 'bg-red-50 text-overdue', 'cancelled' => 'bg-ink-100 text-ink-500'])
<section id="leave" class="card p-6" data-leave>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h3 class="font-semibold">{{ __('Leave') }}</h3>
      <p class="text-sm text-ink-500">{{ __('This financial year, by type: days left of the year’s, after what is taken and asked for.') }}</p>
    </div>
    @canany(['leave.approve', 'people.manage'])@if ($person->status !== 'left')<a href="{{ route('people.leave', ['person' => $person->id]) }}#record" class="btn-secondary"><x-icon name="plus" /> {{ __('Record leave') }}</a>@endif @endcanany
  </div>
  <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach ($leave as $b)
      <div class="rounded-xl border border-ink-100 p-3 text-sm">
        <div class="truncate text-ink-500">{{ $b['name'] }}</div>
        @if ($b['paid'])
          <div class="text-lg font-semibold">{{ __(':left of :days left', ['left' => rtrim(rtrim(number_format($b['left'], 1), '0'), '.'), 'days' => rtrim(rtrim(number_format($b['entitled'], 1), '0'), '.')]) }}</div>
        @else
          <div class="text-lg font-semibold">{{ trans_choice(':count day taken|:count days taken', $b['taken']) }}</div>
        @endif
        <div class="text-xs text-ink-500">{{ __('Approved :n', ['n' => $b['taken']]) }}@if ($b['waiting']) · {{ __('asked :n', ['n' => $b['waiting']]) }}@endif</div>
      </div>
    @endforeach
  </div>
  @if ($leaveRequests->isNotEmpty())
    <ul class="mt-4 divide-y divide-ink-100 text-sm">
      @foreach ($leaveRequests as $r)
        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
          <span>{{ $r->from_on->format('d M') }}{{ $r->to_on->isSameDay($r->from_on) ? '' : ' – ' . $r->to_on->format('d M') }} {{ $r->from_on->format('Y') }} · {{ app(\App\Modules\People\Services\Leave::class)->typeName($r->leave_type) }} · {{ trans_choice(':count day|:count days', $r->days) }}@if ($r->reason)<span class="text-ink-500"> · {{ $r->reason }}</span>@endif</span>
          <span class="pill {{ $tones[$r->status] ?? '' }}">{{ __(\App\Modules\People\Models\LeaveRequest::STATUSES[$r->status]) }}</span>
        </li>
      @endforeach
    </ul>
  @endif
</section>
