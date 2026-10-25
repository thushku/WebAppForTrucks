@extends('layouts.site')
@section('title', __('Contact us'))
@section('description', __('Ask for a demo, a price or help. A person reads every message.'))
@section('content')
<section class="site-hero">
  <div class="mx-auto max-w-7xl px-4 pb-24 pt-14 sm:px-6">
    <span class="site-kicker">{{ __('Contact') }}</span>
    <h1 class="mt-4 max-w-2xl font-display text-4xl font-semibold leading-tight sm:text-5xl">{{ __('Talk to a person, not a bot') }}</h1>
    <p class="mt-4 max-w-xl text-lg text-white/80">{{ __('A demo, a price for your fleet, or help with your account — write to us and we answer the same working day.') }}</p>
  </div>
</section>

<div class="mx-auto -mt-14 grid max-w-7xl gap-6 px-4 pb-24 sm:px-6 lg:grid-cols-[1.4fr_1fr]">
  <form method="POST" action="{{ route('site.contact.send') }}" class="site-card relative space-y-4 p-6 sm:p-8" novalidate>
    @csrf
    @if (session('status'))
      <div class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    {{-- A field people never see: robots fill it in, people do not. --}}
    <div class="absolute -start-[9999px]" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="name">{{ __('Your name') }}</label><input id="name" name="name" class="input" value="{{ old('name') }}" maxlength="120" autocomplete="name" required>@error('name')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="company">{{ __('Company') }} <span class="text-ink-400">({{ __('optional') }})</span></label><input id="company" name="company" class="input" value="{{ old('company') }}" maxlength="150" autocomplete="organization">@error('company')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="email">{{ __('Email') }}</label><input id="email" name="email" type="email" class="input" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>@error('email')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="mobile">{{ __('Mobile or WhatsApp') }} <span class="text-ink-400">({{ __('optional') }})</span></label><input id="mobile" name="mobile" type="tel" class="input" value="{{ old('mobile') }}" maxlength="20" autocomplete="tel">@error('mobile')<span class="error">{{ $message }}</span>@enderror</div>
    </div>
    <div><label class="label" for="topic">{{ __('About') }}</label>
      <select id="topic" name="topic" class="input">@foreach (\App\Platform\Website\ContactMessage::TOPICS as $key => $label)<option value="{{ $key }}" @selected(old('topic', request('topic')) === $key)>{{ __($label) }}</option>@endforeach</select></div>
    <div><label class="label" for="message">{{ __('Message') }}</label><textarea id="message" name="message" rows="5" class="input" maxlength="3000" required>{{ old('message') }}</textarea>@error('message')<span class="error">{{ $message }}</span>@enderror</div>
    @include('partials.captcha')
    <button class="btn-primary btn-lg">{{ __('Send the message') }} <x-icon name="send" /></button>
    <p class="text-xs text-ink-500">{{ __('We use your details only to answer you.') }} <a href="{{ route('site.legal', 'privacy') }}" class="underline">{{ __('Privacy policy') }}</a></p>
  </form>

  <aside class="space-y-4">
    @if ($contact['whatsapp'])
      <a href="https://wa.me/{{ preg_replace('/\D+/', '', $contact['whatsapp']) }}" target="_blank" rel="noopener" class="site-card flex items-center gap-4 hover:border-brand-200">
        <span class="grid size-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><x-icon name="phone" class="size-5" /></span>
        <span><span class="block font-semibold">{{ __('WhatsApp') }}</span><span class="text-sm text-ink-600">{{ $contact['whatsapp'] }}</span></span>
      </a>
    @endif
    @if ($contact['phone'])
      <a href="tel:{{ preg_replace('/[^+0-9]/', '', $contact['phone']) }}" class="site-card flex items-center gap-4 hover:border-brand-200">
        <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon name="phone" class="size-5" /></span>
        <span><span class="block font-semibold">{{ __('Phone') }}</span><span class="text-sm text-ink-600">{{ $contact['phone'] }}</span></span>
      </a>
    @endif
    @if ($contact['email'])
      <a href="mailto:{{ $contact['email'] }}" class="site-card flex items-center gap-4 hover:border-brand-200">
        <span class="grid size-11 place-items-center rounded-xl bg-violet-50 text-violet-700"><x-icon name="mail" class="size-5" /></span>
        <span><span class="block font-semibold">{{ __('Email') }}</span><span class="text-sm text-ink-600">{{ $contact['email'] }}</span></span>
      </a>
    @endif
    <div class="site-card">
      <h2 class="font-semibold">{{ __('Already a customer?') }}</h2>
      <p class="mt-1 text-sm text-ink-600">{{ __('Sign in and open Settings → Plan and billing to renew, or write to us with your company name.') }}</p>
      <a href="{{ route('login') }}" class="mt-3 inline-block font-semibold text-brand-700 hover:underline">{{ __('Sign in') }} →</a>
    </div>
  </aside>
</div>
@endsection
