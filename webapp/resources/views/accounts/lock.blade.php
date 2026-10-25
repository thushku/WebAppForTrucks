@extends('layouts.app')
@section('title', __('Lock the books'))
@section('content')
@include('accounts._nav')
<section class="card p-6">
  <h2 class="text-lg font-semibold">{{ __('Lock the books') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('Once a month is checked (and the GST return filed), lock it. Nothing dated on or before the lock date changes any more: a late entry, a voided bill or a reversal is posted on the first open day, with its own date shown beside it.') }}</p>
  <p class="mt-3 text-sm font-medium">{{ $lockedUntil ? __('Locked up to :date.', ['date' => $lockedUntil->format('d M Y')]) : __('Not locked.') }}</p>
  <form method="POST" action="{{ route('accounts.lock.update') }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf @method('PUT')
    <div><label class="label" for="lk_date">{{ __('Lock up to') }}</label><input id="lk_date" type="date" name="locked_until" value="{{ old('locked_until', $lockedUntil?->toDateString() ?? $today->subMonthNoOverflow()->endOfMonth()->toDateString()) }}" max="{{ $today->toDateString() }}" class="input">@error('locked_until')<span class="error">{{ $message }}</span>@enderror</div>
    <div class="min-w-64 flex-1"><label class="label" for="lk_reason">{{ __('Reason (needed to open locked days again)') }}</label><input id="lk_reason" name="reason" value="{{ old('reason') }}" class="input" maxlength="255">@error('reason')<span class="error">{{ $message }}</span>@enderror</div>
    <button class="btn-primary"><x-icon name="lock" class="size-4" /> {{ __('Save the lock') }}</button>
    @if ($lockedUntil)<button name="locked_until" value="" class="btn-ghost text-sm">{{ __('Unlock') }}</button>@endif
  </form>
</section>
<section class="card overflow-hidden">
  <h3 class="px-6 pt-6 font-semibold">{{ __('History') }}</h3>
  <table class="table mt-2">
    <tbody>
      @forelse ($history as $h)
        <tr><td class="text-sm">{{ $h->created_at?->format('d M Y, H:i') }}</td><td class="text-sm">{{ $h->locked_until ? __('Locked up to :d', ['d' => $h->locked_until->format('d M Y')]) : __('Unlocked') }}</td><td class="text-sm">{{ $h->author?->name }}</td><td class="text-sm text-ink-500">{{ $h->reason }}</td></tr>
      @empty
        <tr><td class="py-6 text-center text-sm text-ink-500">{{ __('Never locked.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
@endsection
