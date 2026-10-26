@extends('layouts.app')
@section('title', __('Help'))
@section('content')
<div>
  <h1 class="font-display text-2xl font-semibold">{{ __('Help') }}</h1>
  <p class="text-sm text-ink-500">{{ __('Talk to the :brand team, or read the answers to common questions.', ['brand' => $help['brand']]) }}</p>
</div>

<div class="grid gap-4 sm:grid-cols-3">
  @if ($help['whatsapp_url'])
    <a href="{{ $help['whatsapp_url'] }}" class="card flex items-center gap-3 p-5 hover:border-brand-300" target="_blank" rel="noopener noreferrer"><x-icon name="send" class="size-6 text-brand-700" /><span><span class="block font-semibold">{{ __('WhatsApp support') }}</span><span class="block text-sm text-ink-500">+{{ $help['whatsapp'] }}</span></span></a>
  @endif
  @if ($help['phone'])
    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $help['phone']) }}" class="card flex items-center gap-3 p-5 hover:border-brand-300"><x-icon name="phone" class="size-6 text-brand-700" /><span><span class="block font-semibold">{{ __('Call support') }}</span><span class="block text-sm text-ink-500">{{ $help['phone'] }}</span></span></a>
  @endif
  @if ($help['email'])
    <a href="mailto:{{ $help['email'] }}" class="card flex items-center gap-3 p-5 hover:border-brand-300"><x-icon name="mail" class="size-6 text-brand-700" /><span><span class="block font-semibold">{{ __('Email support') }}</span><span class="block text-sm text-ink-500">{{ $help['email'] }}</span></span></a>
  @endif
  @if (! $help['whatsapp_url'] && ! $help['phone'] && ! $help['email'])
    <a href="{{ $help['contact_url'] }}" class="card flex items-center gap-3 p-5 hover:border-brand-300"><x-icon name="mail" class="size-6 text-brand-700" /><span><span class="block font-semibold">{{ __('Write to us') }}</span><span class="block text-sm text-ink-500">{{ __('The contact form') }}</span></span></a>
  @endif
</div>

<section class="card divide-y divide-ink-100">
  <a href="{{ $help['faq_url'] }}" class="flex items-center justify-between px-5 py-4 hover:bg-ink-50"><span class="flex items-center gap-3"><x-icon name="help" /> {{ __('Questions and answers') }}</span><x-icon name="arrow-right" class="size-4" /></a>
  <a href="{{ $help['terms_url'] }}" class="flex items-center justify-between px-5 py-4 hover:bg-ink-50"><span class="flex items-center gap-3"><x-icon name="file-text" /> {{ __('Terms of service') }}</span><x-icon name="arrow-right" class="size-4" /></a>
  <a href="{{ $help['privacy_url'] }}" class="flex items-center justify-between px-5 py-4 hover:bg-ink-50"><span class="flex items-center gap-3"><x-icon name="shield" /> {{ __('Privacy policy') }}</span><x-icon name="arrow-right" class="size-4" /></a>
</section>
<p class="text-xs text-ink-500">{{ __('Version :version', ['version' => $help['version']]) }}</p>
@endsection
