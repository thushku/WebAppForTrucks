@extends('layouts.app')
@section('title', $person->name)
@section('content')
@php
  $state = $person->licenceState();
  $dial  = $person->mobile ? preg_replace('/[^0-9+]/', '', $person->mobile) : null;
@endphp
<section class="card p-6">
  <div class="flex flex-wrap items-start gap-4">
    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-indigo-50 font-display text-lg font-bold text-indigo-700">{{ $person->initials() }}</span>
    <div class="min-w-0 flex-1">
      <h2 class="text-2xl font-semibold">{{ $person->name }}</h2>
      <p class="mt-0.5 text-sm text-ink-500">{{ __(\App\Modules\People\Models\Person::TYPES[$person->type]) }}{{ $person->employee_code ? ' · ' . $person->employee_code : '' }}{{ $person->branch ? ' · ' . $person->branch->name : '' }}</p>
      <div class="mt-2 flex flex-wrap gap-1.5">
        <span class="pill {{ $person->status === 'active' ? 'bg-emerald-50 text-paid' : 'bg-ink-100 text-ink-500' }}">{{ __(\App\Modules\People\Models\Person::STATUSES[$person->status]) }}</span>
        @if ($state === 'expired')<span class="pill bg-red-50 text-overdue">{{ __('Licence expired') }}</span>@elseif ($state === 'expiring')<span class="pill bg-amber-50 text-partial">{{ __('Licence expires :date', ['date' => $person->licence_expiry->format('d M Y')]) }}</span>@elseif ($state === 'missing')<span class="pill bg-red-50 text-overdue">{{ __('Licence missing') }}</span>@endif
        @if ($person->hazardous_endorsement)<span class="pill bg-amber-50 text-partial">{{ __('Hazardous goods') }}</span>@endif
        @if ($papers)
          <a href="{{ route('documents.owner', ['person', $person->id]) }}" class="pill {{ ['green' => 'bg-emerald-50 text-paid', 'amber' => 'bg-amber-50 text-partial', 'red' => 'bg-red-50 text-overdue'][$papers['state']] }}">{{ $papers['state'] === 'green' ? __('Papers in order') : collect([
            $papers['expired'] ? trans_choice(':count expired|:count expired', $papers['expired']) : null,
            $papers['missing'] ? trans_choice(':count missing|:count missing', $papers['missing']) : null,
            $papers['due'] ? trans_choice(':count due soon|:count due soon', $papers['due']) : null,
          ])->filter()->join(' · ') }}</a>
        @endif
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      @if ($dial)
        <a href="tel:{{ $dial }}" class="btn-secondary"><x-icon name="phone" /> {{ __('Call') }}</a>
        <a href="https://wa.me/{{ ltrim($dial, '+') }}?text={{ rawurlencode(__('Hello :name', ['name' => $person->name])) }}" class="btn-secondary" target="_blank" rel="noopener noreferrer">{{ __('WhatsApp') }}</a>
        <a href="https://wa.me/?text={{ rawurlencode($person->name . ' — ' . __(\App\Modules\People\Models\Person::TYPES[$person->type]) . ' — ' . $person->mobile) }}" class="btn-secondary" target="_blank" rel="noopener noreferrer"><x-icon name="send" /> {{ __('Share contact') }}</a>
      @endif
      @php($ledgerId = auth()->user()->canAny(['ledger.view', 'accounts.view']) ? \App\Modules\Accounts\Models\LedgerAccount::where('system_key', 'person:' . $person->id)->value('id') : null)
      @if ($ledgerId)<a href="{{ route('ledgers.show', $ledgerId) }}" class="btn-secondary"><x-icon name="ledger" /> {{ __('Khata') }}</a>@endif
      @can('people.manage')<a href="{{ route('people.edit', $person) }}" class="btn-primary">{{ __('Edit') }}</a>@endcan
    </div>
  </div>
  <nav class="mt-5 flex gap-1 overflow-x-auto border-b border-ink-100" aria-label="{{ __('Person sections') }}">
    @foreach ($tabs as $key => $label)
      <a href="{{ route('people.show', [$person, 'tab' => $key === 'profile' ? null : $key]) }}" class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-900' }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a href="{{ route('people.show', $person) }}#leave" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-500 hover:text-ink-900">{{ __('Leave') }}</a>
    @can('document.view')<a href="{{ route('documents.owner', ['person', $person->id]) }}" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-500 hover:text-ink-900">{{ __('Documents') }}</a>@endcan
    @canany(['ledger.view', 'accounts.view'])@if ($ledgerId)<a href="{{ route('ledgers.show', $ledgerId) }}" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-500 hover:text-ink-900">{{ __('Ledger') }}</a>@endif @endcanany
  </nav>
