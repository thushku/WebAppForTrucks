{{-- "+ New" in the trip form (M25, 13-parity #3): short forms in dialogs; the new record is selected at once. --}}
@php
  $forms = array_filter([
    'client' => auth()->user()->can('party.manage') ? [route('quick.client'), __('New client'), [['name', __('Name'), 'text', true], ['mobile', __('Mobile'), 'tel', false]]] : null,
    'person' => auth()->user()->can('people.manage') ? [route('quick.person'), __('New driver'), [['name', __('Name'), 'text', true], ['mobile', __('Mobile'), 'tel', false]]] : null,
    'truck' => auth()->user()->can('truck.manage') ? [route('quick.truck'), __('New truck'), [['reg_no', __('Registration number'), 'text', true], ['rc_owner_name', __('Owner on the RC'), 'text', true]]] : null,
    'place' => auth()->user()->can('settings.masters') ? [route('quick.place'), __('New place'), [['name', __('Name'), 'text', true], ['address', __('Full address (for a customer site)'), 'text', false]]] : null,
  ]);
@endphp
@foreach ($forms as $kind => [$url, $title, $fields])
  <dialog class="w-[min(28rem,calc(100vw-2rem))] rounded-2xl p-0 shadow-xl backdrop:bg-ink-900/40" data-quick-dialog="{{ $kind }}">
    <form method="dialog" class="space-y-4 p-6" data-quick-form data-url="{{ $url }}">
      <h2 class="text-lg font-semibold">{{ $title }}</h2>
      @if ($kind === 'person')<input type="hidden" name="type" value="driver" data-quick-person-type>@endif
      @foreach ($fields as [$name, $label, $type, $required])
        <div><label class="label" for="quick_{{ $kind }}_{{ $name }}">{{ $label }}</label>
          <input id="quick_{{ $kind }}_{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="input" maxlength="150" @required($required)></div>
      @endforeach
      <p class="error hidden" data-quick-error role="alert"></p>
      <p class="text-xs text-ink-500">{{ __('Fill in the rest later on its own page.') }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="btn-ghost" data-quick-close>{{ __('Cancel') }}</button>
        <button type="submit" class="btn-primary">{{ __('Add and choose') }}</button>
      </div>
    </form>
  </dialog>
@endforeach
