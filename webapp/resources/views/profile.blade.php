@extends('layouts.app')
@section('title', __('My profile'))
@section('content')
<div>
  <h1 class="font-display text-2xl font-semibold">{{ __('My profile') }}</h1>
  <p class="text-sm text-ink-500">{{ __('Your own details, the same in every company you work for and in the phone apps.') }}</p>
</div>

<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]" novalidate>
  @csrf
  @method('PUT')
  <section class="card flex flex-col items-center gap-4 p-6 text-center">
    @if ($account->photo_path)
      <img src="{{ route('profile.photo', ['v' => substr(md5($account->photo_path), 0, 8)]) }}" alt="{{ __('Your photo') }}" class="size-32 rounded-full object-cover">
    @else
      <span class="grid size-32 place-items-center rounded-full bg-brand-100 font-display text-3xl font-bold text-brand-800">{{ $account->initials() }}</span>
    @endif
    <div class="w-full">
      <label class="label" for="pf_photo">{{ $account->photo_path ? __('A new photo') : __('Add a photo') }}</label>
      <input id="pf_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="input">
      <p class="mt-1 text-xs text-ink-500">{{ __('JPG, PNG or WebP. The middle square is kept, small.') }}</p>
      @error('photo')<span class="error">{{ $message }}</span>@enderror
    </div>
    @if ($account->photo_path)
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_photo" value="1" class="size-4 rounded border-ink-300"> {{ __('Remove the photo') }}</label>
    @endif
  </section>

  <section class="card space-y-4 p-6">
    <div><label class="label" for="pf_name">{{ __('Name') }}</label><input id="pf_name" name="name" class="input" value="{{ old('name', $account->name) }}" maxlength="120" required>@error('name')<span class="error">{{ $message }}</span>@enderror</div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><span class="label">{{ __('Email') }}</span><p class="input bg-ink-50 text-ink-700">{{ $account->email ?: '—' }} @if ($account->email_verified_at)<span class="text-xs text-paid">· {{ __('confirmed') }}</span>@endif</p></div>
      <div><span class="label">{{ __('Mobile') }}</span><p class="input bg-ink-50 text-ink-700">{{ $account->mobile ?: '—' }} @if ($account->mobile_verified_at)<span class="text-xs text-paid">· {{ __('confirmed') }}</span>@endif</p></div>
    </div>
    <p class="text-xs text-ink-500">{{ __('You sign in with your email and mobile, so they are changed only by your company’s owner, after the new one is confirmed.') }}</p>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="pf_locale">{{ __('Screen language') }}</label>
        <select id="pf_locale" name="locale" class="input">@foreach (\App\Support\Locales::enabled() as $code)<option value="{{ $code }}" @selected(old('locale', $account->locale) === $code)>{{ \App\Support\Locales::NATIVE[$code] }}</option>@endforeach</select>
        @error('locale')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="pf_dob">{{ __('Date of birth (optional)') }}</label><input id="pf_dob" name="date_of_birth" type="date" class="input" value="{{ old('date_of_birth', $account->date_of_birth?->toDateString()) }}">@error('date_of_birth')<span class="error">{{ $message }}</span>@enderror</div>
    </div>
    <div class="flex flex-wrap items-center gap-3 border-t border-ink-100 pt-4">
      <button class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button>
      <a href="{{ route('notifications.settings') }}" class="text-sm text-brand-700 hover:underline">{{ __('My notifications') }}</a>
    </div>
  </section>
</form>
@endsection
