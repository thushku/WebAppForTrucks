@extends('layouts.guest')
@section('title', __('Sign-in code'))
@section('content')
<div class="card p-6 text-center sm:p-8">
  <span class="mx-auto grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700"><x-icon name="qr" class="size-6" /></span>
  <h1 class="mt-4 font-display text-2xl font-semibold">{{ __('Scan this from the app') }}</h1>
  <p class="mt-2 text-sm text-ink-500">{{ __('This code signs a computer in to the web app. Open the owner app, go to Menu → Use on computer, and scan the code there.') }}</p>
</div>
@endsection
