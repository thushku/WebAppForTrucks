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

<form method="POST" action="{{ route('platform.settings.website') }}" class="card space-y-6 p-6">
  @csrf
  @method('PUT')
  <div>
    <h2 class="font-display text-xl font-semibold">{{ __('Website') }}</h2>
    <p class="text-sm text-ink-500">{{ __('The texts of About us and the legal pages. Plain text: a line starting “## ” is a heading, lines starting “- ” make a list, a blank line starts a paragraph, :brand is the product name. A box left as shipped keeps following the shipped text.') }}</p>
  </div>
  <div class="min-w-0">
    <label class="label" for="crew_app_url">{{ __('Crew app download link') }}</label>
    <input id="crew_app_url" name="crew_app_url" type="url" class="input" value="{{ old('crew_app_url', $values['crew_app_url']) }}" maxlength="255" placeholder="https://play.google.com/store/apps/details?id=…">
    <span class="hint">{{ __('Shown as a QR code to new companies and on the people pages.') }}</span>
  </div>
  @foreach (\App\Platform\Website\SiteText::PAGES as $page => $label)
    <div class="min-w-0">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="label" for="site_{{ $page }}">{{ __($label) }} <a href="{{ $page === 'about' ? route('site.about') : route('site.legal', $page) }}" target="_blank" class="text-xs font-normal text-brand-700 hover:underline">{{ __('open') }}</a></label>
        @if ($texts->isCustom($page))<label class="flex items-center gap-2 text-xs text-ink-600"><input type="checkbox" name="reset_{{ $page }}" value="1" class="size-4"> {{ __('Use the shipped text again') }}</label>@else<span class="text-xs text-ink-400">{{ __('Shipped text') }}</span>@endif
      </div>
      <textarea id="site_{{ $page }}" name="site_{{ $page }}" rows="8" class="input font-mono text-sm" maxlength="30000">{{ old('site_' . $page, $texts->raw($page)) }}</textarea>
    </div>
  @endforeach
  @if ($errors->any())<ul class="space-y-1">@foreach ($errors->all() as $e)<li class="error">{{ $e }}</li>@endforeach</ul>@endif
  <button type="submit" class="btn-primary">{{ __('Save the website') }}</button>
</form>
@endsection
