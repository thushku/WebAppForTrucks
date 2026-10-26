@extends('layouts.app')
@section('title', __('LR book :book', ['book' => $book->book_no]))
@section('content')
@php
  $r = $report;
  $tones = ['used' => 'bg-emerald-50 text-paid border-emerald-200', 'cancelled' => 'bg-ink-100 text-ink-500 border-ink-200 line-through', 'lost' => 'bg-red-50 text-overdue border-red-200', 'gap' => 'bg-amber-50 text-partial border-amber-300', 'free' => 'bg-white text-ink-500 border-ink-100'];
  $words = ['used' => __('Used'), 'cancelled' => __('Cancelled'), 'lost' => __('Lost'), 'gap' => __('Skipped'), 'free' => __('Unused')];
@endphp
<div class="flex flex-wrap items-start justify-between gap-3">
  <div>
    <a href="{{ route('lr-books.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('LR books') }}</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ __('LR book :book', ['book' => $book->book_no]) }} <span class="font-mono text-base font-normal text-ink-500">{{ $book->rangeLabel() }}</span></h1>
    <p class="text-sm text-ink-600">{{ __('Given to :who on :date', ['who' => $book->issued_to ?: '—', 'date' => $book->issued_on->format('d M Y')]) }}@if ($book->notes) · {{ $book->notes }}@endif</p>
  </div>
  @can('lr.manage')
    <form method="POST" action="{{ route('lr-books.status', $book) }}">@csrf<input type="hidden" name="status" value="{{ $book->status === 'active' ? 'closed' : 'active' }}"><button class="btn-secondary">{{ $book->status === 'active' ? __('Close the book') : __('Use it again') }}</button></form>
  @endcan
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
  @foreach (['used' => $r['used'], 'free' => $r['free'], 'gap' => $r['gaps']->count(), 'cancelled' => $r['cancelled'], 'lost' => $r['lost']] as $key => $n)
    <div class="stat"><div class="stat-label">{{ $key === 'gap' ? __('Skipped (free below the last used)') : $words[$key] }}</div><div class="stat-value text-2xl">{{ $n }}</div></div>
  @endforeach
</div>
@if ($r['gaps']->isNotEmpty())
  <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('Leaves skipped so far: :list. An LR may have been written on one and not entered — find it, or mark the leaf cancelled or lost.', ['list' => $r['gaps']->take(30)->join(', ') . ($r['gaps']->count() > 30 ? ' …' : '')]) }}</p>
@endif

@can('lr.manage')
  <form method="POST" action="{{ route('lr-books.mark', $book) }}" class="card flex flex-wrap items-end gap-3 p-5">
    @csrf
    <div><label class="label" for="lf_no">{{ __('Leaf') }}</label><input id="lf_no" name="leaf_no" type="number" min="{{ $book->first_no }}" max="{{ $book->last_no }}" class="input w-32" value="{{ old('leaf_no') }}" required>@error('leaf_no')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="lf_status">{{ __('Mark it') }}</label><select id="lf_status" name="status" class="input">@foreach (\App\Modules\Trips\Models\LrBookLeaf::STATUSES as $v => $label)<option value="{{ $v }}">{{ __($label) }}</option>@endforeach</select></div>
    <div class="min-w-56 flex-1"><label class="label" for="lf_reason">{{ __('Why') }}</label><input id="lf_reason" name="reason" class="input" maxlength="255" value="{{ old('reason') }}" required placeholder="{{ __('Written wrong, torn, book lost on the road…') }}">@error('reason')<span class="error">{{ $message }}</span>@enderror</div>
    <button class="btn-secondary">{{ __('Mark the leaf') }}</button>
  </form>
@endcan

<section class="card p-5">
  <div class="mb-3 flex flex-wrap gap-3 text-xs">
    @foreach ($words as $key => $label)<span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded border {{ $tones[$key] }}"></span> {{ $label }}</span>@endforeach
  </div>
  <div class="grid grid-cols-[repeat(auto-fill,minmax(5.5rem,1fr))] gap-1.5">
    @foreach ($r['leaves'] as $leaf)
      @if ($leaf->load)
        <a href="{{ route('trips.show', $leaf->load->trip_id) }}" class="rounded-lg border px-2 py-1.5 text-center font-mono text-xs {{ $tones['used'] }}" title="{{ $leaf->load->trip?->label }}{{ $leaf->load->status === 'cancelled' ? ' · ' . __('LR cancelled') : '' }}">{{ $leaf->label }}</a>
      @elseif ($leaf->mark)
        <span class="flex items-center justify-between gap-1 rounded-lg border px-2 py-1.5 font-mono text-xs {{ $tones[$leaf->state] }}" title="{{ $leaf->mark->reason }}">{{ $leaf->label }}
          @can('lr.manage')<form method="POST" action="{{ route('lr-books.unmark', [$book, $leaf->mark->id]) }}">@csrf @method('DELETE')<button class="no-underline" aria-label="{{ __('Free leaf :no again', ['no' => $leaf->label]) }}">×</button></form>@endcan</span>
      @else
        <span class="rounded-lg border px-2 py-1.5 text-center font-mono text-xs {{ $tones[$leaf->state] }}">{{ $leaf->label }}</span>
      @endif
    @endforeach
  </div>
</section>
@endsection
