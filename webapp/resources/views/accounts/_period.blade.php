{{-- The period of a book page: from / to (or "as on" for a balance), this financial year by default. --}}
<form method="GET" class="card flex flex-wrap items-end gap-3 p-4">
  @foreach ($keep ?? [] as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
  @unless ($asOn ?? false)
    <div><label class="label" for="p_from">{{ __('From date') }}</label><input id="p_from" type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
  @endunless
  <div><label class="label" for="p_to">{{ ($asOn ?? false) ? __('As on') : __('To date') }}</label><input id="p_to" type="date" name="to" value="{{ $to->toDateString() }}" class="input"></div>
  <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  <span class="text-xs text-ink-500">{{ $fy->label() }}</span>
  @if ($csv ?? null)<a href="{{ $csv }}" class="btn-ghost ms-auto text-sm"><x-icon name="download" class="size-4" /> {{ __('Excel (CSV)') }}</a>@endif
</form>
@error('to')<p class="error">{{ $message }}</p>@enderror
