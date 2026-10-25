@extends('layouts.guest')
@section('title', __('Choose a company'))
@section('content')
<div class="card p-6 sm:p-8">
  <h1 class="font-display text-2xl font-semibold">{{ __('Choose a company') }}</h1>
  @if ($memberships->isEmpty())
    <p class="mt-2 text-sm text-ink-500">{{ __('Your login is not part of any active company yet. Ask your company to invite you, or start your own free trial.') }}</p>
    <a href="{{ route('signup') }}" class="btn-secondary mt-5 w-full">{{ __('Start a free trial') }}</a>
  @else
    <p class="mt-1 text-sm text-ink-500">{{ __('You work with more than one company. Which one do you want to open?') }}</p>
    <div class="mt-5 space-y-2">
      @foreach ($memberships as $member)
        <form method="POST" action="{{ route('company.switch') }}">
          @csrf
          <input type="hidden" name="company" value="{{ $member->company->uuid }}">
          <button type="submit" class="flex w-full items-center gap-3 rounded-xl border border-ink-100 bg-white px-4 py-3 text-start hover:border-brand-300">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon name="building" /></span>
            <span class="min-w-0 flex-1">
              <span class="block truncate font-semibold">{{ $member->company->name }}</span>
              <span class="block text-xs text-ink-500">{{ $member->title ?: __('Member') }} · {{ $member->company->country }}</span>
            </span>
            <x-icon name="arrow-right" class="size-4 text-ink-400" />
          </button>
        </form>
      @endforeach
    </div>
  @endif
  <form method="POST" action="{{ route('logout') }}" class="mt-5 text-center">
    @csrf
    <button type="submit" class="text-sm text-ink-500 hover:underline">{{ __('Sign out') }}</button>
  </form>
</div>
@endsection
