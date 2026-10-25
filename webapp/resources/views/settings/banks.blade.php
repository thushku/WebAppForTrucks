@extends('layouts.settings')
@section('title', __('Bank accounts'))
@section('settings')
<section class="card p-6">
  <h2 class="text-xl font-semibold">{{ __('Bank accounts') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('Where clients pay you. The default one prints on bills, with a UPI QR for the amount due when it has a UPI id. Numbers are stored encrypted.') }}</p>
  <div class="mt-4 space-y-3">
    @forelse ($accounts as $a)
      <form method="POST" action="{{ route('settings.banks.update', $a) }}" class="flex flex-wrap items-center gap-4 rounded-2xl border border-ink-100 p-4 text-sm">@csrf @method('PUT')
        <div class="min-w-48 flex-1"><div class="font-medium">{{ $a->label }} <span class="font-mono text-xs text-ink-500">{{ $a->masked() }}</span></div><div class="text-xs text-ink-500">{{ $a->bank_name }}{{ $a->ifsc ? ' · ' . $a->ifsc : '' }} · {{ $a->holder_name }}{{ $a->upi_id ? ' · UPI' : '' }}</div></div>
        <label class="flex items-center gap-1.5"><input type="checkbox" name="is_default" value="1" class="size-4 rounded border-ink-300" @checked($a->is_default)> {{ __('Default') }}</label>
        <label class="flex items-center gap-1.5"><input type="checkbox" name="show_on_invoice" value="1" class="size-4 rounded border-ink-300" @checked($a->show_on_invoice)> {{ __('On bills') }}</label>
        <label class="flex items-center gap-1.5"><input type="checkbox" name="active" value="1" class="size-4 rounded border-ink-300" @checked($a->active)> {{ __('In use') }}</label>
        <button class="btn-ghost text-xs">{{ __('Save') }}</button>
      </form>
    @empty
      <p class="rounded-xl bg-ink-50 px-4 py-3 text-sm">{{ __('No bank account yet: bills print without payment details.') }}</p>
    @endforelse
  </div>
</section>
<form method="POST" action="{{ route('settings.banks.store') }}" class="card grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-3" novalidate>@csrf
  <h3 class="text-lg font-semibold sm:col-span-2 xl:col-span-3">{{ __('Add a bank account') }}</h3>
  @foreach ([['label', __('Name for it (e.g. HDFC current)'), 60], ['bank_name', __('Bank'), 120], ['holder_name', __('Account holder (printed)'), 150], ['account_number', __('Account number'), 34], ['ifsc', 'IFSC', 11], ['branch', __('Branch'), 120], ['upi_id', __('UPI id (for the QR)'), 100], ['iban', 'IBAN', 34], ['swift', 'SWIFT / BIC', 11]] as [$name, $label, $max])
    <div><label class="label" for="bk_{{ $name }}">{{ $label }}</label><input id="bk_{{ $name }}" name="{{ $name }}" value="{{ $name === 'account_number' ? '' : old($name) }}" class="input {{ in_array($name, ['account_number', 'ifsc', 'iban', 'swift'], true) ? 'font-mono uppercase' : '' }}" maxlength="{{ $max }}" autocomplete="off">@error($name)<span class="error">{{ $message }}</span>@enderror</div>
  @endforeach
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="size-4 rounded border-ink-300"> {{ __('Make it the default') }}</label>
  <div class="text-end sm:col-span-2 xl:col-span-3"><button class="btn-primary"><x-icon name="plus" /> {{ __('Add') }}</button></div>
</form>
@endsection
