{{--
  The main menu (blueprint 04 §1): grouped items on tinted tiles, the style
  the founder liked in Clinic Ledger. Each item shows only if the person holds
  its permission; modules not built yet are listed as "soon" so the shape of
  the product is visible from day one.
--}}
@php
  $groups = [
    __('Work') => [
      ['dashboard', 'home', __('Dashboard'), 'dashboard.view', 'bg-brand-50 text-brand-700'],
      ['trips.index', 'route', __('Trips'), 'trip.view', 'bg-amber-50 text-amber-700', 'trips.'],
      [null, 'check', __('Approvals'), 'event.verify', 'bg-emerald-50 text-emerald-700'],
      [null, 'map', __('Live map'), 'map.view', 'bg-sky-50 text-sky-700'],
    ],
    __('Money') => [
      [null, 'receipt', __('Invoices & LR'), 'invoice.view', 'bg-violet-50 text-violet-700'],
      [null, 'ledger', __('Ledgers'), 'ledger.view', 'bg-rose-50 text-rose-700'],
      [null, 'wallet', __('Accounts'), 'accounts.view', 'bg-teal-50 text-teal-700'],
      [null, 'payroll', __('Payroll'), 'payroll.view', 'bg-orange-50 text-orange-700'],
    ],
    __('Fleet & people') => [
      ['trucks.index', 'truck', __('Trucks'), 'truck.view', 'bg-blue-50 text-blue-700', 'trucks.'],
      ['complaints.index', 'wrench', __('Complaints'), ['complaint.manage'], 'bg-red-50 text-red-700', 'complaints.'],
      ['documents.index', 'folder', __('Documents'), 'document.view', 'bg-yellow-50 text-yellow-700', 'documents.'],
      ['people.index', 'users', __('People'), 'people.view', 'bg-indigo-50 text-indigo-700', 'people.'],
      ['parties.index', 'handshake', __('Parties'), 'party.view', 'bg-lime-50 text-lime-700', 'parties.'],
    ],
    __('Insight') => [
      [null, 'bar-chart', __('Reports'), 'report.view', 'bg-cyan-50 text-cyan-700'],
      ['settings.index', 'settings', __('Settings'), \App\Http\Controllers\Settings\SettingsController::anyPermission(), 'bg-slate-100 text-slate-700', 'settings.'],
    ],
  ];
@endphp
<nav id="main-nav" class="-mx-1 flex-1 overflow-y-auto px-1" aria-label="{{ __('Main menu') }}">
  @foreach ($groups as $label => $items)
    @php($visible = array_filter($items, fn ($i) => auth()->user()?->canAny((array) $i[3])))
    @continue(empty($visible))
    <div class="nav-group-label">{{ $label }}</div>
    <ul class="space-y-0.5">
      @foreach ($visible as $item)
        @php([$route, $icon, $text, $permission, $tile] = $item)
        {{-- The item is lit on every page of its area ("settings.*"), not only its own. --}}
        @php($active = $route && request()->routeIs(($item[5] ?? $route) . '*'))
        <li>
          @if ($route && Route::has($route))
            <a href="{{ route($route) }}" class="nav-link {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>
              <span class="nav-tile {{ $tile }}"><x-icon :name="$icon" class="size-4" /></span>{{ $text }}
            </a>
          @else
            <span class="nav-link cursor-default opacity-60" title="{{ __('Coming in a later milestone') }}">
              <span class="nav-tile {{ $tile }}"><x-icon :name="$icon" class="size-4" /></span>{{ $text }}
              <span class="ms-auto rounded-md bg-ink-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink-500">{{ __('soon') }}</span>
            </span>
          @endif
        </li>
      @endforeach
    </ul>
  @endforeach
</nav>
