<section class="card overflow-hidden">
  <div class="p-6 pb-4">
    <h3 class="text-lg font-semibold">{{ __('Trucks of this supplier') }}</h3>
    <p class="mt-1 text-sm text-ink-500">{{ __('Hired trucks you can pick when a trip uses a market truck.') }}</p>
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Truck') }}</th><th>{{ __('Type') }}</th><th>{{ __('RC owner') }}</th><th>{{ __('Usual driver') }}</th><th></th></tr></thead>
      <tbody>
        @forelse ($party->vehicles as $v)
          <tr class="{{ $v->active ? '' : 'opacity-50' }}">
            <td class="docno font-mono font-semibold">{{ $v->reg_no }}</td>
            <td class="text-sm">{{ collect([$v->truck_type, $v->body_type, $v->capacity_mt ? rtrim(rtrim((string) $v->capacity_mt, '0'), '.') . ' MT' : null])->filter()->join(' · ') }}</td>
            <td class="text-sm">{{ $v->rc_owner_name }}</td>
            <td class="text-sm">{{ collect([$v->driver_name, $v->driver_mobile])->filter()->join(' · ') }}</td>
            <td class="text-end">
              @can('party.manage')
                <form method="POST" action="{{ route('parties.vehicles.update', [$party, $v]) }}" class="inline">
                  @csrf @method('PUT')
                  @foreach (['reg_no', 'truck_type', 'body_type', 'capacity_mt', 'rc_owner_name', 'driver_name', 'driver_mobile', 'driver_licence', 'notes'] as $f)
                    <input type="hidden" name="{{ $f }}" value="{{ $v->$f }}">
                  @endforeach
                  @unless ($v->active)<input type="hidden" name="active" value="1">@endunless
                  <button class="text-xs font-semibold text-brand-700 hover:underline">{{ $v->active ? __('Stop using') : __('Use again') }}</button>
                </form>
              @endcan
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ __('No trucks yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@can('party.manage')
<form method="POST" action="{{ route('parties.vehicles.store', $party) }}" class="card p-6" novalidate>
  @csrf
  <h3 class="font-semibold">{{ __('Add a truck') }}</h3>
  <div class="mt-4 grid gap-3 sm:grid-cols-4">
    <div><label class="label" for="v_reg">{{ __('Registration no.') }}</label><input id="v_reg" name="reg_no" class="input font-mono uppercase" value="{{ old('reg_no') }}" maxlength="20" placeholder="KL-07-AB-1234" required>@error('reg_no')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="v_type">{{ __('Truck type') }}</label><input id="v_type" name="truck_type" class="input" value="{{ old('truck_type') }}" maxlength="40" placeholder="{{ __('12 wheels') }}"></div>
    <div><label class="label" for="v_cap">{{ __('Capacity (MT)') }}</label><input id="v_cap" name="capacity_mt" type="number" min="0" step="0.001" class="input" value="{{ old('capacity_mt') }}"></div>
    <div><label class="label" for="v_owner">{{ __('RC owner name') }}</label><input id="v_owner" name="rc_owner_name" class="input" value="{{ old('rc_owner_name') }}" maxlength="150"></div>
    <div><label class="label" for="v_driver">{{ __('Driver name') }}</label><input id="v_driver" name="driver_name" class="input" value="{{ old('driver_name') }}" maxlength="120"></div>
    <div><label class="label" for="v_mobile">{{ __('Driver mobile') }}</label><input id="v_mobile" name="driver_mobile" class="input" value="{{ old('driver_mobile') }}" maxlength="20" inputmode="tel">@error('driver_mobile')<span class="error">{{ $message }}</span>@enderror</div>
    <div><label class="label" for="v_lic">{{ __('Driving licence') }}</label><input id="v_lic" name="driver_licence" class="input font-mono uppercase" value="{{ old('driver_licence') }}" maxlength="30"></div>
    <div class="self-end"><button class="btn-primary w-full"><x-icon name="plus" /> {{ __('Add truck') }}</button></div>
  </div>
</form>
@endcan
