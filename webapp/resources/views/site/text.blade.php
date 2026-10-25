@extends('layouts.site')
@section('title', $title)
@section('content')
<section class="site-hero">
  <div class="mx-auto max-w-4xl px-4 pb-20 pt-14 sm:px-6">
    <span class="site-kicker">{{ $page === 'about' ? __('About us') : __('Legal') }}</span>
    <h1 class="mt-4 font-display text-4xl font-semibold leading-tight sm:text-5xl">{{ $title }}</h1>
    @if ($updated)<p class="mt-3 text-sm text-white/70">{{ __('Last updated :date', ['date' => \Carbon\CarbonImmutable::parse($updated)->translatedFormat('j F Y')]) }}</p>@endif
  </div>
</section>
<article class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
  <div class="site-prose">{{ $html }}</div>
  @if ($page === 'about')
    <div class="mt-12 flex flex-wrap gap-3">
      <a href="{{ route('signup') }}" class="btn-primary btn-lg">{{ __('Start free trial') }}</a>
      <a href="{{ route('site.contact') }}" class="btn-secondary btn-lg">{{ __('Contact us') }}</a>
    </div>
  @endif
</article>
@endsection
