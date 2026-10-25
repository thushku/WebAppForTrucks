{{--
  Every settings page: the side list of settings pages the person may open,
  and the page itself. Pages and their permissions come from
  SettingsController::PAGES, so the menu and the routes cannot disagree.
--}}
@extends('layouts.app')
@section('content')
<div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
  <nav aria-label="{{ __('Settings') }}" class="lg:sticky lg:top-20 lg:self-start">
    <ul class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
      @foreach (\App\Http\Controllers\Settings\SettingsController::PAGES as $route => [$label, $icon, $permissions])
        @continue(! auth()->user()->canAny($permissions))
        @php($active = request()->routeIs($route, $route . '.*'))
        <li class="shrink-0">
          <a href="{{ route($route) }}" class="nav-link whitespace-nowrap {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>
            <x-icon :name="$icon" class="size-4" /> {{ __($label) }}
          </a>
        </li>
      @endforeach
    </ul>
  </nav>
  <div class="min-w-0 space-y-6">
    @yield('settings')
  </div>
</div>
@endsection
