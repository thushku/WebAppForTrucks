@extends('layouts.site')
@section('title', __('Questions and answers'))
@section('description', __('Answers about the crew app, offline use, GPS, GST and VAT, data safety, the free trial and payment.'))
@section('content')
<section class="site-hero">
  <div class="mx-auto max-w-4xl px-4 pb-24 pt-14 text-center sm:px-6">
    <span class="site-kicker">{{ __('FAQ') }}</span>
    <h1 class="mt-4 font-display text-4xl font-semibold leading-tight sm:text-5xl">{{ __('Questions and answers') }}</h1>
    <div class="relative mx-auto mt-8 max-w-xl">
      <x-icon name="search" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-ink-400" />
      <input type="search" class="input rounded-2xl py-3.5 ps-12 text-base" placeholder="{{ __('Search the questions, e.g. offline, GST, driver') }}" aria-label="{{ __('Search the questions') }}" data-faq-search aria-controls="faq-list">
    </div>
  </div>
</section>

<div id="faq-list" class="mx-auto max-w-4xl space-y-10 px-4 py-16 sm:px-6">
  @foreach ($groups as $group => $items)
    <section data-faq-group>
      <h2 class="font-display text-2xl font-semibold">{{ $group }}</h2>
      <div class="mt-4 space-y-3">
        @foreach ($items as [$q, $a])
          <details class="site-card p-5" data-faq-item>
            <summary class="flex cursor-pointer list-none items-start justify-between gap-4 font-semibold">{{ $q }} <x-icon name="plus" class="size-5 shrink-0 text-ink-400" /></summary>
            <p class="mt-3 text-[15px] leading-7 text-ink-700">{{ $a }}</p>
          </details>
        @endforeach
      </div>
    </section>
  @endforeach
  <p class="hidden text-center text-ink-500" data-faq-empty role="status">{{ __('No question matches. Ask us on the contact page.') }}</p>
  <div class="site-card flex flex-wrap items-center justify-between gap-4">
    <p class="font-semibold">{{ __('Still have a question?') }}</p>
    <a href="{{ route('site.contact') }}" class="btn-primary">{{ __('Ask us') }}</a>
  </div>
</div>
@endsection
