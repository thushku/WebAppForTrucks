@extends('layouts.app')
@section('title', __('LR books'))
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-semibold">{{ __('LR books') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Pre-printed LR books handed to a branch, an agent or a driver. A typed LR number is taken only from a book in use, once, and never from a cancelled or lost leaf. Loads without a typed number get the automatic series.') }}</p>
  </div>
  <a href="{{ route('invoices.index') }}" class="btn-secondary">{{ __('Bills') }}</a>
</div>

@can('lr.manage')
  <form method="POST" action="{{ route('lr-books.store') }}" class="card grid gap-4 p-5 sm:grid-cols-4 lg:grid-cols-8">
    @csrf
    <div><label class="label" for="lb_no">{{ __('Book no.') }}</label><input id="lb_no" name="book_no" class="input" value="{{ old('book_no') }}" maxlength="30" required>@error('book_no')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="lb_prefix">{{ __('Printed before the number') }}</label><input id="lb_prefix" name="prefix" class="input font-mono" value="{{ old('prefix') }}" maxlength="10" placeholder="A-">@error('prefix')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="lb_first">{{ __('First leaf') }}</label><input id="lb_first" name="first_no" type="number" min="1" class="input" value="{{ old('first_no') }}" required>@error('first_no')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="lb_last">{{ __('Last leaf') }}</label><input id="lb_last" name="last_no" type="number" min="1" class="input" value="{{ old('last_no') }}" required>@error('last_no')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="lb_branch">{{ __('Branch') }}</label><select id="lb_branch" name="branch_id" class="input"><option value="">—</option>@foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((int) old('branch_id') === $id)>{{ $name }}</option>@endforeach</select></div>
    <div><label class="label" for="lb_to">{{ __('Given to') }}</label><input id="lb_to" name="issued_to" class="input" value="{{ old('issued_to') }}" maxlength="120" placeholder="{{ __('Agent, driver or desk') }}"></div>
    <div><label class="label" for="lb_on">{{ __('On') }}</label><input id="lb_on" name="issued_on" type="date" class="input" value="{{ old('issued_on', $today) }}" required></div>
    <div class="flex items-end"><button class="btn-primary w-full"><x-icon name="plus" /> {{ __('Add book') }}</button></div>
  </form>
@endcan

<section class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>{{ __('Book') }}</th><th>{{ __('Leaves') }}</th><th>{{ __('Given to') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end">{{ __('Cancelled or lost') }}</th><th class="text-end">{{ __('Free') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
      @forelse ($books as $b)
        <tr class="{{ $b->status === 'closed' ? 'opacity-70' : '' }}">
          <td><a href="{{ route('lr-books.show', $b) }}" class="font-semibold text-brand-700 hover:underline">{{ $b->book_no }}</a></td>
          <td class="font-mono text-sm">{{ $b->rangeLabel() }}</td>
          <td class="text-sm">{{ $b->issued_to ?: '—' }} <span class="text-ink-500">· {{ $b->issued_on->format('d M Y') }}</span></td>
          <td class="text-end">{{ $b->consignments_count }}</td>
          <td class="text-end">{{ $b->leaves_count }}</td>
          <td class="text-end">{{ $b->size() - $b->consignments_count - $b->leaves_count }}</td>
          <td><span class="pill {{ $b->status === 'active' ? 'bg-emerald-50 text-paid' : 'bg-ink-100 text-ink-500' }}">{{ __(\App\Modules\Trips\Models\LrBook::STATUSES[$b->status]) }}</span></td>
        </tr>
      @empty
        <tr><td colspan="7" class="py-8 text-center text-sm text-ink-500">{{ __('No LR books. Add one when the office uses pre-printed LRs; without books every LR gets the automatic number.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
@endsection
