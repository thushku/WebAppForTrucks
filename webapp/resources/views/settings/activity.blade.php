@extends('layouts.settings')
@section('title', __('Activity'))
@section('settings')
<form method="GET" action="{{ route('settings.activity') }}" class="card flex flex-wrap items-end gap-3 p-5">
  <div><label class="label" for="a_from">{{ __('From') }}</label><input id="a_from" type="date" name="from" value="{{ $filters['from'] }}" class="input"></div>
  <div><label class="label" for="a_to">{{ __('To') }}</label><input id="a_to" type="date" name="to" value="{{ $filters['to'] }}" class="input"></div>
  <div><label class="label" for="a_who">{{ __('Who') }}</label><input id="a_who" name="who" value="{{ $filters['who'] }}" class="input w-44" maxlength="100"></div>
  <div>
    <label class="label" for="a_area">{{ __('What') }}</label>
    <select id="a_area" name="area" class="input w-48">
      <option value="">{{ __('Everything') }}</option>
      @foreach ($areas as $key => [$label])
        <option value="{{ $key }}" @selected($filters['area'] === $key)>{{ __($label) }}</option>
      @endforeach
    </select>
  </div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  @error('to')<span class="error w-full">{{ $message }}</span>@enderror
</form>

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('What') }}</th><th>{{ __('From') }}</th></tr></thead>
      <tbody>
        @forelse ($entries as $entry)
          <tr>
            <td class="nowrap text-xs text-ink-500">{{ $entry->occurred_at?->setTimezone($tz)->format('d M Y, H:i') }}</td>
            <td class="text-sm">{{ $entry->actor_label ?? __('System') }}</td>
            <td class="text-sm">
              @if ($entry->severity !== 'info')<span class="me-1 inline-block size-2 rounded-full {{ $entry->severity === 'sensitive' ? 'bg-overdue' : 'bg-road-500' }}" aria-hidden="true"></span>@endif
              {{ $entry->summary }}
              @if ($entry->reason)<div class="text-xs text-ink-500">{{ __('Reason') }}: {{ $entry->reason }}</div>@endif
            </td>
            <td class="nowrap font-mono text-xs text-ink-400">{{ $entry->ip }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="py-10 text-center text-ink-500">{{ __('Nothing recorded for these filters.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($entries->hasPages())<div class="p-4">{{ $entries->links() }}</div>@endif
</section>
@endsection
