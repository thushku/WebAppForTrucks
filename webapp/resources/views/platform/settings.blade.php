@extends('platform.layout')
@section('title', 'Settings')
@section('content')
<form method="POST" action="{{ route('platform.settings.save') }}" class="card space-y-6 p-6">
  @csrf
  @method('PUT')
  <h2 class="font-display text-xl font-semibold">{{ __('Platform settings') }}</h2>

  <div class="grid gap-5 sm:grid-cols-2">
    <div class="min-w-0">
      <label class="label" for="brand_name">{{ __('Product name') }}</label>
      <input id="brand_name" name="brand_name" class="input" value="{{ old('brand_name', $values['brand_name']) }}" maxlength="60" placeholder="{{ config('app.name') }}">
      <span class="hint">{{ __('Shown on every screen, email and the phone apps.') }}</span>
    </div>
    <div class="min-w-0">
      <label class="label" for="trial_days">{{ __('Free trial days') }}</label>
      <input id="trial_days" name="trial_days" type="number" min="1" max="365" class="input" value="{{ old('trial_days', $values['trial_days'] ?? config('platform.trial_days')) }}" required>
      <span class="hint">{{ __('For new sign-ups. No card is asked for.') }}</span>
    </div>
    <div class="min-w-0">
      <label class="label" for="captcha_mode">{{ __('Sign-in picture') }}</label>
      <select id="captcha_mode" name="captcha_mode" class="input">
        <option value="always" @selected($values['captcha_mode'] === 'always')>{{ __('Every sign-in (safest)') }}</option>
        <option value="after_failures" @selected($values['captcha_mode'] === 'after_failures')>{{ __('Only after 3 failures') }}</option>
      </select>
    </div>
    <div class="min-w-0">
      <label class="label" for="support_email">{{ __('Support email') }}</label>
      <input id="support_email" name="support_email" type="email" class="input" value="{{ old('support_email', $values['support_email']) }}" maxlength="255">
    </div>
    <div class="min-w-0">
      <label class="label" for="support_phone">{{ __('Support phone') }}</label>
      <input id="support_phone" name="support_phone" class="input" value="{{ old('support_phone', $values['support_phone']) }}" maxlength="20">
    </div>
    <div class="min-w-0">
      <label class="label" for="support_whatsapp">{{ __('Support WhatsApp') }}</label>
      <input id="support_whatsapp" name="support_whatsapp" class="input" value="{{ old('support_whatsapp', $values['support_whatsapp']) }}" maxlength="20">
    </div>
  </div>

  <fieldset>
    <legend class="label">{{ __('Languages customers can choose') }}</legend>
    <div class="flex flex-wrap gap-3">
      @foreach ($languages as $code => $name)
        <label class="flex items-center gap-2 rounded-xl border border-ink-100 bg-white px-3 py-2 text-sm">
          <input type="checkbox" name="enabled_languages[]" value="{{ $code }}" class="size-4" @checked(in_array($code, (array) $values['enabled_languages'], true)) @disabled($code === 'en')>
          {{ $name }}
        </label>
      @endforeach
      <input type="hidden" name="enabled_languages[]" value="en">
    </div>
  </fieldset>

  @if ($errors->any())
    <ul class="space-y-1">@foreach ($errors->all() as $e)<li class="error">{{ $e }}</li>@endforeach</ul>
  @endif
  <button type="submit" class="btn-primary">{{ __('Save settings') }}</button>
</form>
@endsection