</section>

@if ($tab !== 'profile')
  @include('people.partials.' . $tab)
@else

<div class="grid gap-6 xl:grid-cols-2">
  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Details') }}</h3>
    <dl class="mt-3 grid grid-cols-[10rem_1fr] gap-x-4 gap-y-2 text-sm">
      <dt class="text-ink-500">{{ __('Mobile') }}</dt><dd>{{ collect([$person->mobile, $person->alt_mobile])->filter()->join(' · ') ?: '—' }}</dd>
      <dt class="text-ink-500">{{ __('Licence') }}</dt><dd>{{ $person->licence_no ? $person->licence_no . ($person->licence_class ? ' · ' . $person->licence_class : '') . ($person->licence_expiry ? ' · ' . __('until :date', ['date' => $person->licence_expiry->format('d M Y')]) : '') : '—' }}</dd>
      @if ($person->badge_no)<dt class="text-ink-500">{{ __('Badge') }}</dt><dd>{{ $person->badge_no }}</dd>@endif
      @if ($person->date_of_birth)<dt class="text-ink-500">{{ __('Born') }}</dt><dd>{{ $person->date_of_birth->format('d M Y') }} ({{ $person->date_of_birth->age }})</dd>@endif
      @if ($person->joining_date)<dt class="text-ink-500">{{ __('Joined') }}</dt><dd>{{ $person->joining_date->format('d M Y') }}</dd>@endif
      @if ($person->leaving_date)<dt class="text-ink-500">{{ __('Left') }}</dt><dd>{{ $person->leaving_date->format('d M Y') }}</dd>@endif
      <dt class="text-ink-500">{{ __('Usual truck') }}</dt><dd>@if ($person->defaultTruck)<a href="{{ route('trucks.show', $person->defaultTruck) }}" class="docno font-mono text-brand-700 hover:underline">{{ $person->defaultTruck->reg_no }}</a>@else — @endif</dd>
      <dt class="text-ink-500">{{ __('Pay') }}</dt>
      <dd>{{ __(\App\Modules\People\Models\Person::PAY_BASES[$person->pay_basis]) }}:
        {{ match ($person->pay_basis) { 'per_trip' => $person->per_trip_rate ? \App\Support\Money::company($person->per_trip_rate) : '—', 'per_km' => $person->per_km_rate ? \App\Support\Money::company($person->per_km_rate) : '—', default => $person->monthly_salary ? \App\Support\Money::company($person->monthly_salary) : '—' } }}</dd>
      @if ($person->emergency_name)<dt class="text-ink-500">{{ __('Emergency') }}</dt><dd>{{ $person->emergency_name }} · {{ $person->emergency_mobile }}</dd>@endif
      @if ($person->blood_group)<dt class="text-ink-500">{{ __('Blood group') }}</dt><dd>{{ $person->blood_group }}</dd>@endif
      @if ($person->address)<dt class="text-ink-500">{{ __('Address') }}</dt><dd class="whitespace-pre-line">{{ $person->address }}</dd>@endif
      @if ($person->id_last4)
        <dt class="text-ink-500">{{ __(\App\Modules\People\Models\Person::ID_TYPES[$person->id_type] ?? 'ID') }}</dt>
        <dd class="flex items-center gap-2 font-mono">
          {{ $revealed ?? $person->maskedId() }}
          @if (! $revealed)
            @can('people.id_documents')
              <form method="POST" action="{{ route('people.reveal-id', $person) }}">@csrf<button class="font-sans text-xs font-semibold text-brand-700 hover:underline">{{ __('Show') }}</button></form>
            @endcan
          @endif
        </dd>
      @endif
      @if ($person->bank_last4 || $person->bank_name)
        <dt class="text-ink-500">{{ __('Bank') }}</dt><dd>{{ collect([$person->bank_name, $person->bank_last4 ? '••••' . $person->bank_last4 : null, $person->bank_ifsc])->filter()->join(' · ') }}</dd>
      @endif
    </dl>
    @if ($person->notes)<p class="mt-4 whitespace-pre-line rounded-xl bg-ink-50 p-3 text-sm">{{ $person->notes }}</p>@endif
  </section>

  <section class="card p-6">
    <h3 class="font-semibold">{{ __('Crew app') }}</h3>
    @if ($code)
      <div class="mt-4 rounded-2xl border border-brand-200 bg-brand-50 p-5 text-center" role="status">
        <p class="text-sm text-brand-800">{{ __('Give this code to :name. It works once, for 72 hours.', ['name' => $person->name]) }}</p>
        <p class="docno mt-3 font-mono text-4xl font-bold tracking-[.2em] text-brand-900">{{ substr($code, 0, 4) }} {{ substr($code, 4) }}</p>
        <div class="mx-auto mt-4 size-40 rounded-xl bg-white p-2">{!! $qr !!}</div>
        <p class="mt-3 text-xs text-brand-800">{{ __('In the crew app: enter :mobile and this code, or scan the QR.', ['mobile' => $person->mobile]) }}</p>
      </div>
    @else
      <dl class="mt-3 grid grid-cols-[10rem_1fr] gap-x-4 gap-y-2 text-sm">
        <dt class="text-ink-500">{{ __('Login') }}</dt><dd>{{ $person->company_user_id ? __('Linked to :mobile', ['mobile' => $person->member?->account?->mobile ?? $person->mobile]) : __('Not set up yet') }}</dd>
        @if ($used)<dt class="text-ink-500">{{ __('Activated') }}</dt><dd>{{ $used->used_at->format('d M Y, H:i') }}{{ $used->used_device ? ' · ' . $used->used_device : '' }}</dd>@endif
        @if ($pending)<dt class="text-ink-500">{{ __('Code waiting') }}</dt><dd>{{ __('Made :when, works until :until', ['when' => $pending->created_at->diffForHumans(), 'until' => $pending->expires_at->format('d M, H:i')]) }}</dd>@endif
      </dl>
    @endif
    @can('people.manage')
      @if ($person->status !== 'left')
        <form method="POST" action="{{ route('people.activate', $person) }}" class="mt-4" @if ($person->company_user_id) data-confirm="{{ __('Make a new code? Any code not used yet stops working.') }}" @endif>
          @csrf
          <button class="btn-secondary"><x-icon name="key" /> {{ $person->company_user_id || $pending ? __('Make a new activation code') : __('Make an activation code') }}</button>
        </form>
        @error('mobile')<p class="error">{{ $message }}</p>@enderror
        @error('status')<p class="error">{{ $message }}</p>@enderror
      @endif
    @endcan
    <p class="mt-4 text-xs text-ink-500">{{ __('No SMS is needed: read the code out or show the QR. The app then asks for a PIN or fingerprint each time it opens.') }}</p>
  </section>
</div>
@if ($khata)
  @include('people.partials.cash')
@endif
@include('people.partials.leave')
@if ($payslips->isNotEmpty())
  <section class="card overflow-x-auto">
    <h2 class="border-b border-ink-100 px-5 py-4 text-lg font-semibold">{{ __('Pay') }}</h2>
    <table class="table">
      <thead><tr><th>{{ __('Period') }}</th><th class="text-end">{{ __('Net pay') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
      <tbody>
        @foreach ($payslips as $slip)
          <tr>
            <td><a href="{{ route('payroll.show', $slip->run) }}" class="text-brand-700 hover:underline">{{ $slip->run->periodLabel() }}</a></td>
            <td class="money text-end">{{ \App\Support\Money::company($slip->net) }}</td>
            <td>{{ $slip->status === 'paid' ? __('Paid :date', ['date' => $slip->paid_at->format('d M Y')]) : __(\App\Modules\Payroll\Models\PayRun::STATUSES[$slip->run->status] ?? $slip->run->status) }}</td>
            <td class="text-end"><a href="{{ route('payroll.slip', [$slip->run, $slip->id]) }}" class="text-sm text-brand-700 hover:underline" target="_blank">{{ __('Payslip') }}</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
@endif
@endif
@endsection
