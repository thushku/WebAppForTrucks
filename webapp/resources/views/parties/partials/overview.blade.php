<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Details') }}</h3>
    <dl class="mt-3 grid grid-cols-[9rem_1fr] gap-x-4 gap-y-2 text-sm">
      <dt class="text-ink-500">{{ __('Mobile') }}</dt><dd>{{ $party->mobile ?: '—' }}</dd>
      <dt class="text-ink-500">{{ __('Email') }}</dt><dd class="truncate">{{ $party->email ?: '—' }}</dd>
      <dt class="text-ink-500">{{ __('Address') }}</dt>
      <dd>{{ collect([$party->address_line1, $party->address_line2, $party->city, \App\Support\Regions::name($party->country, $party->state_code), $party->postal_code])->filter()->join(', ') ?: '—' }}</dd>
      @if ($party->pan)<dt class="text-ink-500">{{ __('PAN') }}</dt><dd class="font-mono">{{ $party->pan }}</dd>@endif
      @if ($party->tan)<dt class="text-ink-500">{{ __('TAN') }}</dt><dd class="font-mono">{{ $party->tan }}</dd>@endif
      @if ($party->udyam_number)<dt class="text-ink-500">{{ __('Udyam') }}</dt><dd class="font-mono">{{ $party->udyam_number }}</dd>@endif
      @if ($party->hasRole('client'))
        <dt class="text-ink-500">{{ __('Credit') }}</dt>
        <dd>
          {{ $party->credit_limit ? \App\Support\Money::company($party->credit_limit) : __('No limit') }}
          · {{ $party->credit_days ? trans_choice(':count day|:count days', $party->credit_days) : __('on receipt') }}
          {{ $party->due_basis === 'submission' ? __('from bill submission') : __('from invoice date') }}
        </dd>
        <dt class="text-ink-500">{{ __('TDS') }}</dt><dd>{{ $party->tds_deducted ? trim(($party->tds_section ?: '') . ' ' . ($party->tds_rate !== null ? rtrim(rtrim(number_format((float) $party->tds_rate, 2), '0'), '.') . '%' : '')) ?: __('Deducted') : __('Not deducted') }}</dd>
      @endif
      @if ($party->vendor_category)<dt class="text-ink-500">{{ __('Sells') }}</dt><dd>{{ __(\App\Modules\Parties\Models\Party::VENDOR_CATEGORIES[$party->vendor_category] ?? $party->vendor_category) }}</dd>@endif
      @if ($party->supplier_type)<dt class="text-ink-500">{{ __('Supplier') }}</dt><dd>{{ __(\App\Modules\Parties\Models\Party::SUPPLIER_TYPES[$party->supplier_type] ?? $party->supplier_type) }} @if ($party->rating)<span class="text-road-500">{{ str_repeat('★', $party->rating) }}</span>@endif</dd>@endif
      @if ((float) $party->opening_balance > 0)
        <dt class="text-ink-500">{{ __('Opening balance') }}</dt>
        <dd class="{{ $party->opening_side === 'payable' ? 'text-overdue' : 'text-paid' }}">
          {{ $party->opening_side === 'payable' ? __('You will give') : __('They will give you') }}
          <span class="money font-semibold">{{ \App\Support\Money::company($party->opening_balance) }}</span>
          <span class="text-xs text-ink-500">({{ __('as on :date', ['date' => $party->opening_as_of?->format('d M Y')]) }})</span>
        </dd>
      @endif
      @if ($members->isNotEmpty())
        <dt class="text-ink-500">{{ __('Group members') }}</dt>
        <dd>@foreach ($members as $m)<a href="{{ route('parties.show', $m) }}" class="text-brand-700 hover:underline">{{ $m->name }}</a>@if (! $loop->last), @endif @endforeach</dd>
      @endif
    </dl>
    @if ($party->notes)<p class="mt-4 whitespace-pre-line rounded-xl bg-ink-50 p-3 text-sm text-ink-700">{{ $party->notes }}</p>@endif
  </section>

  <section class="card p-6">
    <h3 class="font-semibold">{{ __(':label registrations', ['label' => $taxLabel]) }}</h3>
    <p class="mt-1 text-xs text-ink-500">{{ __('One per state or plant you bill. The main one is used unless a trip says otherwise.') }}</p>
    <ul class="mt-3 divide-y divide-ink-100">
      @forelse ($party->taxIds as $t)
        <li class="flex flex-wrap items-center gap-3 py-2.5 {{ $t->active ? '' : 'opacity-50' }}">
          <span class="docno font-mono text-sm font-semibold">{{ $t->tax_id }}</span>
          @if ($t->state_code)<span class="text-xs text-ink-500">{{ \App\Support\Regions::name($t->country, $t->state_code) }}</span>@endif
          @if ($t->is_default)<span class="pill bg-brand-50 text-brand-700">{{ __('Main') }}</span>@endif
          @if ($t->address)<span class="w-full text-xs text-ink-500">{{ $t->address }}</span>@endif
          @can('party.manage')
            <span class="ms-auto flex gap-2">
              @unless ($t->is_default)
                <form method="POST" action="{{ route('parties.tax-ids.default', [$party, $t]) }}">@csrf<button class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Make main') }}</button></form>
              @endunless
              <form method="POST" action="{{ route('parties.tax-ids.toggle', [$party, $t]) }}">@csrf<button class="text-xs text-ink-500 hover:underline">{{ $t->active ? __('Stop using') : __('Use again') }}</button></form>
            </span>
          @endcan
        </li>
      @empty
        <li class="py-3 text-sm text-ink-500">{{ __('None yet (unregistered party).') }}</li>
      @endforelse
    </ul>
    @can('party.manage')
      <form method="POST" action="{{ route('parties.tax-ids.store', $party) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_1fr_auto]" novalidate>
        @csrf
        <input name="tax_id" class="input font-mono uppercase" placeholder="{{ $taxLabel }}" maxlength="20" value="{{ old('tax_id') }}" aria-label="{{ $taxLabel }}">
        <input name="address" class="input" placeholder="{{ __('Billing address (optional)') }}" maxlength="500" aria-label="{{ __('Billing address') }}">
        <button class="btn-secondary"><x-icon name="plus" /> {{ __('Add') }}</button>
        @error('tax_id')<span class="error sm:col-span-3">{{ $message }}</span>@enderror
      </form>
    @endcan
  </section>

  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Contacts') }}</h3>
    <ul class="mt-3 divide-y divide-ink-100">
      @forelse ($party->contacts as $c)
        <li class="flex flex-wrap items-center gap-3 py-2.5">
          <div class="min-w-0 flex-1">
            <div class="font-medium">{{ $c->name }} @if ($c->is_primary)<span class="pill ms-1 bg-brand-50 text-brand-700">{{ __('Main') }}</span>@endif</div>
            <div class="text-xs text-ink-500">{{ collect([$c->role ? __(\App\Modules\Parties\Models\PartyContact::ROLES[$c->role] ?? $c->role) : null, $c->mobile, $c->alt_mobile, $c->email])->filter()->join(' · ') }}</div>
          </div>
          @if ($c->mobile)<a href="tel:{{ $c->mobile }}" class="btn-ghost px-2 py-1.5" aria-label="{{ __('Call :name', ['name' => $c->name]) }}"><x-icon name="phone" class="size-4" /></a>@endif
          @can('party.manage')
            <form method="POST" action="{{ route('parties.contacts.destroy', [$party, $c]) }}" data-confirm="{{ __('Remove :name?', ['name' => $c->name]) }}">@csrf @method('DELETE')<button class="btn-ghost px-2 py-1.5" aria-label="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /></button></form>
          @endcan
        </li>
      @empty
        <li class="py-3 text-sm text-ink-500">{{ __('No contacts yet.') }}</li>
      @endforelse
    </ul>
    @can('party.manage')
      <form method="POST" action="{{ route('parties.contacts.store', $party) }}" class="mt-3 grid gap-2 sm:grid-cols-2" novalidate>
        @csrf
        <input name="name" class="input" placeholder="{{ __('Name') }}" maxlength="120" aria-label="{{ __('Contact name') }}" required>
        <select name="role" class="input" aria-label="{{ __('Role') }}">
          <option value="">{{ __('Role') }}</option>
          @foreach (\App\Modules\Parties\Models\PartyContact::ROLES as $v => $t)<option value="{{ $v }}">{{ __($t) }}</option>@endforeach
        </select>
        <input name="mobile" class="input" placeholder="{{ __('Mobile') }}" maxlength="20" inputmode="tel" aria-label="{{ __('Mobile') }}">
        <input name="email" type="email" class="input" placeholder="{{ __('Email') }}" maxlength="255" aria-label="{{ __('Email') }}">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_primary" value="1" class="size-4 rounded border-ink-300"> {{ __('Main contact') }}</label>
        <button class="btn-secondary justify-self-end"><x-icon name="plus" /> {{ __('Add contact') }}</button>
        @foreach (['name', 'mobile', 'email'] as $f)@error($f)<span class="error sm:col-span-2">{{ $message }}</span>@enderror @endforeach
      </form>
    @endcan
  </section>

  @if ($party->hasRole('supplier') || $party->hasRole('vendor') || $party->hasRole('broker'))
  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Bank accounts') }}</h3>
    <p class="mt-1 text-xs text-ink-500">{{ __('Where you pay them. Numbers are stored encrypted and shown as the last 4 digits.') }}</p>
    <ul class="mt-3 divide-y divide-ink-100">
      @forelse ($party->bankAccounts as $b)
        <li class="flex flex-wrap items-center gap-3 py-2.5">
          <div class="min-w-0 flex-1">
            <div class="font-medium">{{ $b->label }} @if ($b->is_default)<span class="pill ms-1 bg-brand-50 text-brand-700">{{ __('Default') }}</span>@endif</div>
            <div class="text-xs text-ink-500">{{ collect([$b->account_holder_name, $b->ifsc, $b->swift, $b->maskedUpi()])->filter()->join(' · ') }}</div>
          </div>
          @can('settings.bank')
            <form method="POST" action="{{ route('parties.banks.destroy', [$party, $b]) }}" data-confirm="{{ __('Remove this bank account?') }}">@csrf @method('DELETE')<button class="btn-ghost px-2 py-1.5" aria-label="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /></button></form>
          @endcan
        </li>
      @empty
        <li class="py-3 text-sm text-ink-500">{{ __('No bank account yet.') }}</li>
      @endforelse
    </ul>
    @can('settings.bank')
      <form method="POST" action="{{ route('parties.banks.store', $party) }}" class="mt-3 grid gap-2 sm:grid-cols-2" novalidate autocomplete="off">
        @csrf
        <input name="account_holder_name" class="input sm:col-span-2" placeholder="{{ __('Account holder name') }}" maxlength="150" aria-label="{{ __('Account holder name') }}" value="{{ old('account_holder_name') }}">
        <input name="bank_name" class="input" placeholder="{{ __('Bank') }}" maxlength="120" aria-label="{{ __('Bank') }}" value="{{ old('bank_name') }}">
        @if ($party->country === 'IN')
          <input name="ifsc" class="input font-mono uppercase" placeholder="IFSC" maxlength="11" aria-label="IFSC" value="{{ old('ifsc') }}">
          <input name="account_number" class="input font-mono" placeholder="{{ __('Account number') }}" maxlength="18" inputmode="numeric" aria-label="{{ __('Account number') }}">
        @else
          <input name="iban" class="input font-mono uppercase" placeholder="IBAN" maxlength="34" aria-label="IBAN">
          <input name="swift" class="input font-mono uppercase" placeholder="SWIFT / BIC" maxlength="11" aria-label="SWIFT">
        @endif
        <input name="upi_id" class="input" placeholder="{{ __('UPI id (optional)') }}" maxlength="100" aria-label="{{ __('UPI id') }}">
        <button class="btn-secondary sm:col-span-2 sm:justify-self-end"><x-icon name="plus" /> {{ __('Add bank account') }}</button>
        @foreach (['account_holder_name', 'account_number', 'ifsc', 'iban', 'swift', 'upi_id'] as $f)@error($f)<span class="error sm:col-span-2">{{ $message }}</span>@enderror @endforeach
      </form>
    @else
      <p class="mt-3 text-xs text-ink-500">{{ __('Only people allowed to change bank details can add one.') }}</p>
    @endcan
  </section>
  @endif
</div>
