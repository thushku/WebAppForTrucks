@extends('layouts.app')
@section('title', __('My notifications'))
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('notifications.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Notifications') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('My notifications') }}</h1>
    <p class="text-sm text-ink-500">{{ __('What reaches your phone in this company. The bell and the app’s inbox keep every notice, whatever you choose here.') }}</p>
  </div>
</div>

<form method="POST" action="{{ route('notifications.settings.update') }}" class="space-y-6">
  @csrf
  @method('PUT')
  <section class="card divide-y divide-ink-100">
    @foreach (\App\Platform\Notifications\NotificationSettings::GROUPS as $key => [$name, $about])
      <label class="flex items-center justify-between gap-4 px-5 py-4">
        <span><span class="block font-medium">{{ __($name) }}</span><span class="block text-sm text-ink-500">{{ __($about) }}</span></span>
        <input type="checkbox" name="push[]" value="{{ $key }}" class="size-5 rounded border-ink-300" @checked(! in_array($key, $settings['muted'], true)) aria-label="{{ __('Push :group to my phone', ['group' => __($name)]) }}">
      </label>
    @endforeach
  </section>

  <section class="card space-y-3 p-5">
    <h2 class="font-semibold">{{ __('Quiet hours') }}</h2>
    <p class="text-sm text-ink-500">{{ __('Between these times only high-priority notices (a trip sent, an incident, a second approval) ring your phone; the rest wait in the inbox.') }}</p>
    <div class="flex flex-wrap items-end gap-3">
      <div><label class="label" for="ns_from">{{ __('From') }}</label><input id="ns_from" name="quiet_from" type="time" class="input" value="{{ old('quiet_from', $settings['quiet_from']) }}">@error('quiet_from')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="ns_to">{{ __('To') }}</label><input id="ns_to" name="quiet_to" type="time" class="input" value="{{ old('quiet_to', $settings['quiet_to']) }}">@error('quiet_to')<span class="error">{{ $message }}</span>@enderror</div>
      <p class="text-xs text-ink-500">{{ __('Leave both empty for no quiet hours.') }}</p>
    </div>
  </section>

  <section class="card space-y-3 p-5">
    <h2 class="font-semibold">{{ __('Morning summary') }}</h2>
    <p class="text-sm text-ink-500">{{ __('At 7 in the morning: trips started and ended, money billed, received and paid, spending on the road, what waits for you and who owes the most — by email and in the inbox.') }}</p>
    <div class="flex flex-wrap gap-2">
      @foreach (\App\Platform\Notifications\NotificationSettings::DIGESTS as $value => $label)
        <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-ink-200 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
          <input type="radio" name="digest" value="{{ $value }}" class="size-4" @checked(old('digest', $settings['digest']) === $value)> {{ __($label) }}
        </label>
      @endforeach
    </div>
    @error('digest')<span class="error">{{ $message }}</span>@enderror
  </section>

  <div class="flex justify-end"><button class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button></div>
</form>
@endsection
