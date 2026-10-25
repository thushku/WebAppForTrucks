@extends('layouts.settings')
@section('title', __('Trip rules'))
@section('settings')
<form method="POST" action="{{ route('settings.rules.update') }}" class="space-y-6" novalidate>
  @csrf @method('PUT')
  @foreach ($groups as $group => $keys)
    <section class="card p-6">
      <h2 class="text-lg font-semibold">{{ __($group) }}</h2>
      <div class="mt-4 divide-y divide-ink-100">
        @foreach ($keys as $key)
          @php([$default, $type, $label] = $defaults[$key])
          <div class="grid gap-2 py-3.5 sm:grid-cols-[1fr_16rem] sm:items-center">
            <label for="r_{{ $key }}" class="text-sm text-ink-900">{{ __($label) }}</label>
            <div>
              @if ($type === 'bool')
                <label class="inline-flex items-center gap-2 text-sm">
                  <input id="r_{{ $key }}" type="checkbox" name="{{ $key }}" value="1" class="size-5 rounded border-ink-300" @checked(old($key, $values[$key]))>
                  <span>{{ __('On') }}</span>
                </label>
              @elseif (isset($choices[$key]))
                <select id="r_{{ $key }}" name="{{ $key }}" class="input">
                  @foreach ($choices[$key] as $value => $text)
                    <option value="{{ $value }}" @selected(old($key, $values[$key]) === $value)>{{ __($text) }}</option>
                  @endforeach
                </select>
              @else
                <input id="r_{{ $key }}" name="{{ $key }}" type="number" min="0" step="{{ $type === 'money' ? '0.01' : '1' }}" class="input" value="{{ old($key, $values[$key]) }}" inputmode="decimal">
              @endif
              @error($key)<span class="error">{{ $message }}</span>@enderror
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endforeach
  <div class="flex justify-end"><button type="submit" class="btn-primary"><x-icon name="check" /> {{ __('Save rules') }}</button></div>
</form>
@endsection
